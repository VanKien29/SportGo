<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleLockController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $this->ensureClusterAccess($request, $data['venue_cluster_id']);

        $locks = SlotLock::query()
            ->with('venueCourt.courtType:id,name')
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->where('lock_type', 'manual')
            ->whereNull('booking_id')
            ->when(
                ! empty($data['booking_date']),
                fn ($query) => $query->where('booking_date', $data['booking_date']),
                fn ($query) => $query->where('booking_date', '>=', today()->toDateString())
            )
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (SlotLock $lock): array => $this->payload($lock));

        return response()->json(['data' => $locks]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_court_id' => ['nullable', 'required_without:slots', 'uuid', 'exists:venue_courts,id'],
            'slots' => ['nullable', 'required_without:venue_court_id', 'array', 'min:1', 'max:200'],
            'slots.*.venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'slots.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'slots.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'booking_date' => ['nullable', 'required_without:start_date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_date' => ['nullable', 'required_without:booking_date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:venue_court_id', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['nullable', 'required_with:venue_court_id', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $startDate = $data['start_date'] ?? $data['booking_date'];
        $endDate = $data['end_date'] ?? $startDate;
        $dates = $this->dateRange($startDate, $endDate);

        if ($dates->count() > 31) {
            throw ValidationException::withMessages([
                'end_date' => 'Mỗi lần chỉ nên khóa tối đa 31 ngày để dễ kiểm soát lịch sân.',
            ]);
        }

        $isBatch = ! empty($data['slots']);
        $requestedSlots = collect($data['slots'] ?? [[
            'venue_court_id' => $data['venue_court_id'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]]);

        foreach ($requestedSlots as $index => $slot) {
            if ($this->timeToMinutes($slot['end_time']) <= $this->timeToMinutes($slot['start_time'])) {
                throw ValidationException::withMessages([
                    $isBatch ? "slots.{$index}.end_time" : 'end_time' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
                ]);
            }
            if ($this->timeToMinutes($slot['start_time']) % 30 !== 0) {
                throw ValidationException::withMessages([
                    $isBatch ? "slots.{$index}.start_time" : 'start_time' => 'Giờ bắt đầu phải theo bước 30 phút.',
                ]);
            }
            if ($this->timeToMinutes($slot['end_time']) % 30 !== 0) {
                throw ValidationException::withMessages([
                    $isBatch ? "slots.{$index}.end_time" : 'end_time' => 'Giờ kết thúc phải theo bước 30 phút.',
                ]);
            }
        }

        if ($requestedSlots
            ->map(fn (array $slot): string => implode('|', $slot))
            ->duplicates()
            ->isNotEmpty()) {
            throw ValidationException::withMessages([
                'slots' => 'Danh sách có khung giờ bị trùng.',
            ]);
        }

        if ($dates->count() * $requestedSlots->count() > 500) {
            throw ValidationException::withMessages([
                'slots' => 'Số khoảng khóa quá lớn. Vui lòng chia thành nhiều lần tạo.',
            ]);
        }

        $locks = DB::transaction(function () use ($request, $data, $requestedSlots, $isBatch, $dates): Collection {
            $courts = VenueCourt::query()
                ->with('venueCluster')
                ->whereIn('id', $requestedSlots->pluck('venue_court_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $createdLocks = collect();

            foreach ($dates as $date) {
                foreach ($requestedSlots as $index => $slot) {
                    $court = $courts->get($slot['venue_court_id']);
                    abort_unless($court, 404);

                    $this->ensureClusterAccess($request, $court->venue_cluster_id);

                    if ($court->status !== 'active') {
                        throw ValidationException::withMessages([
                            $isBatch ? "slots.{$index}.venue_court_id" : 'venue_court_id' => "{$court->name} không ở trạng thái hoạt động.",
                        ]);
                    }

                    if ($court->venueCluster->status === 'locked') {
                        throw ValidationException::withMessages([
                            $isBatch ? "slots.{$index}.venue_court_id" : 'venue_court_id' => "Cụm sân của {$court->name} đang bị khóa.",
                        ]);
                    }

                    if (! $this->bookingService->checkAvailability(
                        $court->id,
                        $date,
                        $slot['start_time'],
                        $slot['end_time']
                    )) {
                        throw ValidationException::withMessages([
                            $isBatch ? "slots.{$index}.start_time" : 'start_time' => "{$court->name} ngày {$date} bị trùng booking hoặc khoảng đã khóa.",
                        ]);
                    }

                    $lock = SlotLock::query()->create([
                        'venue_cluster_id' => $court->venue_cluster_id,
                        'venue_court_id' => $court->id,
                        'lock_scope' => 'court',
                        'booking_date' => $date,
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'locked_by' => $request->user()->id,
                        'booking_id' => null,
                        'lock_type' => 'manual',
                        'reason' => $data['reason'],
                        'expires_at' => Carbon::parse($date)->endOfDay(),
                    ])->load('venueCourt.courtType');

                    $this->audit($request, 'schedule_lock.created', $lock, null, $this->payload($lock));
                    $createdLocks->push($lock);
                }
            }

            return $createdLocks;
        });

        $payload = $locks->map(fn (SlotLock $lock): array => $this->payload($lock))->values();

        return response()->json([
            'message' => $payload->count() > 1
                ? "Đã tạo {$payload->count()} khoảng khóa lịch."
                : 'Đã khóa khung giờ.',
            'data' => $isBatch ? $payload : $payload->first(),
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $lock = SlotLock::query()
            ->with('venueCourt.courtType')
            ->findOrFail($id);

        $this->ensureClusterAccess($request, $lock->venue_cluster_id);

        if ($lock->lock_type !== 'manual' || $lock->booking_id !== null) {
            throw ValidationException::withMessages([
                'schedule_lock' => 'Chỉ được hủy khóa lịch thủ công do sân tạo.',
            ]);
        }

        $oldValues = $this->payload($lock);

        DB::transaction(function () use ($request, $lock, $oldValues): void {
            $this->audit($request, 'schedule_lock.deleted', $lock, $oldValues, null);
            $lock->delete();
        });

        return response()->json(['message' => 'Đã mở lại khung giờ.']);
    }

    private function ensureClusterAccess(Request $request, string $clusterId): VenueCluster
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        abort_unless($this->visibleClusterIds($request->user()->id)->contains($cluster->id), 403);

        return $cluster;
    }

    private function visibleClusterIds(string $userId): Collection
    {
        $owned = DB::table('venue_clusters')->where('owner_id', $userId)->pluck('id');
        $assigned = DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $owned->merge($assigned)->unique()->values();
    }

    private function payload(SlotLock $lock): array
    {
        return [
            'id' => $lock->id,
            'venue_cluster_id' => $lock->venue_cluster_id,
            'venue_court_id' => $lock->venue_court_id,
            'booking_date' => $lock->booking_date?->toDateString(),
            'start_time' => $lock->start_time,
            'end_time' => $lock->end_time,
            'reason' => $lock->reason,
            'lock_type' => $lock->lock_type,
            'locked_by' => $lock->locked_by,
            'created_at' => $lock->created_at?->toISOString(),
            'venue_court' => $lock->venueCourt ? [
                'id' => $lock->venueCourt->id,
                'name' => $lock->venueCourt->name,
                'court_type' => $lock->venueCourt->courtType ? [
                    'id' => $lock->venueCourt->courtType->id,
                    'name' => $lock->venueCourt->courtType->name,
                ] : null,
            ] : null,
        ];
    }

    private function audit(Request $request, string $action, SlotLock $lock, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'owner',
            'module' => 'booking',
            'action' => $action,
            'entity_type' => SlotLock::class,
            'entity_id' => $lock->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => 'owner',
            'metadata' => ['venue_cluster_id' => $lock->venue_cluster_id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function dateRange(string $startDate, string $endDate): Collection
    {
        $dates = collect();
        $current = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        while ($current->lte($end)) {
            $dates->push($current->toDateString());
            $current->addDay();
        }

        return $dates;
    }
}
