<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\SlotLock;
use App\Models\User;
use App\Services\Finance\AdminRefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerBookingCancellationService
{
    public function __construct(
        private readonly AdminRefundService $refunds,
    ) {}

    public function cancelBooking(Booking $booking, User $actor, string $reason, string $targetStatus = 'cancelled'): array
    {
        return DB::transaction(function () use ($booking, $actor, $reason, $targetStatus): array {
            $booking = Booking::query()
                ->with(['items', 'payments'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($targetStatus, ['cancelled', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'action' => 'Trạng thái hủy booking không hợp lệ.',
                ]);
            }

            $booking->forceFill([
                'status' => $targetStatus,
                'status_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ])->save();

            $itemStatus = $targetStatus === 'rejected' ? 'cancelled_by_owner' : 'cancelled_by_owner';

            BookingItem::query()
                ->where('booking_id', $booking->id)
                ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['active', 'moved']))
                ->update([
                    'status' => $itemStatus,
                    'status_reason' => $reason,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                ]);

            SlotLock::query()->where('booking_id', $booking->id)->delete();

            $refunds = $this->createFullRefundRequests(
                $booking,
                $actor,
                "Chủ sân hủy booking: {$reason}",
                'owner_booking_cancelled',
            );

            return [
                'booking' => $booking->fresh(['venueCourt.courtType', 'customer', 'payments', 'items']),
                'refunds' => $refunds,
            ];
        });
    }

    public function cancelItemsForMaintenance(
        Booking $booking,
        array $bookingItemIds,
        User $actor,
        string $reason,
        ?string $maintenanceLockId = null,
        ?float $refundRatioOverride = null,
        string $source = 'maintenance_item_cancelled',
        bool $completeAsCashRefund = false,
        ?string $itemStatusOverride = null,
    ): array
    {
        return DB::transaction(function () use ($booking, $bookingItemIds, $actor, $reason, $maintenanceLockId, $refundRatioOverride, $source, $completeAsCashRefund, $itemStatusOverride): array {
            $booking = Booking::query()
                ->with(['items', 'payments'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = $booking->items
                ->whereIn('id', $bookingItemIds)
                ->filter(fn (BookingItem $item): bool => in_array($item->status ?: 'active', ['active', 'moved'], true));

            if ($items->isEmpty()) {
                return [
                    'booking' => $booking->fresh(['items', 'payments']),
                    'refunds' => [],
                ];
            }

            BookingItem::query()
                ->whereIn('id', $items->pluck('id')->all())
                ->update([
                    'status' => $itemStatusOverride ?: 'cancelled_by_maintenance',
                    'status_reason' => $reason,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                    'maintenance_lock_id' => $maintenanceLockId,
                ]);

            SlotLock::query()
                ->whereIn('booking_item_id', $items->pluck('id')->all())
                ->delete();

            $activeItemsLeft = BookingItem::query()
                ->where('booking_id', $booking->id)
                ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['active', 'moved']))
                ->exists();

            if (! $activeItemsLeft) {
                $booking->forceFill([
                    'status' => 'cancelled',
                    'status_reason' => $reason,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                ])->save();
            }

            $itemSubtotal = (float) $items->sum(fn (BookingItem $item): float => (float) $item->subtotal);
            $bookingSubtotal = max((float) $booking->items->sum(fn (BookingItem $item): float => (float) $item->subtotal), 0.01);
            $refundRatio = $refundRatioOverride === null
                ? min(1, max(0, $itemSubtotal / $bookingSubtotal))
                : min(1, max(0, $refundRatioOverride));

            $refunds = $this->createFullRefundRequests(
                $booking,
                $actor,
                "Hoàn tiền do bảo trì/khóa sân: {$reason}",
                $source,
                $refundRatio,
                $completeAsCashRefund,
            );

            return [
                'booking' => $booking->fresh(['items', 'payments']),
                'refunds' => $refunds,
            ];
        });
    }

    private function createFullRefundRequests(Booking $booking, User $actor, string $reason, string $source, float $ratio = 1.0, bool $completeAsCashRefund = false): array
    {
        if (! Schema::hasTable('refunds')) {
            return [];
        }

        $ratio = min(1, max(0, $ratio));
        if ($ratio <= 0) {
            return [];
        }

        $created = [];

        Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->orderBy('paid_at')
            ->lockForUpdate()
            ->get()
            ->each(function (Payment $payment) use ($booking, $actor, $reason, $source, $ratio, $completeAsCashRefund, &$created): void {
                $targetAmount = round((float) $payment->amount * $ratio, 2);
                $existingAmount = (float) Refund::query()
                    ->where('payment_id', $payment->id)
                    ->whereNotIn('status', ['failed', 'rejected', 'cancelled'])
                    ->sum('amount');
                $amount = round(max($targetAmount - $existingAmount, 0), 2);

                if ($amount <= 0) {
                    return;
                }

                $refund = Refund::query()->create([
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'amount' => $amount,
                    'reason' => $reason,
                    'refund_destination' => $completeAsCashRefund ? 'cash' : 'user_wallet',
                    'user_wallet_id' => $payment->user_wallet_id,
                    'status' => 'owner_confirmed',
                    'status_reason' => $completeAsCashRefund
                        ? 'Chủ sân đã hoàn tiền mặt trực tiếp tại sân.'
                        : 'Chủ sân hủy hoặc khóa lịch, hoàn phần bị ảnh hưởng vào ví SportGo của khách.',
                    'owner_confirmed_by' => $actor->id,
                    'owner_confirmed_at' => now(),
                    'owner_confirm_note' => $reason,
                ]);

                $this->writeRefundHistory($refund, $actor, $reason, $source, $ratio);

                if ($completeAsCashRefund) {
                    $refund = $this->refunds->updateStatus($refund, 'completed_cash', [
                        'actor_id' => $actor->id,
                        'reason' => $reason,
                        'source' => $source,
                        'gateway_refund_txn_id' => 'CASH-'.$refund->id,
                    ]);
                    $this->writeRefundHistory($refund, $actor, $reason, 'cash_refund_completed_at_venue', $ratio);
                }

                $created[] = $refund->fresh()->toArray();
            });

        return $created;
    }

    private function writeRefundHistory(Refund $refund, User $actor, string $reason, string $source, float $ratio): void
    {
        if (! Schema::hasTable('refund_status_histories')) {
            return;
        }

        RefundStatusHistory::query()->create([
            'refund_id' => $refund->id,
            'old_status' => null,
            'new_status' => $refund->status,
            'changed_by' => $actor->id,
            'actor_type' => 'owner',
            'reason' => $reason,
            'metadata' => [
                'source' => $source,
                'refund_ratio' => $ratio,
                'auto_owner_confirmed' => true,
            ],
            'created_at' => now(),
        ]);
    }
}
