<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentLogsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payment_logs') || ! Schema::hasTable('payments')) {
            return;
        }

        // [payment_code, event_type, status_before, status_after, gateway_txn_id, error_code]
        $logs = [
            // Confirmed payments — create + ipn paid
            ['PM-CONF-01', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-01', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-001', null],
            ['PM-CONF-02', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-02', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-002', null],
            ['PM-CONF-03', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-03', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-003', null],
            ['PM-CONF-04', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-04', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-004', null],
            ['PM-CONF-05', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-05', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-005', null],
            ['PM-CONF-06', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-06', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-006', null],
            ['PM-CONF-07', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-07', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-007', null],
            ['PM-CONF-08', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CONF-08', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CONF-008', null],

            // Pending payments — only create
            ['PM-PEND-01', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-PEND-02', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-PEND-03', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-PEND-04', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-PEND-05', 'sepay_create_payment', 'pending', 'pending', null, null],

            // Cancelled payments — create + ipn paid (paid before cancel)
            ['PM-CANC-01', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-01', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-001', null],
            ['PM-CANC-02', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-02', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-002', null],
            ['PM-CANC-03', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-03', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-003', null],
            ['PM-CANC-04', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-04', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-004', null],
            ['PM-CANC-05', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-05', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-005', null],
            ['PM-CANC-06', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-06', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-006', null],
            ['PM-CANC-07', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-07', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-007', null],
            ['PM-CANC-08', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-08', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-008', null],
            ['PM-CANC-09', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-09', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-009', null],
            ['PM-CANC-10', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-CANC-10', 'sepay_ipn_paid', 'pending', 'paid', 'SG-CANC-010', null],

            // Expired — create + failed
            ['PM-EXP-01', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-EXP-01', 'payment_expired', 'pending', 'failed', null, 'TIMEOUT'],
            ['PM-EXP-02', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-EXP-02', 'payment_expired', 'pending', 'failed', null, 'TIMEOUT'],

            // No show — create + paid
            ['PM-NOSHOW-01', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-NOSHOW-01', 'sepay_ipn_paid', 'pending', 'paid', 'SG-NOSHOW-001', null],
            ['PM-NOSHOW-02', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PM-NOSHOW-02', 'sepay_ipn_paid', 'pending', 'paid', 'SG-NOSHOW-002', null],
        ];

        foreach ($logs as [$paymentCode, $eventType, $before, $after, $txnId, $errorCode]) {
            $payment = Payment::query()->where('payment_code', $paymentCode)->first();

            if (! $payment) {
                continue;
            }

            PaymentLog::query()->updateOrCreate(
                [
                    'payment_id' => $payment->id,
                    'event_type' => $eventType,
                    'gateway_txn_id' => $txnId,
                ],
                [
                    'request_payload' => ['source' => 'seed'],
                    'response_payload' => ['message' => 'Log thanh toán mẫu'],
                    'status_before' => $before,
                    'status_after' => $after,
                    'error_code' => $errorCode,
                    'error_message' => $errorCode === 'TIMEOUT' ? 'Quá thời gian thanh toán cho phép.' : null,
                ],
            );
        }
    }
}
