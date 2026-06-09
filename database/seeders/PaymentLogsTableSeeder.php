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

        $logs = [
            ['PMADMPAID1', 'sepay_create_payment', 'pending', 'pending', null, null],
            ['PMADMPAID1', 'sepay_ipn_paid', 'pending', 'paid', 'SGDEMO0001', null],
            ['PMADMREF1', 'sepay_ipn_paid', 'pending', 'paid', 'SGDEMO0002', null],
            ['PMADMPEND1', 'sepay_create_payment', 'pending', 'pending', null, null],
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
                    'error_message' => null,
                ],
            );
        }
    }
}
