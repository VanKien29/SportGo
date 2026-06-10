<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\SystemBankAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('bookings')) {
            return;
        }

        $systemBankAccount = SystemBankAccount::query()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        $payments = [
            ['PMADMPAID1', 'BKADMPAID1', 120000, 'full', 'sepay', 'SGDEMO0001', 'paid', now()->subHours(8)],
            ['PMADMREF1', 'BKADMREF1', 150000, 'full', 'sepay', 'SGDEMO0002', 'paid', now()->subDay()],
            ['PMADMREFPROC1', 'BKADMREFPROC1', 180000, 'full', 'sepay', 'SGDEMO0003', 'paid', now()->subDays(2)],
            ['PMADMREFCOMP1', 'BKADMREFCOMP1', 90000, 'full', 'sepay', 'SGDEMO0004', 'refunded', now()->subDays(3)],
            ['PMADMREFFAIL1', 'BKADMREFFAIL1', 125000, 'full', 'sepay', 'SGDEMO0005', 'paid', now()->subDays(4)],
            ['PMADMREFREJ1', 'BKADMREFREJ1', 110000, 'full', 'sepay', 'SGDEMO0006', 'paid', now()->subDays(5)],
            ['PMADMPEND1', 'BKADMPEND1', 30000, 'deposit', 'sepay', null, 'pending', null],
        ];

        foreach ($payments as [$paymentCode, $bookingCode, $amount, $kind, $method, $gatewayTxnId, $status, $paidAt]) {
            $booking = Booking::query()->where('booking_code', $bookingCode)->first();

            if (! $booking) {
                continue;
            }

            Payment::query()->updateOrCreate(
                ['payment_code' => $paymentCode],
                [
                    'booking_id' => $booking->id,
                    'system_bank_account_id' => $systemBankAccount?->id,
                    'amount' => $amount,
                    'payment_kind' => $kind,
                    'method' => $method,
                    'gateway_txn_id' => $gatewayTxnId,
                    'gateway_response' => [
                        'source' => 'seed',
                        'message' => 'Giao dịch mẫu cho màn admin theo dõi thanh toán.',
                    ],
                    'status' => $status,
                    'paid_at' => $paidAt,
                ],
            );
        }
    }
}
