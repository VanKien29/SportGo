<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\SlotLock;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Finance\AdminRefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerBookingCancellationService
{
    public function __construct(
        private readonly AdminRefundService $refunds,
        private readonly BookingService $bookings,
        private readonly BookingLifecycleService $lifecycle,
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

            $fromStatus = $booking->status;
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
            $this->bookings->releaseVoucherUsageForBooking($booking, 'cancelled');
            $this->invalidatePendingPayments($booking, "Booking bị {$targetStatus}: {$reason}");

            $reasonCode = $targetStatus === 'rejected' ? 'owner_rejected' : 'owner_cancelled';
            $this->lifecycle->recordHistory(
                $booking,
                $fromStatus,
                $targetStatus,
                $reasonCode,
                $reason,
                $actor,
            );
            $this->lifecycle->notifyStatusChanged(
                $booking,
                $targetStatus,
                $reasonCode,
                $reason,
            );

            $refunds = $this->createFullRefundRequests(
                $booking,
                $actor,
                "Chủ sân hủy booking: {$reason}",
                'owner_booking_cancelled',
            );

            $this->lifecycle->notifyMatchmakingBookingChanged(
                $booking,
                'booking-cancelled-'.$booking->id.'-'.$targetStatus,
                'Kèo giao lưu bị hủy',
                'Booking gốc của bài giao lưu đã bị chủ sân hủy. Khoản đã thanh toán được hoàn vào ví SportGo nếu có.',
                [
                    'status' => $targetStatus,
                    'reason' => $reason,
                    'refund_destination' => 'user_wallet',
                    'refund_amount' => round(array_sum(array_map(fn (array $refund): float => (float) ($refund['amount'] ?? 0), $refunds)), 2),
                    'refunds' => $refunds,
                ],
            );

            return [
                'booking' => $booking->fresh(['venueCourt.courtType', 'customer', 'payments', 'items']),
                'refunds' => $refunds,
            ];
        });
    }

    private function invalidatePendingPayments(Booking $booking, string $reason): void
    {
        Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->get()
            ->each(function (Payment $payment) use ($reason): void {
                $payment->forceFill([
                    'status' => 'failed',
                    'gateway_response' => array_merge((array) $payment->gateway_response, [
                        'invalidated_at' => now()->toIso8601String(),
                        'invalidated_reason' => $reason,
                    ]),
                ])->save();

                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => 'owner_booking_payment_invalidated',
                    'status_before' => 'pending',
                    'status_after' => 'failed',
                    'error_code' => 'booking_cancelled_by_owner',
                    'error_message' => $reason,
                ]);
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

                $this->bookings->releaseVoucherUsageForBooking($booking, 'cancelled');
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

            $this->lifecycle->notifyMatchmakingBookingChanged(
                $booking,
                'booking-maintenance-cancelled-'.$booking->id.'-'.$items->pluck('id')->sort()->implode('-'),
                $activeItemsLeft ? 'Một phần kèo giao lưu bị hủy do khóa sân' : 'Kèo giao lưu bị hủy do khóa sân',
                $activeItemsLeft
                    ? 'Một phần khung giờ của booking gốc bị hủy do sân cần khóa/bảo trì. Khoản đã thanh toán được xử lý hoàn theo thông tin bên dưới.'
                    : 'Booking gốc của bài giao lưu đã bị hủy do sân cần khóa/bảo trì. Khoản đã thanh toán được xử lý hoàn theo thông tin bên dưới.',
                [
                    'status' => $activeItemsLeft ? 'partially_cancelled' : 'cancelled',
                    'reason' => $reason,
                    'booking_item_ids' => $items->pluck('id')->values()->all(),
                    'refund_destination' => $completeAsCashRefund ? 'cash' : 'user_wallet',
                    'refund_amount' => round(array_sum(array_map(fn (array $refund): float => (float) ($refund['amount'] ?? 0), $refunds)), 2),
                    'refunds' => $refunds,
                ],
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
                    ->whereNotIn('status', ['owner_rejected'])
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
                    'status' => 'pending_owner_confirmation',
                    'status_reason' => $completeAsCashRefund
                        ? 'Chủ sân đã hoàn tiền mặt trực tiếp tại sân.'
                        : 'Chủ sân hủy hoặc khóa lịch, hoàn phần bị ảnh hưởng vào ví SportGo của khách.',
                    'owner_confirmed_by' => $actor->id,
                    'owner_confirmed_at' => now(),
                    'owner_confirm_note' => $reason,
                ]);

                $targetStatus = $completeAsCashRefund ? 'completed_cash' : 'completed';
                $refund = $this->refunds->updateStatus($refund, $targetStatus, [
                    'actor_id' => $actor->id,
                    'actor_type' => 'owner',
                    'reason' => $reason,
                    'source' => $source,
                    'gateway_refund_txn_id' => $completeAsCashRefund ? 'CASH-'.$refund->id : null,
                ]);
                $this->writeRefundHistory($refund, $actor, $reason, $source, $ratio, 'pending_owner_confirmation');

                $created[] = $refund->fresh()->toArray();
            });

        return $created;
    }

    private function writeRefundHistory(Refund $refund, User $actor, string $reason, string $source, float $ratio, string $oldStatus): void
    {
        if (! Schema::hasTable('refund_status_histories')) {
            return;
        }

        RefundStatusHistory::query()->create([
            'refund_id' => $refund->id,
            'old_status' => $oldStatus,
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
