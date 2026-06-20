<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Refund;
use App\Services\Customers\WalkInCustomerService;
use App\Services\Policies\RefundPolicyEvaluator;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminRefundService
{
    public function __construct(
        private readonly OwnerWalletService $wallets,
        private readonly FinanceReceiptService $receipts,
        private readonly RefundPolicyEvaluator $refundPolicies,
        private readonly WalkInCustomerService $walkInCustomers,
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

            if (in_array($status, ['processing', 'completed'], true)) {
                $this->refundPolicies->assertCompliant(
                    $refund,
                    $context['actor_id'] ?? null,
                    ($context['actor_id'] ?? null) ? 'admin' : 'system',
                );
            }

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

                if (! in_array($payment->status, ['paid', 'refunded'], true)) {
                    throw new RuntimeException('Chỉ hoàn tất refund khi payment gốc đang ở trạng thái đã thanh toán.');
                }

                $paymentStatusBefore = $payment->status;
                $refundedAmountBefore = (float) Refund::query()
                    ->where('payment_id', $payment->id)
                    ->where('status', 'completed')
                    ->whereKeyNot($refund->id)
                    ->sum('amount');
                $refundedAmountAfter = $refundedAmountBefore + (float) $refund->amount;
                $payment->status = $refundedAmountAfter + 0.01 >= (float) $payment->amount ? 'refunded' : 'paid';
                $payment->save();

                $ownerLedger = $this->debitOwnerWalletIfNeeded($payment, $refund, $context);
                $refund->owner_wallet_ledger_id = $ownerLedger?->id;

                $refund->refund_destination = 'user_wallet';
                $walletResult = $this->creditUserWallet($refund, $payment, $context);
                $refund->user_wallet_id = $walletResult['wallet_id'];
                $refund->user_wallet_ledger_id = $walletResult['ledger_id'];

                $refund->admin_confirmed_by = $context['actor_id'] ?? null;
                $refund->admin_confirmed_at = now();
                $refund->gateway_refund_txn_id = $context['gateway_refund_txn_id']
                    ?? 'USER-WALLET-'.$refund->id;

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
                    'status_before' => $paymentStatusBefore,
                    'status_after' => $payment->status,
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
            'pending_confirmation' => ['completed', 'processing', 'rejected'],
            'pending_owner_confirmation' => [],
            'owner_confirmed' => ['completed', 'admin_processing', 'processing', 'rejected'],
            'owner_rejected' => [],
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

    private function debitOwnerWalletIfNeeded(Payment $payment, Refund $refund, array $context): ?object
    {
        $hasOwnerCredit = DB::table('owner_wallet_ledgers')
            ->where('payment_id', $payment->id)
            ->where('type', 'credit')
            ->exists();

        if (! $hasOwnerCredit) {
            return null;
        }

        return $this->wallets->debitRefundedPayment(
            $payment,
            (float) $refund->amount,
            $refund->id,
            [
                'source' => $context['source'] ?? 'admin',
                'reason' => $context['reason'],
                'admin_id' => $context['actor_id'] ?? null,
            ],
        );
    }

    private function creditUserWallet(Refund $refund, Payment $payment, array $context): array
    {
        if ($refund->user_wallet_ledger_id) {
            return [
                'wallet_id' => $refund->user_wallet_id,
                'ledger_id' => $refund->user_wallet_ledger_id,
            ];
        }

        $customerId = $refund->customer_id
            ?: $refund->booking?->customer_id
            ?: $payment->booking?->customer_id;

        if (! $customerId) {
            $customerId = $this->resolveWalkInCustomer($refund, $payment);
        }

        $wallet = $this->walkInCustomers->ensureWallet($customerId);

        if (($wallet->status ?? 'active') !== 'active') {
            throw new RuntimeException('Ví của khách hàng đang bị khóa hoặc tạm ngưng.');
        }

        $amount = (float) $refund->amount;
        $balanceBefore = (float) $wallet->balance;
        $balanceAfter = $balanceBefore + $amount;
        $ledgerId = (string) Str::uuid();

        DB::table('user_wallets')
            ->where('id', $wallet->id)
            ->update([
                'balance' => $balanceAfter,
                'updated_at' => now(),
            ]);

        DB::table('user_wallet_ledgers')->insert([
            'id' => $ledgerId,
            'user_wallet_id' => $wallet->id,
            'transaction_code' => 'UWR-'.substr(hash('sha256', $refund->id), 0, 32),
            'type' => 'refund',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => 'refund',
            'reference_id' => $refund->id,
            'status' => 'completed',
            'note' => $context['reason'] ?? 'Hoàn tiền booking vào ví SportGo.',
            'created_by' => $context['actor_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'wallet_id' => $wallet->id,
            'ledger_id' => $ledgerId,
        ];
    }

    private function resolveWalkInCustomer(Refund $refund, Payment $payment): string
    {
        $booking = $refund->booking ?: $payment->booking;
        $user = $this->walkInCustomers->resolveOrCreate(
            null,
            $booking?->walk_in_name,
            $booking?->walk_in_phone,
        );

        if ($booking && ! $booking->customer_id) {
            $booking->forceFill(['customer_id' => $user->id])->save();
        }

        $refund->customer_id = $user->id;

        return $user->id;
    }
}
