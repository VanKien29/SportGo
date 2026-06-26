<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SlotLock;
use App\Services\Memberships\SystemVipService;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminPaymentService
{
    public function __construct(
        private readonly OwnerWalletService $ownerWalletService,
        private readonly SystemVipService $systemVipService,
    ) {}

    public function retry(Payment $payment, array $context): Payment
    {
        return DB::transaction(function () use ($payment, $context): Payment {
            $payment = Payment::query()
                ->with('booking')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($payment->status, ['pending', 'failed'], true)) {
                throw new RuntimeException('Chỉ payment đang chờ hoặc thất bại mới được retry.');
            }

            if ($payment->booking?->status !== 'pending_payment') {
                throw new RuntimeException('Chỉ được retry khi booking vẫn đang chờ thanh toán.');
            }

            $oldStatus = $payment->status;

            if ($payment->status === 'pending') {
                $payment->status = 'failed';
                $payment->save();
            }

            $newPayment = Payment::query()->create([
                'payment_code' => 'PM'.Str::upper(Str::random(10)),
                'payment_context' => $payment->payment_context,
                'booking_id' => $payment->booking_id,
                'subscription_id' => $payment->subscription_id,
                'system_bank_account_id' => $payment->system_bank_account_id,
                'amount' => $payment->amount,
                'wallet_amount' => $payment->wallet_amount,
                'gateway_amount' => $payment->gateway_amount,
                'user_wallet_id' => $payment->user_wallet_id,
                'user_wallet_ledger_id' => null,
                'payment_kind' => $payment->payment_kind,
                'method' => $payment->method,
                'status' => 'pending',
            ]);

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'admin_payment_retry_replaced',
                'request_payload' => $context,
                'response_payload' => ['new_payment_id' => $newPayment->id, 'new_payment_code' => $newPayment->payment_code],
                'status_before' => $oldStatus,
                'status_after' => $payment->status,
                'error_code' => 'retry_replaced',
                'error_message' => 'Payment attempt được thay thế bằng một attempt mới.',
            ]);

            PaymentLog::query()->create([
                'payment_id' => $newPayment->id,
                'event_type' => 'admin_payment_retry_created',
                'request_payload' => $context,
                'response_payload' => ['source_payment_id' => $payment->id, 'source_payment_code' => $payment->payment_code],
                'status_before' => null,
                'status_after' => 'pending',
            ]);

            return $newPayment;
        });
    }

    public function updateStatus(Payment $payment, string $status, array $context): Payment
    {
        return DB::transaction(function () use ($payment, $status, $context): Payment {
            $payment = Payment::query()
                ->with('booking.venueCluster')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertTransitionAllowed($payment->status, $status);

            $statusBefore = $payment->status;

            if (
                $status === 'paid'
                && ($payment->payment_context ?? 'booking') !== 'vip_subscription'
                && ! in_array($payment->booking?->status, ['pending_payment', 'confirmed', 'checked_in', 'completed'], true)
            ) {
                throw new RuntimeException('Booking không còn ở trạng thái cho phép xác nhận thanh toán. Hãy xử lý hoàn tiền hoặc đối soát riêng.');
            }

            $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];
            $incomingGatewayTxnId = $context['gateway_txn_id'];
            $payment->status = $status;

            if ($incomingGatewayTxnId) {
                $payment->gateway_txn_id = $incomingGatewayTxnId;
            }

            $payment->gateway_response = array_merge($gatewayResponse, [
                'admin_update' => [
                    'source' => $context['source'],
                    'reason' => $context['reason'],
                    'payload' => $context['gateway_response'],
                    'updated_by' => $context['actor_id'],
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

            if ($status === 'paid') {
                $payment->paid_at = $payment->paid_at ?: now();
            }

            $payment->save();

            if ($status === 'paid') {
                if (($payment->payment_context ?? 'booking') === 'vip_subscription') {
                    $this->systemVipService->activateSubscriptionFromPayment($payment);
                } else {
                    if ($payment->booking?->status === 'pending_payment') {
                        $payment->booking()->update(['status' => 'confirmed']);
                    }

                    SlotLock::query()->where('booking_id', $payment->booking_id)->delete();
                    $this->ownerWalletService->creditBookingPayment($payment, [
                        'source' => $context['source'],
                        'transaction_id' => $payment->gateway_txn_id,
                        'reason' => $context['reason'],
                        'admin_id' => $context['actor_id'],
                    ]);
                }
            }

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'admin_payment_status_updated',
                'request_payload' => [
                    'source' => $context['source'],
                    'reason' => $context['reason'],
                    'actor_id' => $context['actor_id'],
                ],
                'response_payload' => $context['gateway_response'],
                'status_before' => $statusBefore,
                'status_after' => $payment->status,
                'gateway_txn_id' => $incomingGatewayTxnId ?: $payment->gateway_txn_id,
            ]);

            return $payment->fresh();
        });
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = [
            'pending' => ['paid', 'failed'],
            'failed' => ['paid'],
            'paid' => [],
            'refunded' => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("Không thể chuyển trạng thái payment từ {$from} sang {$to}.");
        }
    }
}
