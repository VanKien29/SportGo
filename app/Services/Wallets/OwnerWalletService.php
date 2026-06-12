<?php

namespace App\Services\Wallets;

use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OwnerWalletService
{
    public function creditBookingPayment(Payment $payment, array $metadata = []): OwnerWalletLedger
    {
        return DB::transaction(function () use ($payment, $metadata): OwnerWalletLedger {
            $payment = Payment::query()
                ->with('booking.venueCluster')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'paid') {
                throw new RuntimeException('Chỉ payment đã thanh toán mới được cộng vào ví chủ sân.');
            }

            $booking = $payment->booking;
            $cluster = $booking?->venueCluster;

            if (! $booking || ! $cluster || ! $cluster->owner_id) {
                throw new RuntimeException('Không xác định được chủ sân để ghi nhận tiền vào ví.');
            }

            if ($booking->payment_option === 'no_prepay') {
                throw new RuntimeException('Thanh toán trực tiếp tại sân không được ghi nhận vào ví chủ sân.');
            }

            $existingLedger = OwnerWalletLedger::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'credit')
                ->first();

            if ($existingLedger) {
                return $existingLedger;
            }

            $wallet = OwnerWallet::query()->firstOrCreate(
                [
                    'owner_id' => $cluster->owner_id,
                    'venue_cluster_id' => $cluster->id
                ],
                [
                    'available_balance' => 0,
                    'pending_withdrawal_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ],
            );

            $wallet = OwnerWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (float) $payment->amount;
            $balanceBefore = (float) $wallet->available_balance;
            $balanceAfter = $balanceBefore + $amount;

            $wallet->available_balance = $balanceAfter;
            $wallet->total_earned = (float) $wallet->total_earned + $amount;
            $wallet->save();

            return OwnerWalletLedger::query()->create([
                'owner_wallet_id' => $wallet->id,
                'owner_id' => $cluster->owner_id,
                'venue_cluster_id' => $cluster->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'type' => 'credit',
                'direction' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'reference_code' => ($metadata['transaction_id'] ?? null) ?: $payment->gateway_txn_id ?: $payment->payment_code,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
                'transaction_code' => $this->transactionCode($payment),
                'description' => 'Hệ thống thu hộ thanh toán booking '.$booking->booking_code.'.',
                'note' => 'Cộng tiền booking '.$booking->booking_code.' vào ví chủ sân.',
                'metadata' => array_merge([
                    'source' => 'booking_payment',
                    'booking_code' => $booking->booking_code,
                    'customer_id' => $booking->customer_id,
                    'payment_code' => $payment->payment_code,
                    'payment_method' => $payment->method,
                ], $metadata),
            ]);
        });
    }

    public function debitRefundedPayment(Payment $payment, float $amount, string $refundId, array $metadata = []): OwnerWalletLedger
    {
        return DB::transaction(function () use ($payment, $amount, $refundId, $metadata): OwnerWalletLedger {
            $payment = Payment::query()
                ->with('booking.venueCluster')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'refunded') {
                throw new RuntimeException('Chỉ payment đã hoàn tiền mới được trừ khỏi ví chủ sân.');
            }

            $booking = $payment->booking;
            $cluster = $booking?->venueCluster;

            if (! $booking || ! $cluster || ! $cluster->owner_id) {
                throw new RuntimeException('Không xác định được chủ sân để ghi nhận hoàn tiền.');
            }

            $existingLedger = OwnerWalletLedger::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'debit')
                ->first();

            if ($existingLedger) {
                return $existingLedger;
            }

            $creditLedger = OwnerWalletLedger::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'credit')
                ->first();

            if (! $creditLedger) {
                throw new RuntimeException('Payment chưa từng được cộng vào ví chủ sân nên không thể ghi nhận hoàn tiền.');
            }

            $wallet = OwnerWallet::query()
                ->where('owner_id', $cluster->owner_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) $wallet->available_balance;

            if ($amount <= 0 || $amount > (float) $payment->amount) {
                throw new RuntimeException('Số tiền hoàn không hợp lệ so với payment gốc.');
            }

            if ($amount > $balanceBefore) {
                throw new RuntimeException('Số dư online còn lại của chủ sân không đủ để hoàn tiền.');
            }

            $balanceAfter = $balanceBefore - $amount;

            $wallet->available_balance = $balanceAfter;
            $wallet->save();

            return OwnerWalletLedger::query()->create([
                'owner_wallet_id' => $wallet->id,
                'owner_id' => $cluster->owner_id,
                'venue_cluster_id' => $cluster->id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'type' => 'debit',
                'direction' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'reference_code' => $payment->gateway_txn_id ?: $payment->payment_code,
                'reference_type' => 'refund',
                'reference_id' => $refundId,
                'transaction_code' => 'OWD-'.substr(hash('sha256', $refundId), 0, 32),
                'description' => 'Hoàn tiền booking '.$booking->booking_code.', giảm số dư ví chủ sân.',
                'note' => $metadata['reason'] ?? 'Admin xác nhận payment đã hoàn tiền.',
                'metadata' => array_merge([
                    'source' => 'payment_refund',
                    'booking_code' => $booking->booking_code,
                    'payment_code' => $payment->payment_code,
                ], $metadata),
            ]);
        });
    }

    public function holdWithdrawal(OwnerWithdrawalRequest $withdrawal, array $metadata = []): OwnerWalletLedger
    {
        return DB::transaction(function () use ($withdrawal, $metadata): OwnerWalletLedger {
            $withdrawal = OwnerWithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $existing = $this->withdrawalLedger($withdrawal, 'hold');

            if ($existing) {
                return $existing;
            }

            $wallet = OwnerWallet::query()->whereKey($withdrawal->owner_wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $withdrawal->amount;
            $balanceBefore = (float) $wallet->available_balance;

            if ($amount <= 0 || $amount > $balanceBefore) {
                throw new RuntimeException('Số tiền rút vượt quá doanh thu online còn lại.');
            }

            $wallet->available_balance = $balanceBefore - $amount;
            $wallet->pending_withdrawal_balance = (float) $wallet->pending_withdrawal_balance + $amount;
            $wallet->save();

            return $this->createWithdrawalLedger(
                $withdrawal,
                'hold',
                'debit',
                $balanceBefore,
                (float) $wallet->available_balance,
                'Giữ tiền cho yêu cầu rút '.$withdrawal->request_code.'.',
                $metadata,
            );
        });
    }

    public function releaseWithdrawal(OwnerWithdrawalRequest $withdrawal, array $metadata = []): OwnerWalletLedger
    {
        return DB::transaction(function () use ($withdrawal, $metadata): OwnerWalletLedger {
            $withdrawal = OwnerWithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $existing = $this->withdrawalLedger($withdrawal, 'release');

            if ($existing) {
                return $existing;
            }

            if (! $this->withdrawalLedger($withdrawal, 'hold')) {
                throw new RuntimeException('Yêu cầu rút tiền chưa được giữ số dư.');
            }

            $wallet = OwnerWallet::query()->whereKey($withdrawal->owner_wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $withdrawal->amount;

            if ($amount > (float) $wallet->pending_withdrawal_balance) {
                throw new RuntimeException('Số tiền đang giữ không đủ để hoàn trả yêu cầu rút.');
            }

            $balanceBefore = (float) $wallet->available_balance;
            $wallet->available_balance = $balanceBefore + $amount;
            $wallet->pending_withdrawal_balance = (float) $wallet->pending_withdrawal_balance - $amount;
            $wallet->save();

            return $this->createWithdrawalLedger(
                $withdrawal,
                'release',
                'credit',
                $balanceBefore,
                (float) $wallet->available_balance,
                'Hoàn trả số dư do yêu cầu rút '.$withdrawal->request_code.' bị từ chối.',
                $metadata,
            );
        });
    }

    public function completeWithdrawal(OwnerWithdrawalRequest $withdrawal, array $metadata = []): OwnerWalletLedger
    {
        return DB::transaction(function () use ($withdrawal, $metadata): OwnerWalletLedger {
            $withdrawal = OwnerWithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $existing = $this->withdrawalLedger($withdrawal, 'debit');

            if ($existing) {
                return $existing;
            }

            if (! $this->withdrawalLedger($withdrawal, 'hold')) {
                throw new RuntimeException('Yêu cầu rút tiền chưa được duyệt và giữ số dư.');
            }

            $wallet = OwnerWallet::query()->whereKey($withdrawal->owner_wallet_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $withdrawal->amount;

            if ($amount > (float) $wallet->pending_withdrawal_balance) {
                throw new RuntimeException('Số tiền đang giữ không đủ để hoàn tất yêu cầu rút.');
            }

            $balance = (float) $wallet->available_balance;
            $wallet->pending_withdrawal_balance = (float) $wallet->pending_withdrawal_balance - $amount;
            $wallet->total_withdrawn = (float) $wallet->total_withdrawn + $amount;
            $wallet->save();

            return $this->createWithdrawalLedger(
                $withdrawal,
                'debit',
                'debit',
                $balance,
                $balance,
                'Đã chi trả yêu cầu rút '.$withdrawal->request_code.'.',
                $metadata,
            );
        });
    }

    private function withdrawalLedger(OwnerWithdrawalRequest $withdrawal, string $type): ?OwnerWalletLedger
    {
        return OwnerWalletLedger::query()
            ->where('reference_type', 'withdrawal')
            ->where('reference_id', $withdrawal->id)
            ->where('type', $type)
            ->first();
    }

    private function createWithdrawalLedger(
        OwnerWithdrawalRequest $withdrawal,
        string $type,
        string $direction,
        float $balanceBefore,
        float $balanceAfter,
        string $description,
        array $metadata,
    ): OwnerWalletLedger {
        $prefix = ['hold' => 'OWH', 'release' => 'OWR', 'debit' => 'OWX'][$type];

        return OwnerWalletLedger::query()->create([
            'owner_wallet_id' => $withdrawal->owner_wallet_id,
            'owner_id' => $withdrawal->owner_id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $withdrawal->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'reference_code' => $withdrawal->request_code,
            'reference_type' => 'withdrawal',
            'reference_id' => $withdrawal->id,
            'transaction_code' => $prefix.'-'.substr(hash('sha256', $withdrawal->id), 0, 32),
            'description' => $description,
            'note' => $metadata['reason'] ?? null,
            'metadata' => array_merge(['request_code' => $withdrawal->request_code], $metadata),
        ]);
    }

    private function transactionCode(Payment $payment): string
    {
        return 'OWC-'.substr(hash('sha256', $payment->id), 0, 32);
    }
}
