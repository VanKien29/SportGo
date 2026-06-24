<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;

class FakeOnlinePaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('username', 'user')->first();
        $owner = User::query()->where('username', 'owner')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $court = VenueCourt::query()->where('name', 'Sân cầu lông A1')->first();
        $systemBank = SystemBankAccount::query()->where('status', 'active')->orderByDesc('is_default')->first();

        if (! $customer || ! $owner || ! $cluster || ! $court) {
            $this->command->error('Thiếu dữ liệu cơ bản (user, owner, cluster, court). Chạy DatabaseSeeder trước.');
            return;
        }

        // Ensure owner wallet exists
        $wallet = OwnerWallet::query()->firstOrCreate(
            ['owner_id' => $owner->id, 'venue_cluster_id' => $cluster->id],
            ['available_balance' => 2000000, 'pending_withdrawal_balance' => 0, 'total_earned' => 3500000, 'total_withdrawn' => 700000],
        );

        $fakePayments = [
            ['BKFAKE-CK01', 'PMFAKE-CK01', 150000, 'full', 'sepay', 'SGFAKE001', now()->subHours(3)],
            ['BKFAKE-CK02', 'PMFAKE-CK02', 200000, 'full', 'bank_transfer', 'SGFAKE002', now()->subHours(6)],
            ['BKFAKE-CK03', 'PMFAKE-CK03', 85000, 'deposit', 'sepay', 'SGFAKE003', now()->subHours(12)],
        ];

        $runningBalance = (float) $wallet->available_balance;

        foreach ($fakePayments as [$bookingCode, $paymentCode, $amount, $kind, $method, $txnId, $paidAt]) {
            // Create booking
            $booking = Booking::query()->updateOrCreate(
                ['booking_code' => $bookingCode],
                [
                    'customer_id' => $customer->id,
                    'venue_court_id' => $court->id,
                    'requested_venue_court_id' => $court->id,
                    'venue_cluster_id' => $cluster->id,
                    'booking_date' => $paidAt->toDateString(),
                    'start_time' => '08:00:00',
                    'end_time' => '09:00:00',
                    'duration_minutes' => 60,
                    'total_price' => $amount,
                    'payment_option' => 'full_payment',
                    'required_payment_amount' => $amount,
                    'source' => 'online',
                    'booking_type' => 'single',
                    'status' => 'confirmed',
                    'created_by' => $customer->id,
                ],
            );

            // Create payment
            $payment = Payment::query()->updateOrCreate(
                ['payment_code' => $paymentCode],
                [
                    'booking_id' => $booking->id,
                    'system_bank_account_id' => $systemBank?->id,
                    'amount' => $amount,
                    'wallet_amount' => 0,
                    'gateway_amount' => $amount,
                    'payment_kind' => $kind,
                    'method' => $method,
                    'gateway_txn_id' => $txnId,
                    'gateway_response' => ['source' => 'seed', 'message' => 'Fake online payment for testing.'],
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                ],
            );

            // Create payment log
            PaymentLog::query()->updateOrCreate(
                ['payment_id' => $payment->id, 'event_type' => 'sepay_ipn_paid'],
                [
                    'request_payload' => ['source' => 'fake_seed'],
                    'response_payload' => ['message' => 'IPN confirmed payment.'],
                    'status_before' => 'pending',
                    'status_after' => 'paid',
                    'gateway_txn_id' => $txnId,
                ],
            );

            // Create wallet credit ledger
            $balanceBefore = $runningBalance;
            $balanceAfter = $balanceBefore + $amount;
            $runningBalance = $balanceAfter;

            OwnerWalletLedger::query()->updateOrCreate(
                ['owner_wallet_id' => $wallet->id, 'payment_id' => $payment->id, 'type' => 'credit'],
                [
                    'owner_id' => $owner->id,
                    'venue_cluster_id' => $cluster->id,
                    'booking_id' => $booking->id,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'status' => 'completed',
                    'reference_code' => $txnId,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                    'transaction_code' => 'OWC-FAKE-' . $paymentCode,
                    'description' => 'Cộng tiền booking ' . $bookingCode . ' vào ví chủ sân.',
                    'note' => 'Fake seed data.',
                    'metadata' => ['source' => 'fake_seed', 'booking_code' => $bookingCode, 'payment_code' => $paymentCode],
                ],
            );

            $this->command->info("✅ {$paymentCode} ({$method}) — " . number_format($amount) . "đ — Ví: " . number_format($balanceBefore) . " + " . number_format($amount) . " = " . number_format($balanceAfter));
        }

        // Update wallet final balance
        $wallet->update([
            'available_balance' => $runningBalance,
            'total_earned' => (float) $wallet->total_earned + 150000 + 200000 + 85000,
        ]);

        $this->command->info("🏦 Số dư ví chủ sân sau seed: " . number_format($runningBalance) . "đ");
    }
}
