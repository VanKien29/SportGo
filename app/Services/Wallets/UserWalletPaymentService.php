<?php

namespace App\Services\Wallets;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\UserWalletLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UserWalletPaymentService
{
    public function payBooking(Booking $booking, User $customer, float $amount): Payment
    {
        return DB::transaction(function () use ($booking, $customer, $amount): Payment {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('Số tiền thanh toán bằng ví không hợp lệ.');
            }

            $wallet = UserWallet::query()
                ->firstOrCreate(
                    ['user_id' => $customer->id],
                    ['balance' => 0, 'locked_balance' => 0, 'status' => 'active'],
                );
            $wallet = UserWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($wallet->status !== 'active') {
                throw new RuntimeException('Ví của bạn đang bị khóa hoặc tạm ngưng.');
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            if ($balanceBefore < $amount) {
                throw new RuntimeException('Số dư ví không đủ để thanh toán booking này.');
            }

            $existing = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('method', 'wallet')
                ->where('status', 'paid')
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $balanceAfter = round($balanceBefore - $amount, 2);
            $wallet->forceFill(['balance' => $balanceAfter])->save();

            $payment = Payment::query()->create([
                'payment_code' => $this->paymentCode(),
                'payment_context' => 'booking',
                'booking_id' => $booking->id,
                'amount' => $amount,
                'wallet_amount' => $amount,
                'gateway_amount' => 0,
                'user_wallet_id' => $wallet->id,
                'payment_kind' => $booking->payment_option === 'deposit' ? 'deposit' : 'full',
                'method' => 'wallet',
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_response' => [
                    'source' => 'user_wallet',
                    'customer_id' => $customer->id,
                ],
            ]);

            $ledger = UserWalletLedger::query()->create([
                'user_wallet_id' => $wallet->id,
                'transaction_code' => $this->ledgerCode(),
                'type' => 'payment',
                'direction' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'payment',
                'reference_id' => (string) $payment->id,
                'status' => 'completed',
                'note' => 'Thanh toán booking '.$booking->booking_code.' bằng ví SportGo.',
                'created_by' => $customer->id,
            ]);

            $payment->forceFill(['user_wallet_ledger_id' => $ledger->id])->save();

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'wallet_payment_completed',
                'request_payload' => [
                    'booking_code' => $booking->booking_code,
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
                'status_before' => null,
                'status_after' => 'paid',
            ]);

            return $payment->fresh();
        });
    }

    private function paymentCode(): string
    {
        do {
            $code = 'PM'.Str::upper(Str::random(10));
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }

    private function ledgerCode(): string
    {
        do {
            $code = 'UWP-'.Str::upper(Str::random(28));
        } while (UserWalletLedger::query()->where('transaction_code', $code)->exists());

        return $code;
    }
}
