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

        // [payment_code, booking_code, amount, kind, method, gateway_txn_id, status, paid_at_offset_description]
        $payments = [
            // ===== Confirmed bookings — paid =====
            ['PM-CONF-01', 'BK-CONF-01', 120000, 'full',    'sepay',         'SG-CONF-001', 'paid', now()->subDay()->subHours(2)],
            ['PM-CONF-02', 'BK-CONF-02', 130000, 'full',    'sepay',         'SG-CONF-002', 'paid', now()->subDay()->subHours(3)],
            ['PM-CONF-03', 'BK-CONF-03', 45000,  'deposit', 'sepay',         'SG-CONF-003', 'paid', now()->subHours(6)],
            ['PM-CONF-04', 'BK-CONF-04', 160000, 'full',    'sepay',         'SG-CONF-004', 'paid', now()->subHours(4)],
            ['PM-CONF-05', 'BK-CONF-05', 120000, 'full',    'sepay',         'SG-CONF-005', 'paid', now()->subHours(8)],
            ['PM-CONF-06', 'BK-CONF-06', 39000,  'deposit', 'sepay',         'SG-CONF-006', 'paid', now()->subHours(10)],
            ['PM-CONF-07', 'BK-CONF-07', 150000, 'full',    'bank_transfer', 'SG-CONF-007', 'paid', now()->subHours(12)],
            ['PM-CONF-08', 'BK-CONF-08', 120000, 'full',    'sepay',         'SG-CONF-008', 'paid', now()->subHours(14)],

            // ===== Pending payment bookings — pending =====
            ['PM-PEND-01', 'BK-PEND-01', 150000, 'full',    'sepay', null, 'pending', null],
            ['PM-PEND-02', 'BK-PEND-02', 39000,  'deposit', 'sepay', null, 'pending', null],
            ['PM-PEND-03', 'BK-PEND-03', 160000, 'full',    'sepay', null, 'pending', null],
            ['PM-PEND-04', 'BK-PEND-04', 36000,  'deposit', 'sepay', null, 'pending', null],
            ['PM-PEND-05', 'BK-PEND-05', 150000, 'full',    'sepay', null, 'pending', null],

            // ===== Cancelled bookings — paid (trước khi hủy) =====
            ['PM-CANC-01', 'BK-CANC-01', 120000, 'full',    'sepay',         'SG-CANC-001', 'paid', now()->subDays(4)],
            ['PM-CANC-02', 'BK-CANC-02', 130000, 'full',    'sepay',         'SG-CANC-002', 'paid', now()->subDays(4)],
            ['PM-CANC-03', 'BK-CANC-03', 150000, 'full',    'sepay',         'SG-CANC-003', 'paid', now()->subDays(5)],
            ['PM-CANC-04', 'BK-CANC-04', 160000, 'full',    'sepay',         'SG-CANC-004', 'refunded', now()->subDays(6)],
            ['PM-CANC-05', 'BK-CANC-05', 120000, 'full',    'sepay',         'SG-CANC-005', 'paid', now()->subDays(5)],
            ['PM-CANC-06', 'BK-CANC-06', 39000,  'deposit', 'sepay',         'SG-CANC-006', 'paid', now()->subDays(3)],
            ['PM-CANC-07', 'BK-CANC-07', 150000, 'full',    'sepay',         'SG-CANC-007', 'paid', now()->subDays(3)],
            ['PM-CANC-08', 'BK-CANC-08', 36000,  'deposit', 'sepay',         'SG-CANC-008', 'refunded', now()->subDays(7)],
            ['PM-CANC-09', 'BK-CANC-09', 160000, 'full',    'bank_transfer', 'SG-CANC-009', 'paid', now()->subDays(4)],
            ['PM-CANC-10', 'BK-CANC-10', 130000, 'full',    'sepay',         'SG-CANC-010', 'paid', now()->subDays(6)],

            // ===== Expired bookings — failed (hết hạn) =====
            ['PM-EXP-01', 'BK-EXP-01', 160000, 'full',    'sepay', null, 'failed', null],
            ['PM-EXP-02', 'BK-EXP-02', 36000,  'deposit', 'sepay', null, 'failed', null],

            // ===== No show — paid =====
            ['PM-NOSHOW-01', 'BK-NOSHOW-01', 130000, 'full', 'sepay', 'SG-NOSHOW-001', 'paid', now()->subDay()->subHours(5)],
            ['PM-NOSHOW-02', 'BK-NOSHOW-02', 150000, 'full', 'sepay', 'SG-NOSHOW-002', 'paid', now()->subDay()->subHours(6)],
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
                    'gateway_response' => $gatewayTxnId
                        ? ['source' => 'seed', 'message' => 'Giao dịch mẫu cho test.']
                        : null,
                    'status' => $status,
                    'paid_at' => $paidAt,
                ],
            );
        }
    }
}
