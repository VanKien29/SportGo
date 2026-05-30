<?php

namespace Database\Seeders;

use App\Models\InternalReceipt;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Payment;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class InternalReceiptsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('internal_receipts')) {
            return;
        }

        $this->seedPlatformFeeReceipt();
        $this->seedWithdrawalReceipt();
        $this->seedPaymentReceipt();
    }

    private function seedPlatformFeeReceipt(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers')) {
            return;
        }

        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $ledger = $cluster
            ? VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $cluster->id)
                ->where('period_start', '2026-04-01')
                ->first()
            : null;

        if (! $cluster || ! $ledger) {
            return;
        }

        $receipt = InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT-FEE-202604-CG'],
            [
                'receipt_type' => 'platform_fee',
                'receiptable_type' => VenuePlatformFeeLedger::class,
                'receiptable_id' => $ledger->id,
                'issued_to_user_id' => $cluster->owner_id,
                'issued_by' => $ledger->payment_confirmed_by,
                'title' => 'Phiếu thu phí duy trì SportGo Cầu Giấy tháng 04/2026',
                'amount' => $ledger->amount_paid,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $ledger->paid_at ?: now()->subDays(8),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/platform-fee/RCPT-FEE-202604-CG.pdf',
                'metadata' => [
                    'period_start' => (string) $ledger->period_start,
                    'period_end' => (string) $ledger->period_end,
                    'court_count' => $ledger->court_count,
                ],
            ]
        );

        if (Schema::hasColumn('venue_platform_fee_ledgers', 'internal_receipt_id')) {
            $ledger->update(['internal_receipt_id' => $receipt->id]);
        }
    }

    private function seedWithdrawalReceipt(): void
    {
        if (! Schema::hasTable('owner_withdrawal_requests')) {
            return;
        }

        $request = OwnerWithdrawalRequest::query()->where('request_code', 'WRADMCOMP1')->first();

        if (! $request) {
            return;
        }

        InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT-WD-WRADMCOMP1'],
            [
                'receipt_type' => 'withdrawal',
                'receiptable_type' => OwnerWithdrawalRequest::class,
                'receiptable_id' => $request->id,
                'issued_to_user_id' => $request->owner_id,
                'issued_by' => $request->completed_by,
                'title' => 'Phiếu chi rút tiền cho chủ sân',
                'amount' => $request->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $request->completed_at ?: now()->subDays(4),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/withdrawals/RCPT-WD-WRADMCOMP1.pdf',
                'metadata' => [
                    'request_code' => $request->request_code,
                    'transfer_reference' => $request->transfer_reference,
                ],
            ]
        );
    }

    private function seedPaymentReceipt(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $payment = Payment::query()->where('payment_code', 'PMADMPAID1')->first();

        if (! $payment) {
            return;
        }

        InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT-PAY-PMADMPAID1'],
            [
                'receipt_type' => 'payment',
                'receiptable_type' => Payment::class,
                'receiptable_id' => $payment->id,
                'issued_to_user_id' => $payment->booking?->customer_id,
                'issued_by' => null,
                'title' => 'Phiếu ghi nhận thanh toán booking online',
                'amount' => $payment->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $payment->paid_at ?: now(),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/payments/RCPT-PAY-PMADMPAID1.pdf',
                'metadata' => [
                    'payment_code' => $payment->payment_code,
                    'booking_id' => $payment->booking_id,
                ],
            ]
        );
    }
}
