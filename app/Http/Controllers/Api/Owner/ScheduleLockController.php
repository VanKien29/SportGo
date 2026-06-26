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
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $this->ensureClusterAccess($request, $data['venue_cluster_id']);

        $locks = SlotLock::query()
            ->with('venueCourt.courtType:id,name')
            ->where('venue_cluster_id', $data['venue_cluster_id'])
            ->whereIn('lock_type', ['manual', 'emergency'])
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

    public function preview(Request $request): JsonResponse
    {
        $data = $this->validateLockPayload($request, false);
        [$dates, $requestedSlots, $isBatch] = $this->prepareLockRanges($data);

        $this->validateRequestedCourts($request, $requestedSlots, $isBatch);

        $affectedItems = collect();
        foreach ($dates as $date) {
            foreach ($requestedSlots as $slot) {
                $affectedItems = $affectedItems->merge($this->affectedBookingItemsForRange(
                    $slot['venue_court_id'],
                    $date,
                    $slot['start_time'],
                    $slot['end_time'],
                ));
            }
        }

        $affectedItems = $affectedItems
            ->unique('id')
            ->values()
            ->map(fn (BookingItem $item): array => $this->affectedBookingItemPayload($item));

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

                    if ($this->hasOverlappingScheduleLock(
                        $court->id,
                        $date,
                        $slot['start_time'],
                        $slot['end_time']
                    )) {
                        throw ValidationException::withMessages([
                            $isBatch ? "slots.{$index}.start_time" : 'start_time' => "{$court->name} ngày {$date} đã có khoảng khóa trùng giờ.",
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
                        'lock_type' => $data['lock_type'] ?? 'manual',
                        'reason' => $data['reason'],
                        'expires_at' => Carbon::parse($date)->endOfDay(),
                    ])->load('venueCourt.courtType');

                    $this->audit($request, 'schedule_lock.created', $lock, null, $this->payload($lock));
                    $this->resolveOverlappingBookingItems($request, $lock, $resolutions);
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

    private function hasOverlappingScheduleLock(string $venueCourtId, string $date, string $startTime, string $endTime): bool
    {
        return SlotLock::query()
            ->where('booking_date', $date)
            ->where(fn ($query) => $this->activeSlotLockConstraint($query))
            ->whereNull('booking_id')
            ->where(function ($query) use ($venueCourtId): void {
                $query->where('venue_court_id', $venueCourtId)
                    ->orWhere(function ($clusterQuery) use ($venueCourtId): void {
                        $clusterQuery
                            ->where('lock_scope', 'cluster')
                            ->where('venue_cluster_id', VenueCourt::query()->whereKey($venueCourtId)->value('venue_cluster_id'));
                    });
            })
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    private function validateLockPayload(Request $request, bool $requireReason): array
    {
        return $request->validate([
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
            'reason' => [$requireReason ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'lock_type' => ['nullable', 'in:manual,emergency'],
            'resolutions' => ['nullable', 'array'],
            'resolutions.*.booking_item_id' => ['required_with:resolutions', 'uuid', 'exists:booking_items,id'],
            'resolutions.*.action' => ['required_with:resolutions', 'in:switch,cancel,cash_refund'],
            'resolutions.*.venue_court_id' => ['nullable', 'uuid', 'exists:venue_courts,id'],
        ]);
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

        if ($requestedSlots->map(fn (array $slot): string => implode('|', $slot))->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'slots' => 'Danh sách có khung giờ bị trùng.',
            ]);
        }

        if ($dates->count() * $requestedSlots->count() > 500) {
            throw ValidationException::withMessages([
                'slots' => 'Số khoảng khóa quá lớn. Vui lòng chia thành nhiều lần tạo.',
            ]);
        }

        return [$dates, $requestedSlots, $isBatch];
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

    private function resolveOverlappingBookingItems(Request $request, SlotLock $lock, Collection $resolutions): void
    {
        $items = $this->affectedBookingItemsForRange(
            $lock->venue_court_id,
            $lock->booking_date->toDateString(),
            $lock->start_time,
            $lock->end_time,
        );

        $walletCancellations = collect();
        $cashCancellations = collect();

        foreach ($items as $item) {
            $resolution = $resolutions->get($item->id);
            if (($resolution['action'] ?? null) === 'switch') {
                $this->switchAffectedBookingItem($request, $item, $resolution['venue_court_id'] ?? null, $lock);
                continue;
            }

            if (($resolution['action'] ?? null) === 'cash_refund') {
                if (! $item->booking?->payments?->where('status', 'paid')->count()) {
                    throw ValidationException::withMessages([
                        'resolutions' => 'Chỉ được ghi nhận hoàn tiền mặt cho booking đã thanh toán.',
                    ]);
                }
                $cashCancellations->push($item);
                continue;
            }

            $walletCancellations->push($item);
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

    private function switchAffectedBookingItem(Request $request, BookingItem $item, ?string $newCourtId, SlotLock $lock): void
    {
        if (! $newCourtId) {
            throw ValidationException::withMessages([
                'resolutions' => 'Vui lòng chọn sân thay thế cho booking bị ảnh hưởng.',
            ]);
        }

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

        if ($booking->venue_court_id === $oldCourt?->id) {
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

    private function remainingRatioForItem(BookingItem $item, SlotLock $lock): float
    {
        return $this->interruptionMetrics($item, $lock)['remaining_ratio'];
    }

    private function hasPlayingItem(Collection $items, SlotLock $lock): bool
    {
        $date = $lock->booking_date->toDateString();
        $now = Carbon::now();

        return $items->contains(function (BookingItem $item) use ($date, $now): bool {
            $start = Carbon::parse("{$date} {$item->start_time}");
            $end = Carbon::parse("{$date} {$item->end_time}");

            return $now->betweenIncluded($start, $end);
        });
    }

    private function isPlayingItem(BookingItem $item, SlotLock $lock): bool
    {
        $date = $lock->booking_date->toDateString();
        $now = Carbon::now();
        $start = Carbon::parse("{$date} {$item->start_time}");
        $end = Carbon::parse("{$date} {$item->end_time}");

        return $now->betweenIncluded($start, $end);
    }

    private function interruptionMetrics(BookingItem $item, SlotLock $lock): array
    {
        return $this->interruptionMetricsForDate($item, $lock->booking_date->toDateString());
    }

    private function interruptionMetricsForDate(BookingItem $item, string $date): array
    {
        $now = Carbon::now();
        $start = Carbon::parse("{$date} {$item->start_time}");
        $end = Carbon::parse("{$date} {$item->end_time}");
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

    private function affectedBookingItemPayload(BookingItem $item): array
    {
        $booking = $item->booking;

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
            'is_playing' => $this->isPlayingForDate($item, $booking?->booking_date?->toDateString()),
            'incident' => $this->incidentPreviewPayload($item, $booking),
            'alternatives' => $this->availableAlternativeCourtsForItem($item)
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

    private function availableAlternativeCourtsForItem(BookingItem $item): Collection
    {
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

        return VenueCourt::query()
            ->with('courtType')
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('court_type_id', $court->court_type_id)
            ->where('status', 'active')
            ->whereKeyNot($court->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (VenueCourt $candidate): bool => $this->bookingService->checkAvailability(
                $candidate->id,
                $booking->booking_date->toDateString(),
                $availabilityStart,
                $item->end_time,
                $booking->id,
            ))
            ->values();
    }

    private function isPlayingForDate(BookingItem $item, ?string $date): bool
    {
        if (! $date) {
            return false;
        }

        $now = Carbon::now();
        $start = Carbon::parse("{$date} {$item->start_time}");
        $end = Carbon::parse("{$date} {$item->end_time}");

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
