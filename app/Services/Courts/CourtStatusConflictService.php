<?php

namespace App\Services\Courts;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Notification;
use App\Models\SlotLock;
use App\Models\VenueCourt;
use App\Services\BookingService;
use App\Services\Bookings\BookingLifecycleService;
use App\Services\Bookings\OwnerBookingCancellationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourtStatusConflictService
{
    public function __construct(
        private readonly OwnerBookingCancellationService $ownerBookingCancellationService,
        private readonly BookingService $bookingService,
        private readonly BookingLifecycleService $bookingLifecycle,
    ) {}

    /**
     * Lấy danh sách booking items trong tương lai bị ảnh hưởng khi sân con đổi trạng thái
     */
    public function getFutureAffectedBookingItems(VenueCourt $court): Collection
    {
        $today = $this->businessToday();
        $currentTime = $this->businessNow()->format('H:i:s');

        return BookingItem::query()
            ->with(['booking.customer', 'booking.payments', 'venueCourt.courtType'])
            ->where('venue_court_id', $court->id)
            ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['active', 'moved']))
            ->where(function ($query) use ($today, $currentTime): void {
                $query->whereHas('booking', function ($bookingQuery) use ($today): void {
                    $bookingQuery
                        ->whereDate('booking_date', '>', $today)
                        ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in']);
                })->orWhere(function ($todayQuery) use ($today, $currentTime): void {
                    $todayQuery->whereHas('booking', function ($bookingQuery) use ($today): void {
                        $bookingQuery
                            ->whereDate('booking_date', '=', $today)
                            ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in']);
                    })->where('end_time', '>', $currentTime);
                });
            })
            ->get();
    }

    /**
     * Dựng payload chi tiết các booking bị ảnh hưởng kèm sân thay thế tương thích
     */
    public function buildConflictPayload(Collection $items): array
    {
        $formattedItems = $items->map(fn (BookingItem $item): array => $this->affectedBookingItemPayload($item))
            ->values();

        return [
            'affected_count' => $formattedItems->count(),
            'items' => $formattedItems->all(),
        ];
    }

    /**
     * Xử lý giải quyết các booking trùng và cập nhật trạng thái sân con
     */
    public function resolveConflicts(
        Request $request,
        VenueCourt $court,
        string $targetStatus,
        ?string $reason,
        array $resolutions
    ): void {
        $items = $this->getFutureAffectedBookingItems($court);

        if ($items->isEmpty()) {
            $court->update(['status' => $targetStatus]);
            return;
        }

        $resolutionsMap = collect($resolutions)->keyBy('booking_item_id');
        $missingResolutionIds = $items
            ->pluck('id')
            ->reject(fn ($itemId): bool => $resolutionsMap->has($itemId))
            ->values();

        if ($missingResolutionIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'resolutions' => 'Vui lòng chọn phương án xử lý cho tất cả booking bị ảnh hưởng.',
            ]);
        }

        $statusLabel = $targetStatus === 'inactive' ? 'tạm ngưng' : 'bảo trì';
        $finalReason = $reason ?: "Sân chuyển sang trạng thái {$statusLabel}.";

        DB::transaction(function () use ($request, $court, $items, $resolutionsMap, $targetStatus, $finalReason): void {
            $walletCancellations = collect();
            $cashCancellations = collect();

            foreach ($items as $item) {
                $resolution = $resolutionsMap->get($item->id);
                $scope = ($resolution['scope'] ?? 'affected') === 'booking_item' ? 'booking_item' : 'affected';
                $action = $resolution['action'] ?? 'cancel';

                if ($action === 'switch') {
                    $this->switchAffectedBookingItem(
                        $request,
                        $item,
                        $resolution['venue_court_id'] ?? null,
                        $finalReason,
                        $scope
                    );
                    continue;
                }

                if ($action === 'cash_refund') {
                    if (! $item->booking?->payments?->where('status', 'paid')->count()) {
                        throw ValidationException::withMessages([
                            'resolutions' => 'Chỉ được ghi nhận hoàn tiền mặt cho booking đã thanh toán.',
                        ]);
                    }
                    $cashCancellations->push($this->isolateBookingItemForScope($request, $item, $scope));
                    continue;
                }

                $walletCancellations->push($this->isolateBookingItemForScope($request, $item, $scope));
            }

            $this->cancelAffectedItems($request, $finalReason, $walletCancellations, false);
            $this->cancelAffectedItems($request, $finalReason, $cashCancellations, true);

            $oldStatus = $court->status;
            $court->update(['status' => $targetStatus]);

            AuditLog::query()->create([
                'actor_id' => $request->user()->id,
                'actor_type' => 'owner',
                'module' => 'venue_court',
                'action' => 'venue_court.status_changed_with_resolutions',
                'entity_type' => VenueCourt::class,
                'entity_id' => $court->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $targetStatus, 'reason' => $finalReason],
                'context' => 'owner',
                'metadata' => [
                    'venue_cluster_id' => $court->venue_cluster_id,
                    'resolved_items_count' => $items->count(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });
    }

    private function affectedBookingItemPayload(BookingItem $item): array
    {
        $booking = $item->booking;
        $date = $booking?->booking_date?->toDateString();
        $isPlaying = $this->isPlayingForDate($item, $date);
        $interruption = $isPlaying ? $this->interruptionMetricsForDate($item, $date) : null;
        $affectedStart = $isPlaying ? ($interruption['resume_time'] ?? $item->start_time) : $item->start_time;

        $duration = $this->timeToMinutes($item->end_time) - $this->timeToMinutes($affectedStart);
        $origDuration = max($this->timeToMinutes($item->end_time) - $this->timeToMinutes($item->start_time), 1);
        $subtotal = round((float) $item->subtotal * max($duration, 0) / $origDuration, 2);

        $affectedRange = [
            'start_time' => $affectedStart,
            'end_time' => $item->end_time,
            'duration_minutes' => max($duration, 0),
            'subtotal' => $subtotal,
        ];

        $alternatives = $this->availableAlternativeCourtsForItem(
            $item,
            $affectedRange['start_time'],
            $affectedRange['end_time']
        )->map(fn (VenueCourt $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'court_type' => $c->courtType ? [
                'id' => $c->courtType->id,
                'name' => $c->courtType->name,
            ] : null,
        ])->values()->all();

        $fullAlternatives = $this->availableAlternativeCourtsForItem(
            $item,
            $item->start_time,
            $item->end_time
        )->map(fn (VenueCourt $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'court_type' => $c->courtType ? [
                'id' => $c->courtType->id,
                'name' => $c->courtType->name,
            ] : null,
        ])->values()->all();

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
            'is_playing' => $isPlaying,
            'incident' => $interruption ? [
                'played_minutes' => $interruption['played_minutes'],
                'remaining_minutes' => $interruption['remaining_minutes'],
                'resume_time' => $interruption['resume_time'],
                'estimated_refund_amount' => round((float) $item->subtotal * ($interruption['remaining_ratio'] ?? 0), 2),
            ] : null,
            'alternatives' => $alternatives,
            'full_item_alternatives' => $fullAlternatives,
        ];
    }

    private function availableAlternativeCourtsForItem(
        BookingItem $item,
        ?string $rangeStart = null,
        ?string $rangeEnd = null
    ): Collection {
        $item->loadMissing(['booking', 'venueCourt.courtType']);
        $booking = $item->booking;
        $court = $item->venueCourt;

        if (! $booking || ! $court) {
            return collect();
        }

        $start = $rangeStart ?: $item->start_time;
        $end = $rangeEnd ?: $item->end_time;

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
                $start,
                $end,
                $booking->id,
            ))
            ->values();
    }

    private function switchAffectedBookingItem(
        Request $request,
        BookingItem $item,
        ?string $newCourtId,
        string $reason,
        string $scope
    ): void {
        if (! $newCourtId) {
            throw ValidationException::withMessages([
                'resolutions' => 'Vui lòng chọn sân thay thế cho booking bị ảnh hưởng.',
            ]);
        }

        $item = $this->isolateBookingItemForScope($request, $item, $scope);
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

        $dateStr = $booking->booking_date->toDateString();
        $isPlaying = $this->isPlayingForDate($item, $dateStr);
        $metrics = $this->interruptionMetricsForDate($item, $dateStr);
        $availabilityStart = $isPlaying ? $metrics['resume_time'] : $item->start_time;

        if (! $this->bookingService->checkAvailability(
            $newCourt->id,
            $dateStr,
            $availabilityStart,
            $item->end_time,
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'resolutions' => "{$newCourt->name} không còn trống trong khung giờ {$this->time($availabilityStart)} - {$this->time($item->end_time)}.",
            ]);
        }

        $switchReason = "Đổi sân do {$reason}";

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
                'status_reason' => $switchReason,
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
                'status_reason' => $switchReason,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $switchReason,
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
                'court_changed_reason' => $switchReason,
            ])->save();
        }

        SlotLock::query()
            ->where('booking_item_id', $item->id)
            ->update([
                'venue_court_id' => $newCourt->id,
                'reason' => $switchReason,
            ]);

        if ($scope === 'booking_item' && $booking->venue_court_id === $oldCourt?->id) {
            $booking->forceFill([
                'venue_court_id' => $newCourt->id,
                'court_changed_by' => $request->user()->id,
                'court_changed_at' => now(),
                'court_changed_reason' => $switchReason,
            ])->save();
        }

        $this->notifyBookingCustomer(
            $booking,
            'Lịch sân được đổi sang sân khác',
            "Khung {$this->time($item->start_time)} - {$this->time($item->end_time)} đã được đổi từ {$oldCourt?->name} sang {$newCourt->name} do sân cũ tạm ngưng/bảo trì.",
            [
                'booking_item_id' => $item->id,
                'from_venue_court_id' => $oldCourt?->id,
                'to_venue_court_id' => $newCourt->id,
            ]
        );

        $this->bookingLifecycle->notifyMatchmakingBookingChanged(
            $booking,
            'booking-court-switched-'.$booking->id.'-'.$item->id.'-'.$newCourt->id,
            'Kèo giao lưu được đổi sân',
            "Một khung giờ trong booking gốc được đổi từ {$oldCourt?->name} sang {$newCourt->name} do sân cũ tạm ngưng/bảo trì.",
            [
                'status' => $booking->status,
                'reason' => $switchReason,
                'booking_item_id' => $item->id,
                'from_venue_court_id' => $oldCourt?->id,
                'from_venue_court_name' => $oldCourt?->name,
                'to_venue_court_id' => $newCourt->id,
                'to_venue_court_name' => $newCourt->name,
                'booking_date' => $booking->booking_date?->toDateString(),
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
            ]
        );
    }

    private function cancelAffectedItems(
        Request $request,
        string $reason,
        Collection $items,
        bool $completeAsCashRefund
    ): void {
        $items
            ->groupBy(fn (BookingItem $item): string => $item->booking_id.'|'.($this->isPlayingForDate($item, $item->booking?->booking_date?->toDateString()) ? 'playing' : 'scheduled'))
            ->each(function (Collection $bookingItems, string $groupKey) use ($request, $reason, $completeAsCashRefund): void {
                [$bookingId, $timing] = explode('|', $groupKey, 2);
                $booking = Booking::query()
                    ->with(['items', 'payments', 'customer'])
                    ->find($bookingId);

                if (! $booking) {
                    return;
                }

                $isPlaying = $timing === 'playing';
                $refundRatio = $this->refundRatioForBookingItems($booking, $bookingItems);

                if ($isPlaying) {
                    $bookingItems->each(function (BookingItem $item) use ($completeAsCashRefund): void {
                        $metrics = $this->interruptionMetricsForDate($item, $item->booking?->booking_date?->toDateString());
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
                    $reason,
                    null,
                    $refundRatio,
                    $isPlaying ? 'maintenance_item_cancelled_mid_play' : 'maintenance_item_cancelled',
                    $completeAsCashRefund,
                    $isPlaying ? 'interrupted_by_emergency' : null
                );

                $this->notifyBookingCustomer(
                    $booking,
                    'Lịch sân bị hủy do sân tạm ngưng/bảo trì',
                    $completeAsCashRefund
                        ? 'Một phần lịch đặt của bạn bị hủy do sân tạm ngưng/bảo trì. Chủ sân đã ghi nhận hoàn tiền mặt tại sân.'
                        : ($refundRatio > 0
                        ? 'Một phần lịch đặt của bạn bị hủy do sân tạm ngưng/bảo trì. Hệ thống đã tạo yêu cầu hoàn tiền vào ví SportGo.'
                        : 'Một phần lịch đặt của bạn bị hủy do sân tạm ngưng/bảo trì.'),
                    [
                        'booking_item_ids' => $bookingItems->pluck('id')->values()->all(),
                        'refund_ratio' => $refundRatio,
                        'refund_destination' => $completeAsCashRefund ? 'cash' : 'user_wallet',
                        'interrupted_while_playing' => $isPlaying,
                    ]
                );
            });
    }

    private function refundRatioForBookingItems(Booking $booking, Collection $items): float
    {
        $bookingSubtotal = max((float) $booking->items->sum(fn (BookingItem $item): float => (float) $item->subtotal), 0.01);
        $refundableSubtotal = $items->sum(function (BookingItem $item): float {
            $date = $item->booking?->booking_date?->toDateString();
            $metrics = $this->interruptionMetricsForDate($item, $date);
            return (float) $item->subtotal * ($metrics['remaining_ratio'] ?? 1.0);
        });

        return min(1, max(0, round($refundableSubtotal / $bookingSubtotal, 6)));
    }

    private function isolateBookingItemForScope(Request $request, BookingItem $item, string $scope): BookingItem
    {
        if ($scope === 'booking_item') {
            return $item;
        }

        $date = $item->booking?->booking_date?->toDateString();
        $isPlaying = $this->isPlayingForDate($item, $date);
        if (! $isPlaying) {
            return $item;
        }

        $metrics = $this->interruptionMetricsForDate($item, $date);
        $affectedStart = $metrics['resume_time'];

        if ($affectedStart === $item->start_time) {
            return $item;
        }

        return $this->splitBookingItemAroundAffectedRange($request, $item, $affectedStart, $item->end_time);
    }

    private function splitBookingItemAroundAffectedRange(Request $request, BookingItem $item, string $affectedStart, string $affectedEnd): BookingItem
    {
        $item = BookingItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
        $booking = Booking::query()->whereKey($item->booking_id)->lockForUpdate()->firstOrFail();

        $originalStart = $item->start_time;
        $originalEnd = $item->end_time;
        $originalDuration = max((int) $item->duration_minutes, 1);
        $originalSubtotal = (float) $item->subtotal;

        $leadDuration = $this->timeToMinutes($affectedStart) - $this->timeToMinutes($originalStart);
        $leadSubtotal = round($originalSubtotal * ($leadDuration / $originalDuration), 2);
        $affectedDuration = $this->timeToMinutes($affectedEnd) - $this->timeToMinutes($affectedStart);
        $affectedSubtotal = round($originalSubtotal * ($affectedDuration / $originalDuration), 2);

        $nextSortOrder = ((int) BookingItem::query()->where('booking_id', $booking->id)->max('sort_order')) + 1;

        $item->forceFill([
            'end_time' => $affectedStart,
            'duration_minutes' => $leadDuration,
            'subtotal' => $leadSubtotal,
        ])->save();

        SlotLock::query()
            ->where('booking_item_id', $item->id)
            ->update(['end_time' => $affectedStart]);

        $affectedItem = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'venue_court_id' => $item->venue_court_id,
            'start_time' => $affectedStart,
            'end_time' => $affectedEnd,
            'duration_minutes' => $affectedDuration,
            'unit_price' => $item->unit_price,
            'subtotal' => $affectedSubtotal,
            'status' => $item->status,
            'status_reason' => $item->status_reason,
            'sort_order' => $nextSortOrder,
        ]);

        SlotLock::query()->create([
            'venue_cluster_id' => $booking->venue_cluster_id,
            'venue_court_id' => $item->venue_court_id,
            'lock_scope' => 'court',
            'booking_date' => $booking->booking_date,
            'start_time' => $affectedStart,
            'end_time' => $affectedEnd,
            'locked_by' => $item->booking?->customer_id ?? $request->user()->id,
            'booking_id' => $booking->id,
            'booking_item_id' => $affectedItem->id,
            'lock_type' => 'booking',
            'reason' => 'Giữ chỗ cho phần booking còn lại',
            'expires_at' => Carbon::parse($booking->booking_date, $this->businessTimezone())->endOfDay(),
        ]);

        return $affectedItem;
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

    private function interruptionMetricsForDate(BookingItem $item, ?string $date): array
    {
        if (! $date) {
            return [
                'interrupted_at' => $this->businessNow(),
                'played_minutes' => 0,
                'remaining_minutes' => (int) $item->duration_minutes,
                'remaining_ratio' => 1.0,
                'resume_time' => $item->start_time,
            ];
        }

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

    private function notifyBookingCustomer(Booking $booking, string $title, string $body, array $data = []): void
    {
        if (! $booking->customer_id) {
            return;
        }

        Notification::query()->create([
            'user_id' => $booking->customer_id,
            'type' => 'emergency_court_maintenance',
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
}
