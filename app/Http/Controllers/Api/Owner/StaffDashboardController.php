<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use App\Models\VenueStaffShiftSchedule;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffDashboardController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $selectedClusterId = $request->query('venue_cluster_id');

        // Lấy danh sách cụm sân được phân công/sở hữu
        $visibleClusterIds = $this->visibleClusterIds($userId);

        if ($selectedClusterId) {
            if (! $visibleClusterIds->contains($selectedClusterId)) {
                return response()->json([
                    'message' => 'Bạn không có quyền xem dữ liệu của cụm sân này.',
                ], 403);
            }
            $clusterId = $selectedClusterId;
        } else {
            $clusterId = $visibleClusterIds->first();
        }

        if (! $clusterId) {
            return response()->json([
                'active_courts_count' => 0,
                'total_courts_count' => 0,
                'today_bookings_count' => 0,
                'playing_now_count' => 0,
                'upcoming_bookings_count' => 0,
                'court_availabilities' => [],
                'notifications' => [],
                'my_shift_today' => null,
            ]);
        }

        $today = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
        $nowTime = Carbon::now('Asia/Ho_Chi_Minh')->toTimeString();

        // 1. Số sân đang hoạt động
        $totalCourts = VenueCourt::where('venue_cluster_id', $clusterId)->count();
        $activeCourts = VenueCourt::where('venue_cluster_id', $clusterId)->where('status', 'active')->count();

        // 2. Số đơn đặt hôm nay
        $todayBookingsCount = Booking::where('venue_cluster_id', $clusterId)
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed'])
            ->count();

        // 3. Số khách đang chơi (trong khung giờ hiện tại)
        $playingNowCount = Booking::where('venue_cluster_id', $clusterId)
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->where('start_time', '<=', $nowTime)
            ->where('end_time', '>=', $nowTime)
            ->count();

        // 4. Số khách sắp đến (ngày hôm nay, start_time > now)
        $upcomingBookingsCount = Booking::where('venue_cluster_id', $clusterId)
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['confirmed', 'pending_payment', 'pending_approval'])
            ->where('start_time', '>', $nowTime)
            ->count();

        // 5. Ca trực hôm nay của nhân viên đăng nhập
        $myShiftToday = null;
        if (Schema::hasTable('venue_staff_shifts') && Schema::hasTable('venue_staff_shift_schedules')) {
            $myShiftToday = VenueStaffShiftSchedule::query()
                ->with(['shift'])
                ->where('user_id', $userId)
                ->where('venue_cluster_id', $clusterId)
                ->where('date', $today)
                ->first();
        }

        // 6. Thời gian trống của từng sân
        $courts = VenueCourt::with('courtType')
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $operatingHours = $this->bookingService->resolveOperatingHours($clusterId, $today);
        $openMins = $this->timeToMinutes($operatingHours['open_time'] ?? '06:00');
        $closeMins = $this->timeToMinutes($operatingHours['close_time'] ?? '22:00');

        $bookings = Booking::where('venue_cluster_id', $clusterId)
            ->whereDate('booking_date', $today)
            ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed'])
            ->get(['venue_court_id', 'start_time', 'end_time']);

        $courtLocks = SlotLock::where('venue_cluster_id', $clusterId)
            ->where('booking_date', $today)
            ->where(function ($q) {
                $q->whereIn('lock_type', ['manual', 'emergency'])
                    ->orWhere(function ($autoQuery) {
                        $autoQuery->where('lock_type', 'auto')
                            ->where('expires_at', '>', Carbon::now());
                    });
            })
            ->get(['venue_court_id', 'lock_scope', 'start_time', 'end_time']);

        $courtAvailabilities = [];

        foreach ($courts as $court) {
            $intervals = [];

            // Bookings cho sân này
            foreach ($bookings->where('venue_court_id', $court->id) as $b) {
                $intervals[] = [
                    'start' => $this->timeToMinutes($b->start_time),
                    'end' => $this->timeToMinutes($b->end_time),
                ];
            }

            // Khoá sân/khoá cụm cho sân này
            foreach ($courtLocks as $lock) {
                if ($lock->lock_scope === 'cluster' || $lock->venue_court_id === $court->id) {
                    $intervals[] = [
                        'start' => $this->timeToMinutes($lock->start_time),
                        'end' => $this->timeToMinutes($lock->end_time),
                    ];
                }
            }

            // Sắp xếp và gộp các khoảng bận
            usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);
            $merged = [];
            foreach ($intervals as $interval) {
                // Giới hạn trong khung giờ mở cửa
                $start = max($openMins, $interval['start']);
                $end = min($closeMins, $interval['end']);
                if ($start >= $end) {
                    continue;
                }

                if (empty($merged)) {
                    $merged[] = ['start' => $start, 'end' => $end];
                } else {
                    $last = &$merged[count($merged) - 1];
                    if ($start <= $last['end']) {
                        $last['end'] = max($last['end'], $end);
                    } else {
                        $merged[] = ['start' => $start, 'end' => $end];
                    }
                }
            }

            // Tính các khoảng trống
            $freeSlots = [];
            $current = $openMins;
            foreach ($merged as $interval) {
                if ($interval['start'] > $current) {
                    $freeSlots[] = $this->minutesToTime($current).' - '.$this->minutesToTime($interval['start']);
                }
                $current = max($current, $interval['end']);
            }
            if ($current < $closeMins) {
                $freeSlots[] = $this->minutesToTime($current).' - '.$this->minutesToTime($closeMins);
            }

            $courtAvailabilities[] = [
                'court_id' => $court->id,
                'court_name' => $court->name,
                'court_type' => $court->courtType?->name ?? 'Mặc định',
                'free_slots' => $freeSlots,
                'is_fully_booked' => empty($freeSlots),
            ];
        }

        // 7. Thông báo mới nhất
        $notifications = $request->user()->notifications()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'title' => $notif->title,
                    'body' => $notif->body,
                    'reference_type' => $notif->reference_type,
                    'reference_id' => $notif->reference_id,
                    'data' => $notif->data,
                    'is_read' => $notif->is_read,
                    'created_at' => $notif->created_at,
                ];
            });

        return response()->json([
            'active_courts_count' => $activeCourts,
            'total_courts_count' => $totalCourts,
            'today_bookings_count' => $todayBookingsCount,
            'playing_now_count' => $playingNowCount,
            'upcoming_bookings_count' => $upcomingBookingsCount,
            'court_availabilities' => $courtAvailabilities,
            'notifications' => $notifications,
            'my_shift_today' => $myShiftToday,
        ]);
    }

    private function visibleClusterIds(string $userId)
    {
        $ownedClusterIds = DB::table('venue_clusters')
            ->where('owner_id', $userId)
            ->pluck('id');

        $assignedClusterIds = DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $ownedClusterIds
            ->merge($assignedClusterIds)
            ->unique()
            ->values();
    }

    private function timeToMinutes(string $timeStr): int
    {
        $parts = explode(':', $timeStr);

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    private function minutesToTime(int $minutes): string
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}
