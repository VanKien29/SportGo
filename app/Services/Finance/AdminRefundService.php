<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Refund;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminRefundService
{
    public function __construct(
        private readonly OwnerWalletService $wallets,
        private readonly FinanceReceiptService $receipts,
    ) {}

    public function updateStatus(Refund $refund, string $status, array $context): Refund
    {
        return DB::transaction(function () use ($refund, $status, $context): Refund {
            $refund = Refund::query()
                ->with(['payment.booking.venueCluster', 'booking', 'payoutAccount'])
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status === $status && in_array($status, ['completed', 'rejected'], true)) {
                return $refund;
            }

            $this->assertTransitionAllowed($refund->status, $status);
            $statusBefore = $refund->status;
            $refund->status = $status;
            $refund->processed_by = $context['actor_id'] ?? null;
            $refund->processed_at = now();

            if ($status === 'rejected') {
                $refund->status_reason = $context['reason'];
            } else {
                $refund->status_reason = null;
            }

            if ($status === 'completed') {
                $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();

                if ($payment->status !== 'paid') {
                    throw new RuntimeException('Chỉ hoàn tất refund khi payment gốc đang ở trạng thái đã thanh toán.');
                }

                if (empty($context['gateway_refund_txn_id'])) {
                    throw new RuntimeException('Cần nhập mã giao dịch hoàn tiền trước khi xác nhận hoàn tất.');
                }

                $payment->status = 'refunded';
                $payment->save();

                $ledger = $this->wallets->debitRefundedPayment(
                    $payment,
                    (float) $refund->amount,
                    $refund->id,
                    [
                        'source' => $context['source'] ?? 'admin',
                        'reason' => $context['reason'],
                        'admin_id' => $context['actor_id'] ?? null,
                    ],
                );

                $refund->owner_wallet_ledger_id = $ledger->id;
                $refund->admin_confirmed_by = $context['actor_id'] ?? null;
                $refund->admin_confirmed_at = now();
                $refund->gateway_refund_txn_id = $context['gateway_refund_txn_id'] ?? null;

                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => 'admin_refund_completed',
                    'request_payload' => [
                        'refund_id' => $refund->id,
                        'reason' => $context['reason'],
                        'actor_id' => $context['actor_id'] ?? null,
                    ],
                    'response_payload' => [
                        'gateway_refund_txn_id' => $refund->gateway_refund_txn_id,
                        'refund_amount' => $refund->amount,
                    ],
                    'status_before' => 'paid',
                    'status_after' => 'refunded',
                    'gateway_txn_id' => $refund->gateway_refund_txn_id,
                ]);
            }

            $refund->save();

            if ($status === 'completed') {
                $this->receipts->createRefundReceipt($refund, $context['actor_id'] ?? null);
            }

            return $refund->fresh();
        });
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = [
            'pending_confirmation' => ['processing', 'rejected'],
            'pending_owner_confirmation' => ['owner_confirmed', 'owner_rejected', 'rejected'],
            'owner_confirmed' => ['admin_processing', 'processing', 'completed', 'rejected'],
            'owner_rejected' => ['rejected'],
            'admin_processing' => ['completed', 'failed', 'rejected'],
            'processing' => ['completed', 'rejected'],
            'failed' => ['processing', 'rejected'],
            'completed' => [],
            'rejected' => [],
            'cancelled' => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("Không thể chuyển trạng thái refund từ {$from} sang {$to}.");
        }
    }
}
