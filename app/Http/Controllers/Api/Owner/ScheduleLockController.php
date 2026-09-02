<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Notification;
use App\Models\SlotLock;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\Bookings\OwnerBookingCancellationService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ScheduleLockController extends Controller
{
    public function __construct(
        private readonly OwnerBookingCancellationService $ownerBookingCancellationService,
        private readonly BookingService $bookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $this->ensureClusterAccess($request, $data['venue_cluster_id']);

        $lockModels = SlotLock::query()
            ->with('venueCourt.courtType:id,name')
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->whereIn('lock_type', ['manual', 'emergency'])
            ->whereNull('booking_id')
            ->when(
                ! empty($data['booking_date']),
                fn ($query) => $query->where('booking_date', $data['booking_date']),
                fn ($query) => ! empty($data['start_date'])
                    ? $query->whereBetween('booking_date', [
                        $data['start_date'],
                        $data['end_date'] ?? $data['start_date'],
                    ])
                    : $query->where('booking_date', '>=', $this->businessNow()->toDateString())
            )
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        $locks = $lockModels->map(fn (SlotLock $lock): array => $this->payload($lock));
        $rangeStart = $data['booking_date'] ?? ($data['start_date'] ?? null);
        $rangeEnd = $data['booking_date'] ?? ($data['end_date'] ?? $rangeStart);

        return response()->json([
            'data' => $locks,
            'meta' => [
                'full_day_locked_court_ids' => $rangeStart && $rangeEnd
                    ? $this->fullDayLockedCourtIdsForRange(
                        (string) $data['venue_cluster_id'],
                        $rangeStart,
                        $rangeEnd,
                        $lockModels,
                    )
                    : [],
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $this->validateLockPayload($request, false);
        [$dates, $requestedSlots, $isBatch] = $this->prepareLockRanges($data);

        $courts = $this->validateRequestedCourts($request, $requestedSlots, $isBatch);

        $affectedItems = collect();
        foreach ($dates as $date) {
            $dateSlots = $this->requestedSlotsForDate($data, $requestedSlots, $courts, $date);

            foreach ($dateSlots as $slot) {
                $items = $this->affectedBookingItemsForRange(
                    $slot['venue_court_id'],
                    $date,
                    $slot['start_time'],
                    $slot['end_time'],
                );

                $affectedItems = $affectedItems->merge(
                    $items->map(fn (BookingItem $item): array => $this->affectedBookingItemPayload(
                        $item,
                        $date,
                        $slot['start_time'],
                        $slot['end_time'],
                        $dateSlots,
                    ))
                );
            }
        }

        $affectedItems = $affectedItems
            ->unique('booking_item_id')
            ->values();

        return response()->json([
            'data' => [
                'affected_count' => $affectedItems->count(),
                'items' => $affectedItems,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateLockPayload($request, true);
        [$dates, $requestedSlots, $isBatch] = $this->prepareLockRanges($data);
        $resolutions = collect($data['resolutions'] ?? [])->keyBy('booking_item_id');

        $locks = DB::transaction(function () use ($request, $data, $requestedSlots, $isBatch, $dates, $resolutions): Collection {
            $courts = $this->validateRequestedCourts($request, $requestedSlots, $isBatch, true);

            $createdLocks = collect();

            foreach ($dates as $date) {
                $dateSlots = $this->requestedSlotsForDate($data, $requestedSlots, $courts, $date);

                foreach ($dateSlots as $index => $slot) {
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

                    $overlappingLocks = $this->overlappingManualScheduleLocks(
                        $court->id,
                        $date,
                        $slot['start_time'],
                        $slot['end_time'],
                    );

                    $mergedStartTime = $this->minTime($slot['start_time'], $overlappingLocks->pluck('start_time')->all());
                    $mergedEndTime = $this->maxTime($slot['end_time'], $overlappingLocks->pluck('end_time')->all());
                    $lockType = ($data['lock_type'] ?? 'manual') === 'emergency' || $overlappingLocks->contains('lock_type', 'emergency')
                        ? 'emergency'
                        : 'manual';

                    $overlappingLocks->each(function (SlotLock $existingLock) use ($request): void {
                        $oldPayload = $this->payload($existingLock);
                        $this->audit($request, 'schedule_lock.merged', $existingLock, $oldPayload, null);
                        $existingLock->delete();
                    });

                    $lock = SlotLock::query()->create([
                        'venue_cluster_id' => $court->venue_cluster_id,
                        'venue_court_id' => $court->id,
                        'lock_scope' => 'court',
                        'booking_date' => $date,
                        'start_time' => $mergedStartTime,
                        'end_time' => $mergedEndTime,
                        'locked_by' => $request->user()->id,
                        'booking_id' => null,
                        'lock_type' => $lockType,
                        'reason' => $data['reason'],
                        'expires_at' => Carbon::parse($date, $this->businessTimezone())->endOfDay(),
                    ])->load('venueCourt.courtType');

                    $this->audit($request, 'schedule_lock.created', $lock, null, $this->payload($lock));
                    $this->resolveOverlappingBookingItems($request, $lock, $resolutions, $dateSlots);
                    $createdLocks->push($lock);
                }
            }

            return $createdLocks;
        });

        $payload = $locks->map(fn (SlotLock $lock): array => $this->payload($lock))->values();

        return response()->json([
            'message' => ! empty($data['full_day'])
                ? 'Đã khóa toàn bộ giờ hoạt động của các sân đã chọn.'
                : ($payload->count() > 1
                    ? "Đã tạo {$payload->count()} khoảng khóa lịch."
                    : 'Đã khóa khung giờ.'),
            'data' => $isBatch ? $payload : $payload->first(),
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $lock = SlotLock::query()
            ->with('venueCourt.courtType')
            ->findOrFail($id);

        $this->ensureClusterAccess($request, $lock->venue_cluster_id);

        if (! in_array($lock->lock_type, ['manual', 'emergency'], true) || $lock->booking_id !== null) {
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

    public function unlockRanges(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ranges' => ['required', 'array', 'min:1', 'max:200'],
            'ranges.*.schedule_lock_id' => ['required', 'integer', 'exists:slot_locks,id'],
            'ranges.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'ranges.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
        ]);

        $requestedByLock = collect($data['ranges'])
            ->groupBy(fn (array $range): string => (string) $range['schedule_lock_id']);

        $result = DB::transaction(function () use ($request, $requestedByLock): array {
            $locks = SlotLock::query()
                ->with('venueCourt.courtType')
                ->whereIn('id', $requestedByLock->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (SlotLock $lock): string => (string) $lock->id);

            if ($locks->count() !== $requestedByLock->count()) {
                throw ValidationException::withMessages([
                    'ranges' => 'Một khoảng khóa đã thay đổi. Vui lòng tải lại lịch và chọn lại.',
                ]);
            }

            $remainingLocks = collect();
            $unlockedRanges = collect();

            foreach ($requestedByLock as $lockId => $requestedRanges) {
                /** @var SlotLock $lock */
                $lock = $locks->get((string) $lockId);
                $this->ensureClusterAccess($request, $lock->venue_cluster_id);

                if (! in_array($lock->lock_type, ['manual', 'emergency'], true) || $lock->booking_id !== null) {
                    throw ValidationException::withMessages([
                        'ranges' => 'Chỉ được mở các khoảng khóa do sân tạo.',
                    ]);
                }

                $normalizedRanges = $this->normalizeUnlockRanges($lock, $requestedRanges);
                $remainingRanges = $this->remainingLockRanges($lock, $normalizedRanges);
                $oldValues = $this->payload($lock);

                $replacements = collect($remainingRanges)->map(function (array $range) use ($lock): SlotLock {
                    $replacement = $lock->replicate();
                    $replacement->start_time = $this->minutesToTime($range['start']);
                    $replacement->end_time = $this->minutesToTime($range['end']);

                    return $replacement;
                });

                $lock->delete();

                $replacements->each(function (SlotLock $replacement) use ($remainingLocks): void {
                    $replacement->save();
                    $replacement->load('venueCourt.courtType');
                    $remainingLocks->push($replacement);
                });

                $unlockedPayload = collect($normalizedRanges)
                    ->map(fn (array $range): array => [
                        'schedule_lock_id' => $lock->id,
                        'start_time' => $this->minutesToTime($range['start']),
                        'end_time' => $this->minutesToTime($range['end']),
                    ])
                    ->values();

                $unlockedRanges = $unlockedRanges->merge($unlockedPayload);

                $this->audit(
                    $request,
                    $replacements->isEmpty()
                        ? 'schedule_lock.deleted'
                        : 'schedule_lock.partially_unlocked',
                    $lock,
                    $oldValues,
                    [
                        'unlocked_ranges' => $unlockedPayload->all(),
                        'remaining_locks' => $replacements
                            ->map(fn (SlotLock $replacement): array => $this->payload($replacement))
                            ->values()
                            ->all(),
                    ],
                );
            }

            return [
                'unlocked_ranges' => $unlockedRanges->values()->all(),
                'remaining_locks' => $remainingLocks
                    ->map(fn (SlotLock $lock): array => $this->payload($lock))
                    ->values()
                    ->all(),
            ];
        });

        return response()->json([
            'message' => 'Đã mở các khung giờ được chọn.',
            'data' => $result,
        ]);
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
        $startsAt = $lock->booking_date
            ? $this->businessDateTime($lock->booking_date->toDateString(), (string) $lock->start_time)
            : null;
        $endsAt = $lock->booking_date
            ? $this->businessDateTime($lock->booking_date->toDateString(), (string) $lock->end_time)
            : null;
        $status = $endsAt?->isPast()
            ? 'ended'
            : ($startsAt?->isFuture() ? 'upcoming' : 'active');

        return [
            'id' => $lock->id,
            'venue_cluster_id' => $lock->venue_cluster_id,
            'venue_court_id' => $lock->venue_court_id,
            'booking_date' => $lock->booking_date?->toDateString(),
            'start_time' => $lock->start_time,
            'end_time' => $lock->end_time,
            'reason' => $lock->reason,
            'lock_type' => $lock->lock_type,
            'status' => $status,
            'status_label' => match ($status) {
                'active' => 'Đang khóa',
                'upcoming' => 'Sắp áp dụng',
                default => 'Đã kết thúc',
            },
            'lock_type_label' => $lock->lock_type === 'emergency'
                ? 'Khóa đột xuất'
                : 'Khóa thủ công',
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

    private function overlappingManualScheduleLocks(string $venueCourtId, string $date, string $startTime, string $endTime): Collection
    {
        return SlotLock::query()
            ->where('booking_date', $date)
            ->whereIn('lock_type', ['manual', 'emergency'])
            ->whereNull('booking_id')
            ->where('lock_scope', 'court')
            ->where('venue_court_id', $venueCourtId)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->get();
    }

    private function minTime(string $baseTime, array $candidateTimes): string
    {
        return collect($candidateTimes)
            ->push($baseTime)
            ->filter()
            ->sortBy(fn (string $time): int => $this->timeToMinutes($time))
            ->first();
    }

    private function maxTime(string $baseTime, array $candidateTimes): string
    {
        return collect($candidateTimes)
            ->push($baseTime)
            ->filter()
            ->sortByDesc(fn (string $time): int => $this->timeToMinutes($time))
            ->first();
    }

    private function validateLockPayload(Request $request, bool $requireReason): array
    {
        $data = $request->validate([
            'full_day' => ['nullable', 'boolean'],
            'venue_court_ids' => ['nullable', 'required_if:full_day,true', 'array', 'min:1', 'max:50'],
            'venue_court_ids.*' => ['required', 'integer', 'distinct', 'exists:venue_courts,id'],
            'venue_court_id' => ['nullable', 'required_without_all:slots,venue_court_ids', 'integer', 'exists:venue_courts,id'],
            'slots' => ['nullable', 'required_without_all:venue_court_id,venue_court_ids', 'array', 'min:1', 'max:200'],
            'slots.*.venue_court_id' => ['required', 'integer', 'exists:venue_courts,id'],
            'slots.*.start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'slots.*.end_time' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'booking_date' => ['nullable', 'required_without:start_date', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday()],
            'start_date' => ['nullable', 'required_without:booking_date', 'date_format:Y-m-d', 'after_or_equal:'.$this->businessToday()],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:venue_court_id', 'regex:/^([01]\d|2[0-3]):[0-5]\d:00$/'],
            'end_time' => ['nullable', 'required_with:venue_court_id', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00):00$/'],
            'reason' => [$requireReason ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'lock_type' => ['nullable', 'in:manual,emergency'],
            'resolutions' => ['nullable', 'array'],
            'resolutions.*.booking_item_id' => ['required_with:resolutions', 'integer', 'exists:booking_items,id'],
            'resolutions.*.action' => ['required_with:resolutions', 'in:switch,cancel,cash_refund'],
            'resolutions.*.scope' => ['nullable', 'in:affected,booking_item'],
            'resolutions.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
        ]);

        if (! empty($data['venue_court_ids']) && empty($data['full_day'])) {
            throw ValidationException::withMessages([
                'full_day' => 'Danh sách sân cả ngày chỉ được dùng trong chế độ khóa cả ngày.',
            ]);
        }

        return $data;
    }

    private function prepareLockRanges(array $data): array
    {
        $startDate = $data['start_date'] ?? $data['booking_date'];
        $endDate = $data['end_date'] ?? $startDate;
        $dates = $this->dateRange($startDate, $endDate);

        if ($dates->count() > 31) {
            throw ValidationException::withMessages([
                'end_date' => 'Mỗi lần chỉ nên khóa tối đa 31 ngày để dễ kiểm soát lịch sân.',
            ]);
        }

        $isFullDay = (bool) ($data['full_day'] ?? false);
        $isBatch = $isFullDay || ! empty($data['slots']);
        $requestedSlots = $isFullDay
            ? collect($data['venue_court_ids'])->map(fn ($courtId): array => [
                'venue_court_id' => (int) $courtId,
            ])
            : collect($data['slots'] ?? [[
                'venue_court_id' => $data['venue_court_id'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ]]);

        foreach ($isFullDay ? collect() : $requestedSlots as $index => $slot) {
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

        if ($requestedSlots->map(fn (array $slot): string => implode('|', $slot))->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                $isFullDay ? 'venue_court_ids' : 'slots' => $isFullDay
                    ? 'Danh sách sân áp dụng bị trùng.'
                    : 'Danh sách có khung giờ bị trùng.',
            ]);
        }

        if ($dates->count() * $requestedSlots->count() > 500) {
            throw ValidationException::withMessages([
                'slots' => 'Số khoảng khóa quá lớn. Vui lòng chia thành nhiều lần tạo.',
            ]);
        }

        return [$dates, $requestedSlots, $isBatch];
    }

    private function requestedSlotsForDate(array $data, Collection $requestedSlots, Collection $courts, string $date): Collection
    {
        if (empty($data['full_day'])) {
            return $requestedSlots;
        }

        $hoursByCluster = [];

        return $requestedSlots->map(function (array $slot) use ($courts, $date, &$hoursByCluster): array {
            $court = $courts->get($slot['venue_court_id']);
            abort_unless($court, 404);

            $clusterId = (string) $court->venue_cluster_id;
            $hours = $hoursByCluster[$clusterId]
                ??= $this->bookingService->resolveOperatingHours($clusterId, $date);

            if (! ($hours['is_open'] ?? false)) {
                throw ValidationException::withMessages([
                    'start_date' => "Cụm sân không có giờ hoạt động trong ngày {$date}.",
                ]);
            }

            return [
                'venue_court_id' => $court->id,
                'start_time' => $hours['open_time'],
                'end_time' => $hours['close_time'],
            ];
        })->values();
    }

    private function validateRequestedCourts(Request $request, Collection $requestedSlots, bool $isBatch, bool $lockForUpdate = false): Collection
    {
        $query = VenueCourt::query()
            ->with('venueCluster')
            ->whereIn('id', $requestedSlots->pluck('venue_court_id')->unique())
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $courts = $query->get()->keyBy('id');

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
        }

        return $courts;
    }

    private function affectedBookingItemsForRange(string $venueCourtId, string $date, string $startTime, string $endTime): Collection
    {
        return BookingItem::query()
            ->with(['booking.customer', 'booking.payments', 'venueCourt.courtType'])
            ->where('venue_court_id', $venueCourtId)
            ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['active', 'moved']))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->whereHas('booking', function ($bookingQuery) use ($date): void {
                $bookingQuery
                    ->whereDate('booking_date', $date)
                    ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in', 'completed']);
            })
            ->get();
    }

    private function resolveOverlappingBookingItems(Request $request, SlotLock $lock, Collection $resolutions, Collection $requestedSlots): void
    {
        $items = $this->affectedBookingItemsForRange(
            $lock->venue_court_id,
            $lock->booking_date->toDateString(),
            $lock->start_time,
            $lock->end_time,
        );

        if ($items->isEmpty()) {
            return;
        }

        if ($lock->lock_type !== 'emergency') {
            throw ValidationException::withMessages([
                'lock_type' => 'Khung giờ có booking bị ảnh hưởng. Vui lòng dùng luồng khóa đột xuất và chọn phương án xử lý.',
            ]);
        }

        $missingResolutionIds = $items
            ->pluck('id')
            ->reject(fn ($itemId): bool => $resolutions->has($itemId))
            ->values();

        if ($missingResolutionIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'resolutions' => 'Vui lòng chọn phương án xử lý cho tất cả booking bị ảnh hưởng.',
            ]);
        }

        $walletCancellations = collect();
        $cashCancellations = collect();

        foreach ($items as $item) {
            $resolution = $resolutions->get($item->id);
            $scope = $this->resolutionScope($resolution);

            if (($resolution['action'] ?? null) === 'switch') {
                $this->switchAffectedBookingItem(
                    $request,
                    $item,
                    $resolution['venue_court_id'] ?? null,
                    $lock,
                    $scope,
                    $requestedSlots,
                );

                continue;
            }

            if (($resolution['action'] ?? null) === 'cash_refund') {
                if (! $item->booking?->payments?->where('status', 'paid')->count()) {
                    throw ValidationException::withMessages([
                        'resolutions' => 'Chỉ được ghi nhận hoàn tiền mặt cho booking đã thanh toán.',
                    ]);
                }
                $cashCancellations->push($this->isolateBookingItemForEmergencyScope($request, $item, $lock, $scope));

                continue;
            }

            $walletCancellations->push($this->isolateBookingItemForEmergencyScope($request, $item, $lock, $scope));
        }

        $this->cancelAffectedItems($request, $lock, $walletCancellations, false);
        $this->cancelAffectedItems($request, $lock, $cashCancellations, true);
    }

    private function cancelAffectedItems(Request $request, SlotLock $lock, Collection $items, bool $completeAsCashRefund): void
    {
        $items
            ->groupBy(fn (BookingItem $item): string => $item->booking_id.'|'.($this->isPlayingItem($item, $lock) ? 'playing' : 'scheduled'))
            ->each(function (Collection $bookingItems, string $groupKey) use ($request, $lock, $completeAsCashRefund): void {
                [$bookingId, $timing] = explode('|', $groupKey, 2);
                $booking = Booking::query()
                    ->with(['items', 'payments', 'customer'])
                    ->find($bookingId);

                if (! $booking) {
                    return;
                }

                $refundRatio = $this->refundRatioForAffectedItems($booking, $bookingItems, $lock);
                $isPlaying = $timing === 'playing';

                if ($isPlaying) {
                    $bookingItems->each(function (BookingItem $item) use ($lock, $completeAsCashRefund): void {
                        $metrics = $this->interruptionMetrics($item, $lock);
                        $item->forceFill([
                            'interrupted_at' => $metrics['interrupted_at'],
                            'played_minutes' => $metrics['played_minutes'],
                            'remaining_minutes' => $metrics['remaining_minutes'],
                            'incident_refund_ratio' => $metrics['remaining_ratio'],
                            'incident_resolution' => $completeAsCashRefund ? 'cash_refund' : 'wallet_refund',
                            'incident_original_court_id' => $item->venue_court_id,
                        ])->save();
                    });
                }

                $this->ownerBookingCancellationService->cancelItemsForMaintenance(
                    $booking,
                    $bookingItems->pluck('id')->all(),
                    $request->user(),
                    $lock->reason ?: 'Sân được khóa để bảo trì.',
                    $lock->id,
                    $refundRatio,
                    $isPlaying ? 'maintenance_item_cancelled_mid_play' : 'maintenance_item_cancelled',
                    $completeAsCashRefund,
                    $isPlaying ? 'interrupted_by_emergency' : null,
                );

                $this->notifyBookingCustomer(
                    $booking,
                    'Lịch sân bị hủy do khóa sân',
                    $completeAsCashRefund
                        ? 'Một phần lịch đặt của bạn bị hủy do sân cần khóa/bảo trì. Chủ sân đã ghi nhận hoàn tiền mặt tại sân.'
                        : ($refundRatio > 0
                        ? 'Một phần lịch đặt của bạn bị hủy do sân cần khóa/bảo trì. Nếu đã thanh toán, hệ thống đã tạo yêu cầu hoàn tiền vào ví SportGo.'
                        : 'Một phần lịch đặt của bạn bị hủy do sân cần khóa/bảo trì.'),
                    [
                        'schedule_lock_id' => $lock->id,
                        'booking_item_ids' => $bookingItems->pluck('id')->values()->all(),
                        'refund_ratio' => $refundRatio,
                        'refund_destination' => $completeAsCashRefund ? 'cash' : 'user_wallet',
                        'interrupted_while_playing' => $isPlaying,
                    ],
                );
            });
    }

    private function switchAffectedBookingItem(
        Request $request,
        BookingItem $item,
        ?string $newCourtId,
        SlotLock $lock,
        string $scope,
        Collection $requestedSlots,
    ): void {
        if (! $newCourtId) {
            throw ValidationException::withMessages([
                'resolutions' => 'Vui lòng chọn sân thay thế cho booking bị ảnh hưởng.',
            ]);
        }

        $item = $this->isolateBookingItemForEmergencyScope($request, $item, $lock, $scope);
        $item->loadMissing(['booking.customer', 'venueCourt.courtType']);
        $booking = $item->booking;
        $oldCourt = $item->venueCourt;

        $newCourt = VenueCourt::query()
            ->with('courtType')
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('court_type_id', $oldCourt?->court_type_id)
            ->where('status', 'active')
            ->whereKeyNot($oldCourt?->id)
            ->findOrFail($newCourtId);

        $isPlaying = $this->isPlayingItem($item, $lock);
        $metrics = $this->interruptionMetrics($item, $lock);
        $availabilityStart = $isPlaying ? $metrics['resume_time'] : $item->start_time;

        if ($this->requestedLocksOverlapCourt($requestedSlots, $newCourt->id, $availabilityStart, $item->end_time)) {
            throw ValidationException::withMessages([
                'resolutions' => "{$newCourt->name} cũng nằm trong phạm vi sắp khóa từ {$this->time($availabilityStart)} - {$this->time($item->end_time)}.",
            ]);
        }

        if (! $this->bookingService->checkAvailability(
            $newCourt->id,
            $booking->booking_date->toDateString(),
            $availabilityStart,
            $item->end_time,
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'resolutions' => "{$newCourt->name} không còn trống trong khung giờ {$this->time($availabilityStart)} - {$this->time($item->end_time)}.",
            ]);
        }

        $reason = "Đổi sân do khóa/bảo trì: {$lock->reason}";

        if ($isPlaying && $metrics['remaining_minutes'] > 0) {
            $originalEnd = $item->end_time;
            $originalSubtotal = (float) $item->subtotal;
            $remainingSubtotal = round($originalSubtotal * $metrics['remaining_ratio'], 2);
            $playedSubtotal = round($originalSubtotal - $remainingSubtotal, 2);
            $nextSortOrder = ((int) BookingItem::query()->where('booking_id', $booking->id)->max('sort_order')) + 1;

            $item->forceFill([
                'end_time' => $metrics['resume_time'],
                'duration_minutes' => $metrics['played_minutes'],
                'subtotal' => $playedSubtotal,
                'status' => 'interrupted_by_emergency',
                'status_reason' => $reason,
                'maintenance_lock_id' => $lock->id,
                'interrupted_at' => $metrics['interrupted_at'],
                'played_minutes' => $metrics['played_minutes'],
                'remaining_minutes' => $metrics['remaining_minutes'],
                'incident_refund_ratio' => 0,
                'incident_resolution' => 'switched_court',
                'incident_original_court_id' => $oldCourt?->id,
            ])->save();

            $movedItem = BookingItem::query()->create([
                'booking_id' => $booking->id,
                'venue_court_id' => $newCourt->id,
                'requested_venue_court_id' => $oldCourt?->id,
                'start_time' => $metrics['resume_time'],
                'end_time' => $originalEnd,
                'duration_minutes' => $metrics['remaining_minutes'],
                'unit_price' => $item->unit_price,
                'subtotal' => $remainingSubtotal,
                'status' => 'moved',
                'status_reason' => $reason,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $reason,
                'interrupted_at' => $metrics['interrupted_at'],
                'played_minutes' => 0,
                'remaining_minutes' => $metrics['remaining_minutes'],
                'incident_refund_ratio' => 0,
                'incident_resolution' => 'resumed_on_alternative_court',
                'incident_original_court_id' => $oldCourt?->id,
                'sort_order' => $nextSortOrder,
            ]);

            SlotLock::query()
                ->where('booking_item_id', $item->id)
                ->update(['end_time' => $metrics['resume_time']]);

            $item = $movedItem;
        } else {
            $item->forceFill([
                'venue_court_id' => $newCourt->id,
                'status' => 'moved',
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $reason,
            ])->save();
        }

        SlotLock::query()
            ->where('booking_item_id', $item->id)
            ->update([
                'venue_court_id' => $newCourt->id,
                'reason' => $reason,
            ]);

        if ($scope === 'booking_item' && $booking->venue_court_id === $oldCourt?->id) {
            $booking->forceFill([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $reason,
            ])->save();
        }

        $this->notifyBookingCustomer(
            $booking,
            'Lịch sân được đổi sang sân khác',
            "Khung {$this->time($item->start_time)} - {$this->time($item->end_time)} đã được đổi sang {$newCourt->name} do sân cũ cần khóa/bảo trì.",
            [
                'schedule_lock_id' => $lock->id,
                'booking_item_id' => $item->id,
                'from_venue_court_id' => $oldCourt?->id,
                'to_venue_court_id' => $newCourt->id,
            ],
        );
    }

    private function refundRatioForAffectedItems(Booking $booking, Collection $items, SlotLock $lock): float
    {
        $bookingSubtotal = max((float) $booking->items->sum(fn (BookingItem $item): float => (float) $item->subtotal), 0.01);
        $refundableSubtotal = $items->sum(function (BookingItem $item) use ($lock): float {
            return (float) $item->subtotal * $this->remainingRatioForItem($item, $lock);
        });

        return min(1, max(0, round($refundableSubtotal / $bookingSubtotal, 6)));
    }

    private function resolutionScope(?array $resolution): string
    {
        return ($resolution['scope'] ?? 'affected') === 'booking_item'
            ? 'booking_item'
            : 'affected';
    }

    private function isolateBookingItemForEmergencyScope(Request $request, BookingItem $item, SlotLock $lock, string $scope): BookingItem
    {
        if ($scope === 'booking_item') {
            return $item;
        }

        $range = $this->affectedRangeForItem($item, $lock);
        if (! $range || ($range['start_time'] === $item->start_time && $range['end_time'] === $item->end_time)) {
            return $item;
        }

        return $this->splitBookingItemAroundAffectedRange($request, $item, $lock, $range['start_time'], $range['end_time']);
    }

    private function splitBookingItemAroundAffectedRange(Request $request, BookingItem $item, SlotLock $lock, string $affectedStart, string $affectedEnd): BookingItem
    {
        $item = BookingItem::query()
            ->whereKey($item->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! in_array($item->status ?: 'active', ['active', 'moved'], true)) {
            return $item;
        }

        if ($affectedStart <= $item->start_time && $affectedEnd >= $item->end_time) {
            return $item;
        }

        $originalStart = $item->start_time;
        $originalEnd = $item->end_time;
        $originalDuration = max($this->timeToMinutes($originalEnd) - $this->timeToMinutes($originalStart), 1);
        $originalSubtotal = (float) $item->subtotal;
        $beforeDuration = max($this->timeToMinutes($affectedStart) - $this->timeToMinutes($originalStart), 0);
        $affectedDuration = max($this->timeToMinutes($affectedEnd) - $this->timeToMinutes($affectedStart), 0);
        $afterDuration = max($this->timeToMinutes($originalEnd) - $this->timeToMinutes($affectedEnd), 0);

        if ($affectedDuration <= 0) {
            return $item;
        }

        $beforeSubtotal = $beforeDuration > 0 ? round($originalSubtotal * $beforeDuration / $originalDuration, 2) : 0.0;
        $affectedSubtotal = round($originalSubtotal * $affectedDuration / $originalDuration, 2);
        $afterSubtotal = $afterDuration > 0 ? round($originalSubtotal - $beforeSubtotal - $affectedSubtotal, 2) : 0.0;
        $nextSortOrder = ((int) BookingItem::query()->where('booking_id', $item->booking_id)->max('sort_order')) + 1;

        if ($beforeDuration > 0) {
            $this->createSplitBookingItem($item, $originalStart, $affectedStart, $beforeDuration, $beforeSubtotal, $nextSortOrder++);
        }

        $item->forceFill([
            'start_time' => $affectedStart,
            'end_time' => $affectedEnd,
            'duration_minutes' => $affectedDuration,
            'subtotal' => $affectedSubtotal,
            'status_reason' => $item->status_reason ?: "Tách phần bị ảnh hưởng bởi khóa đột xuất #{$lock->id}.",
        ])->save();

        SlotLock::query()
            ->where('booking_item_id', $item->id)
            ->where('lock_type', 'auto')
            ->update([
                'start_time' => $affectedStart,
                'end_time' => $affectedEnd,
            ]);

        if ($afterDuration > 0) {
            $this->createSplitBookingItem($item, $affectedEnd, $originalEnd, $afterDuration, $afterSubtotal, $nextSortOrder++);
        }

        $this->auditBookingItem($request, 'booking_item.split_for_emergency_lock', $item, [
            'start_time' => $originalStart,
            'end_time' => $originalEnd,
            'duration_minutes' => $originalDuration,
            'subtotal' => $originalSubtotal,
        ], [
            'affected_start_time' => $affectedStart,
            'affected_end_time' => $affectedEnd,
            'schedule_lock_id' => $lock->id,
        ]);

        return $item->fresh(['booking.customer', 'booking.payments', 'venueCourt.courtType']);
    }

    private function auditBookingItem(Request $request, string $action, BookingItem $item, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'owner',
            'module' => 'booking',
            'action' => $action,
            'entity_type' => BookingItem::class,
            'entity_id' => $item->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => 'owner',
            'metadata' => [
                'booking_id' => $item->booking_id,
                'venue_court_id' => $item->venue_court_id,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function createSplitBookingItem(BookingItem $source, string $startTime, string $endTime, int $durationMinutes, float $subtotal, int $sortOrder): BookingItem
    {
        return BookingItem::query()->create([
            'booking_id' => $source->booking_id,
            'venue_court_id' => $source->venue_court_id,
            'requested_venue_court_id' => $source->requested_venue_court_id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'unit_price' => $source->unit_price,
            'subtotal' => max($subtotal, 0),
            'status' => $source->status ?: 'active',
            'status_reason' => $source->status_reason,
            'court_changed_by' => $source->court_changed_by,
            'court_changed_at' => $source->court_changed_at,
            'court_changed_reason' => $source->court_changed_reason,
            'sort_order' => $sortOrder,
        ]);
    }

    private function affectedRangeForItem(BookingItem $item, SlotLock $lock): ?array
    {
        return $this->affectedRangeForTimes(
            $item,
            $lock->booking_date->toDateString(),
            $lock->start_time,
            $lock->end_time,
        );
    }

    private function affectedRangeForTimes(BookingItem $item, string $date, string $lockStart, string $lockEnd): ?array
    {
        $start = $this->maxClock($item->start_time, $lockStart);
        $end = $this->minClock($item->end_time, $lockEnd);

        if ($this->isPlayingForDate($item, $date)) {
            $start = $this->maxClock($start, $this->interruptionMetricsForDate($item, $date)['resume_time']);
        }

        if ($this->timeToMinutes($end) <= $this->timeToMinutes($start)) {
            return null;
        }

        $duration = $this->timeToMinutes($end) - $this->timeToMinutes($start);
        $originalDuration = max($this->timeToMinutes($item->end_time) - $this->timeToMinutes($item->start_time), 1);
        $subtotal = round((float) $item->subtotal * $duration / $originalDuration, 2);

        return [
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'subtotal' => $subtotal,
        ];
    }

    private function minClock(string $first, string $second): string
    {
        return $this->timeToMinutes($first) <= $this->timeToMinutes($second) ? $first : $second;
    }

    private function maxClock(string $first, string $second): string
    {
        return $this->timeToMinutes($first) >= $this->timeToMinutes($second) ? $first : $second;
    }

    private function remainingRatioForItem(BookingItem $item, SlotLock $lock): float
    {
        return $this->interruptionMetrics($item, $lock)['remaining_ratio'];
    }

    private function hasPlayingItem(Collection $items, SlotLock $lock): bool
    {
        $date = $lock->booking_date->toDateString();
        $now = $this->businessNow();

        return $items->contains(function (BookingItem $item) use ($date, $now): bool {
            $start = $this->businessDateTime($date, $item->start_time);
            $end = $this->businessDateTime($date, $item->end_time);

            return $now->betweenIncluded($start, $end);
        });
    }

    private function isPlayingItem(BookingItem $item, SlotLock $lock): bool
    {
        $date = $lock->booking_date->toDateString();
        $now = $this->businessNow();
        $start = $this->businessDateTime($date, $item->start_time);
        $end = $this->businessDateTime($date, $item->end_time);

        return $now->betweenIncluded($start, $end);
    }

    private function interruptionMetrics(BookingItem $item, SlotLock $lock): array
    {
        return $this->interruptionMetricsForDate($item, $lock->booking_date->toDateString());
    }

    private function interruptionMetricsForDate(BookingItem $item, string $date): array
    {
        $now = $this->businessNow();
        $start = $this->businessDateTime($date, $item->start_time);
        $end = $this->businessDateTime($date, $item->end_time);
        $durationMinutes = max($start->diffInMinutes($end), 1);

        if ($now->lt($start)) {
            $remainingMinutes = $durationMinutes;
        } elseif ($now->gte($end)) {
            $remainingMinutes = 0;
        } else {
            $rawRemaining = max($now->diffInMinutes($end), 0);
            $remainingMinutes = min($durationMinutes, (int) ceil($rawRemaining / 30) * 30);
        }

        $playedMinutes = max($durationMinutes - $remainingMinutes, 0);
        $resumeAt = $start->copy()->addMinutes($playedMinutes);

        return [
            'interrupted_at' => $now,
            'played_minutes' => $playedMinutes,
            'remaining_minutes' => $remainingMinutes,
            'remaining_ratio' => min(1, max(0, round($remainingMinutes / $durationMinutes, 6))),
            'resume_time' => $resumeAt->format('H:i:s'),
        ];
    }

    private function affectedBookingItemPayload(
        BookingItem $item,
        ?string $lockDate = null,
        ?string $lockStart = null,
        ?string $lockEnd = null,
        ?Collection $requestedSlots = null,
    ): array {
        $booking = $item->booking;
        $date = $lockDate ?: $booking?->booking_date?->toDateString();
        $affectedRange = ($date && $lockStart && $lockEnd)
            ? $this->affectedRangeForTimes($item, $date, $lockStart, $lockEnd)
            : null;

        return [
            'booking_item_id' => $item->id,
            'booking_id' => $booking?->id,
            'booking_code' => $booking?->booking_code,
            'booking_date' => $booking?->booking_date?->toDateString(),
            'booking_status' => $booking?->status,
            'payment_status' => $booking?->payments?->where('status', 'paid')->isNotEmpty() ? 'paid' : 'unpaid',
            'customer' => [
                'id' => $booking?->customer?->id,
                'name' => $booking?->customer?->name ?? $booking?->walk_in_name ?? 'Khách hàng',
                'phone' => $booking?->customer?->phone ?? $booking?->walk_in_phone,
            ],
            'court' => [
                'id' => $item->venueCourt?->id,
                'name' => $item->venueCourt?->name,
                'court_type' => $item->venueCourt?->courtType ? [
                    'id' => $item->venueCourt->courtType->id,
                    'name' => $item->venueCourt->courtType->name,
                ] : null,
            ],
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'subtotal' => (float) $item->subtotal,
            'affected_range' => $affectedRange,
            'full_item_range' => [
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'duration_minutes' => (int) $item->duration_minutes,
                'subtotal' => (float) $item->subtotal,
            ],
            'is_playing' => $this->isPlayingForDate($item, $booking?->booking_date?->toDateString()),
            'incident' => $this->incidentPreviewPayload($item, $booking),
            'alternatives' => $this->availableAlternativeCourtsForItem(
                $item,
                $affectedRange['start_time'] ?? null,
                $affectedRange['end_time'] ?? null,
                $requestedSlots,
            )
                ->map(fn (VenueCourt $court): array => [
                    'id' => $court->id,
                    'name' => $court->name,
                    'court_type' => $court->courtType ? [
                        'id' => $court->courtType->id,
                        'name' => $court->courtType->name,
                    ] : null,
                ])
                ->values()
                ->all(),
            'full_item_alternatives' => $this->availableAlternativeCourtsForItem(
                $item,
                null,
                null,
                $requestedSlots,
            )
                ->map(fn (VenueCourt $court): array => [
                    'id' => $court->id,
                    'name' => $court->name,
                    'court_type' => $court->courtType ? [
                        'id' => $court->courtType->id,
                        'name' => $court->courtType->name,
                    ] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function availableAlternativeCourtsForItem(
        BookingItem $item,
        ?string $rangeStart = null,
        ?string $rangeEnd = null,
        ?Collection $requestedSlots = null,
    ): Collection {
        $item->loadMissing(['booking', 'venueCourt.courtType']);
        $booking = $item->booking;
        $court = $item->venueCourt;

        if (! $booking || ! $court) {
            return collect();
        }

        $metrics = $this->interruptionMetricsForDate($item, $booking->booking_date->toDateString());
        $availabilityStart = $this->isPlayingForDate($item, $booking->booking_date->toDateString())
            ? $metrics['resume_time']
            : $item->start_time;
        $availabilityStart = $rangeStart ?: $availabilityStart;
        $availabilityEnd = $rangeEnd ?: $item->end_time;

        return VenueCourt::query()
            ->with('courtType')
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('court_type_id', $court->court_type_id)
            ->where('status', 'active')
            ->whereKeyNot($court->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (VenueCourt $candidate): bool => $this->requestedLocksOverlapCourt(
                $requestedSlots ?? collect(),
                $candidate->id,
                $availabilityStart,
                $availabilityEnd,
            ))
            ->filter(fn (VenueCourt $candidate): bool => $this->bookingService->checkAvailability(
                $candidate->id,
                $booking->booking_date->toDateString(),
                $availabilityStart,
                $availabilityEnd,
                $booking->id,
            ))
            ->values();
    }

    private function requestedLocksOverlapCourt(
        Collection $requestedSlots,
        string $venueCourtId,
        string $startTime,
        string $endTime,
    ): bool {
        return $requestedSlots->contains(fn (array $slot): bool => (string) $slot['venue_court_id'] === (string) $venueCourtId
            && $slot['start_time'] < $endTime
            && $slot['end_time'] > $startTime
        );
    }

    private function isPlayingForDate(BookingItem $item, ?string $date): bool
    {
        if (! $date) {
            return false;
        }

        $now = $this->businessNow();
        $start = $this->businessDateTime($date, $item->start_time);
        $end = $this->businessDateTime($date, $item->end_time);

        return $now->betweenIncluded($start, $end);
    }

    private function incidentPreviewPayload(BookingItem $item, ?Booking $booking): array
    {
        if (! $booking?->booking_date) {
            return [
                'played_minutes' => 0,
                'remaining_minutes' => (int) $item->duration_minutes,
                'remaining_ratio' => 1,
                'resume_time' => $item->start_time,
            ];
        }

        $metrics = $this->interruptionMetricsForDate($item, $booking->booking_date->toDateString());

        return [
            'played_minutes' => $metrics['played_minutes'],
            'remaining_minutes' => $metrics['remaining_minutes'],
            'remaining_ratio' => $metrics['remaining_ratio'],
            'resume_time' => $metrics['resume_time'],
            'estimated_refund_amount' => round((float) $item->subtotal * $metrics['remaining_ratio'], 2),
        ];
    }

    private function notifyBookingCustomer(Booking $booking, string $title, string $body, array $data = []): void
    {
        if (! $booking->customer_id || ! Schema::hasTable('notifications')) {
            return;
        }

        Notification::query()->create([
            'user_id' => $booking->customer_id,
            'type' => 'booking_schedule_lock',
            'title' => $title,
            'body' => $body,
            'reference_type' => Booking::class,
            'reference_id' => $booking->id,
            'data' => array_merge([
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
            ], $data),
            'created_at' => now(),
        ]);
    }

    private function time(?string $value): string
    {
        return substr((string) $value, 0, 5);
    }

    private function activeSlotLockConstraint($query): void
    {
        $query->whereIn('lock_type', ['manual', 'emergency'])
            ->orWhere(function ($autoQuery): void {
                $autoQuery->where('lock_type', 'auto')
                    ->where('expires_at', '>', Carbon::now());
            });
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
    }

    private function businessNow(): Carbon
    {
        return Carbon::now($this->businessTimezone());
    }

    private function businessToday(): string
    {
        return $this->businessNow()->toDateString();
    }

    private function businessDateTime(string $date, string $time): Carbon
    {
        $normalizedTime = substr($time, 0, 8);

        if ($normalizedTime === '24:00:00') {
            return Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $this->businessTimezone())->addDay();
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$normalizedTime}", $this->businessTimezone());
    }

    private function minutesToTime(int $minutes): string
    {
        if ($minutes === 24 * 60) {
            return '24:00:00';
        }

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private function fullDayLockedCourtIdsForRange(string $clusterId, string $startDate, string $endDate, Collection $locks): array
    {
        $dates = $this->dateRange($startDate, $endDate);
        if ($dates->count() > 31) {
            return [];
        }

        $courts = VenueCourt::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'active')
            ->pluck('id');

        $locksByCourtAndDate = $locks->groupBy(
            fn (SlotLock $lock): string => $lock->venue_court_id.'|'.$lock->booking_date?->toDateString(),
        );

        $hoursByDate = [];

        return $courts
            ->filter(function ($courtId) use ($clusterId, $dates, $locksByCourtAndDate, &$hoursByDate): bool {
                foreach ($dates as $date) {
                    $hours = $hoursByDate[$date]
                        ??= $this->bookingService->resolveOperatingHours($clusterId, $date);

                    if (! ($hours['is_open'] ?? false)) {
                        return false;
                    }

                    $dateLocks = $locksByCourtAndDate->get($courtId.'|'.$date, collect());
                    if (! $this->lockIntervalsCoverRange($dateLocks, $hours['open_time'], $hours['close_time'])) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }

    private function lockIntervalsCoverRange(Collection $locks, string $startTime, string $endTime): bool
    {
        $targetStart = $this->timeToMinutes($startTime);
        $targetEnd = $this->timeToMinutes($endTime);
        if ($targetEnd <= $targetStart) {
            return false;
        }

        $cursor = $targetStart;
        $intervals = $locks
            ->map(fn (SlotLock $lock): array => [
                'start' => $this->timeToMinutes($lock->start_time),
                'end' => $this->timeToMinutes($lock->end_time),
            ])
            ->sortBy('start')
            ->values();

        foreach ($intervals as $interval) {
            if ($interval['end'] <= $cursor) {
                continue;
            }

            if ($interval['start'] > $cursor) {
                return false;
            }

            $cursor = max($cursor, $interval['end']);
            if ($cursor >= $targetEnd) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUnlockRanges(SlotLock $lock, Collection $ranges): array
    {
        $lockStart = $this->timeToMinutes($lock->start_time);
        $lockEnd = $this->timeToMinutes($lock->end_time);
        $normalized = $ranges
            ->map(function (array $range) use ($lockStart, $lockEnd): array {
                $start = $this->timeToMinutes($range['start_time']);
                $end = $this->timeToMinutes($range['end_time']);

                if ($start % 30 !== 0 || $end % 30 !== 0 || $end <= $start) {
                    throw ValidationException::withMessages([
                        'ranges' => 'Các ô mở khóa phải theo bước 30 phút và có giờ kết thúc hợp lệ.',
                    ]);
                }

                if ($start < $lockStart || $end > $lockEnd) {
                    throw ValidationException::withMessages([
                        'ranges' => 'Chỉ được chọn phần thời gian đang nằm trong khoảng khóa.',
                    ]);
                }

                return ['start' => $start, 'end' => $end];
            })
            ->sortBy('start')
            ->values();

        $merged = [];
        foreach ($normalized as $range) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex < 0 || $range['start'] > $merged[$lastIndex]['end']) {
                $merged[] = $range;

                continue;
            }

            $merged[$lastIndex]['end'] = max($merged[$lastIndex]['end'], $range['end']);
        }

        return $merged;
    }

    private function remainingLockRanges(SlotLock $lock, array $unlockedRanges): array
    {
        $remaining = [];
        $cursor = $this->timeToMinutes($lock->start_time);
        $lockEnd = $this->timeToMinutes($lock->end_time);

        foreach ($unlockedRanges as $range) {
            if ($range['start'] > $cursor) {
                $remaining[] = ['start' => $cursor, 'end' => $range['start']];
            }

            $cursor = max($cursor, $range['end']);
        }

        if ($cursor < $lockEnd) {
            $remaining[] = ['start' => $cursor, 'end' => $lockEnd];
        }

        return $remaining;
    }

    private function dateRange(string $startDate, string $endDate): Collection
    {
        $dates = collect();
        $current = Carbon::parse($startDate, $this->businessTimezone())->startOfDay();
        $end = Carbon::parse($endDate, $this->businessTimezone())->startOfDay();

        while ($current->lte($end)) {
            $dates->push($current->toDateString());
            $current->addDay();
        }

        return $dates;
    }
}
