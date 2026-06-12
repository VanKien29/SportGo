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
            'venue_court_id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->timeToMinutes($data['end_time']) <= $this->timeToMinutes($data['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
            ]);
        }

        $lock = DB::transaction(function () use ($request, $data): SlotLock {
            $court = VenueCourt::query()
                ->with('venueCluster')
                ->whereKey($data['venue_court_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureClusterAccess($request, $court->venue_cluster_id);

            if ($court->status !== 'active') {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Chỉ có thể khóa lịch của sân đang hoạt động.',
                ]);
            }

            if ($court->venueCluster->status === 'locked') {
                throw ValidationException::withMessages([
                    'venue_court_id' => 'Cụm sân đang bị khóa. Vui lòng liên hệ quản trị viên.',
                ]);
            }

            if (! $this->bookingService->checkAvailability(
                $court->id,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            )) {
                throw ValidationException::withMessages([
                    'start_time' => 'Khung giờ này trùng với booking hoặc một khoảng đã khóa.',
                ]);
            }

            $expiresAt = Carbon::parse($data['booking_date'])->endOfDay();
            $lock = SlotLock::query()->create([
                'venue_cluster_id' => $court->venue_cluster_id,
                'venue_court_id' => $court->id,
                'lock_scope' => 'court',
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'locked_by' => $request->user()->id,
                'booking_id' => null,
                'lock_type' => 'manual',
                'reason' => $data['reason'],
                'expires_at' => $expiresAt,
            ]);

            $this->audit($request, 'schedule_lock.created', $lock, null, $this->payload($lock->load('venueCourt.courtType')));

            return $lock;
        });

        return response()->json([
            'message' => 'Đã khóa khung giờ.',
            'data' => $this->payload($lock->load('venueCourt.courtType')),
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
}
