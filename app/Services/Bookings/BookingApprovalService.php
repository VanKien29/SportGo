<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Refund;
use App\Models\SlotLock;
use App\Models\User;
use App\Services\Finance\AdminRefundService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingApprovalService
{
    public const APPROVAL_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly AdminRefundService $refunds,
        private readonly BookingLifecycleService $lifecycle,
    ) {}

    public function approvalDeadline(Booking $booking): Carbon
    {
        $timezone = $this->businessTimezone();
        $lock = $booking->relationLoaded('slotLocks')
            ? $booking->slotLocks
                ->where('lock_type', 'auto')
                ->sortBy('expires_at')
                ->first()
            : $booking->slotLocks()
                ->where('lock_type', 'auto')
                ->orderBy('expires_at')
                ->first();
        $deadline = $booking->approval_deadline_at
            ? $booking->approval_deadline_at->copy()->setTimezone($timezone)
            : ($lock?->expires_at
                ? Carbon::parse($lock->expires_at)->setTimezone($timezone)
                : $booking->created_at->copy()->setTimezone($timezone)->addMinutes(self::APPROVAL_WINDOW_MINUTES));

        $sessionStart = $this->sessionStart($booking);

        return $sessionStart && $sessionStart->lessThan($deadline) ? $sessionStart : $deadline;
    }

    public function approvalDeadlineForValues(string $bookingDate, string $startTime, Carbon $createdAt): Carbon
    {
        $timezone = $this->businessTimezone();
        $deadline = $createdAt->copy()->setTimezone($timezone)->addMinutes(self::APPROVAL_WINDOW_MINUTES);
        $sessionStart = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $bookingDate.' '.substr($startTime, 0, 8),
            $timezone,
        );

        return $sessionStart->lessThan($deadline) ? $sessionStart : $deadline;
    }

    public function approvalSecondsLeft(Booking $booking): int
    {
        if ($booking->status !== 'pending_approval') {
            return 0;
        }

        return max(0, (int) Carbon::now($this->businessTimezone())->diffInSeconds($this->approvalDeadline($booking), false));
    }

    public function initializeApprovalDeadline(Booking $booking): ?Carbon
    {
        if ($booking->status !== 'pending_approval') {
            return null;
        }

        $deadline = $this->approvalDeadline($booking);
        $booking->forceFill(['approval_deadline_at' => $deadline])->save();

        return $deadline;
    }

    public function approve(Booking $booking, User $actor): array
    {
        return DB::transaction(function () use ($booking, $actor): array {
            $locked = Booking::query()
                ->with(['payments', 'venueCluster'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'action' => 'Booking không còn ở trạng thái chờ chủ sân duyệt.',
                ]);
            }

            if (Carbon::now($this->businessTimezone())->greaterThanOrEqualTo($this->approvalDeadline($locked))) {
                $this->expireLocked($locked, 'Chủ sân không duyệt booking trong 30 phút. Slot đã được giải phóng.');

                return [
                    'expired' => true,
                    'booking' => $locked->fresh(['venueCourt.courtType', 'customer', 'payments']),
                ];
            }

            $paidAmount = (float) $locked->payments->where('status', 'paid')->sum('amount');
            $depositPaid = $locked->payment_option === 'deposit'
                && ($locked->required_payment_amount <= 0 || $paidAmount + 0.01 >= (float) $locked->required_payment_amount);
            $fallback = $locked->payment_option === 'deposit' && ! $depositPaid;

            $locked->forceFill([
                'status' => 'confirmed',
                'effective_payment_option' => $fallback ? 'no_prepay' : ($locked->payment_option ?: 'no_prepay'),
                'owner_approved_at' => now(),
                'owner_approved_by' => $actor->id,
                'payment_deadline_at' => null,
                'payment_fallback_at' => $fallback ? now() : null,
                'payment_fallback_reason' => $fallback
                    ? 'Chủ sân đã duyệt nhưng khách chưa thanh toán cọc; booking được chuyển sang trả sau.'
                    : null,
                'status_reason' => $fallback
                    ? 'Đã duyệt. Booking được chuyển sang thanh toán tại sân vì chưa nhận cọc.'
                    : null,
            ])->save();

            $approvalReason = $fallback
                ? 'Chủ sân đã duyệt booking; do khách chưa thanh toán cọc nên booking chuyển sang trả sau.'
                : 'Chủ sân đã duyệt booking và xác nhận lịch chơi.';
            $this->lifecycle->recordHistory(
                $locked,
                'pending_approval',
                'confirmed',
                'owner_approved',
                $approvalReason,
                $actor,
                ['effective_payment_option' => $locked->effective_payment_option],
            );
            $this->lifecycle->notifyStatusChanged(
                $locked,
                'confirmed',
                'owner_approved',
                $approvalReason,
                ['effective_payment_option' => $locked->effective_payment_option],
            );

            if ($fallback) {
                $this->failPendingPayments($locked, 'Booking đã chuyển sang trả sau sau khi chủ sân duyệt.');
                $this->notifyFallback($locked);
            }

            SlotLock::query()->where('booking_id', $locked->id)->where('lock_type', 'auto')->delete();

            return [
                'expired' => false,
                'fallback_to_pay_later' => $fallback,
                'booking' => $locked->fresh(['venueCourt.courtType', 'customer', 'payments']),
            ];
        });
    }

    public function expireIfDue(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending_approval') {
                return false;
            }

            if (Carbon::now($this->businessTimezone())->lessThan($this->approvalDeadline($locked))) {
                return false;
            }

            $this->expireLocked($locked, 'Chủ sân không duyệt booking trong 30 phút. Slot đã được giải phóng.');

            return true;
        });
    }

    public function refundLateDepositPayment(Booking $booking, Payment $payment, string $reason): ?Refund
    {
        if ($payment->status !== 'paid' || $booking->payment_option !== 'deposit') {
            return null;
        }

        return $this->refundPayment($booking, $payment, $reason);
    }

    private function expireLocked(Booking $booking, string $reason): void
    {
        $fromStatus = $booking->status;
        $booking->forceFill([
            'status' => 'expired',
            'status_reason' => $reason,
        ])->save();

        $this->lifecycle->recordHistory(
            $booking,
            $fromStatus,
            'expired',
            'owner_approval_timeout',
            $reason,
        );
        $this->lifecycle->notifyStatusChanged(
            $booking,
            'expired',
            'owner_approval_timeout',
            $reason,
        );

        $this->failPendingPayments($booking, $reason);
        $this->refundPaidDeposits($booking, $reason);
        app(\App\Services\BookingService::class)->releaseVoucherUsageForBooking($booking, 'cancelled');
        SlotLock::query()->where('booking_id', $booking->id)->delete();
    }

    private function refundPaidDeposits(Booking $booking, string $reason): void
    {
        $booking->loadMissing('payments');

        foreach ($booking->payments->where('status', 'paid')->where('payment_kind', 'deposit') as $payment) {
            $this->refundPayment($booking, $payment, $reason);
        }
    }

    private function refundPayment(Booking $booking, Payment $payment, string $reason): ?Refund
    {
        $alreadyRefunded = (float) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', ['completed', 'completed_cash', 'processing', 'admin_processing'])
            ->sum('amount');
        $amount = round(max((float) $payment->amount - $alreadyRefunded, 0), 2);

        if ($amount <= 0) {
            return null;
        }

        $refund = Refund::query()->create([
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'amount' => $amount,
            'reason' => 'Chủ sân hủy booking do quá hạn duyệt. '.$reason,
            'status_reason' => 'Tự động hoàn tiền cọc vào ví SportGo.',
            'refund_destination' => 'user_wallet',
            'status' => 'processing',
        ]);

        $completed = $this->refunds->updateStatus($refund, 'completed', [
            'source' => 'booking_approval_auto_refund',
            'reason' => 'Chủ sân hủy booking do quá hạn duyệt. '.$reason,
        ]);

        if ($booking->customer_id) {
            Notification::query()->firstOrCreate(
                [
                    'user_id' => $booking->customer_id,
                    'type' => 'booking_deposit_refunded',
                    'reference_type' => 'booking',
                    'reference_id' => (string) $booking->id,
                ],
                [
                    'title' => 'Đã hoàn tiền cọc vào ví',
                    'body' => 'Khoản cọc của booking '.$booking->booking_code.' đã được hoàn vào Ví SportGo vì booking hết hiệu lực.',
                    'data' => [
                        'booking_id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'refund_id' => $completed?->id,
                        'action_url' => '/booking/'.$booking->id,
                    ],
                    'is_read' => false,
                ],
            );
        }

        return $completed;
    }

    private function failPendingPayments(Booking $booking, string $reason): void
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
                    'event_type' => 'booking_approval_payment_invalidated',
                    'status_before' => 'pending',
                    'status_after' => 'failed',
                    'error_code' => 'booking_approval_closed',
                    'error_message' => $reason,
                ]);
            });
    }

    private function notifyFallback(Booking $booking): void
    {
        if (! $booking->customer_id) {
            return;
        }

        Notification::query()->firstOrCreate(
            [
                'user_id' => $booking->customer_id,
                'type' => 'booking_deposit_fallback',
                'reference_type' => 'booking',
                'reference_id' => (string) $booking->id,
            ],
            [
                'title' => 'Booking đã chuyển sang trả sau',
                'body' => 'Chủ sân đã duyệt booking nhưng chưa nhận cọc. Bạn sẽ thanh toán tại sân khi đến chơi.',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'status' => 'confirmed',
                    'effective_payment_option' => 'no_prepay',
                    'action_url' => '/booking/'.$booking->id,
                ],
                'is_read' => false,
            ],
        );
    }

    private function sessionStart(Booking $booking): ?Carbon
    {
        if (! $booking->booking_date || ! $booking->start_time) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $booking->booking_date->format('Y-m-d').' '.substr((string) $booking->start_time, 0, 8),
            $this->businessTimezone(),
        );
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
    }
}
