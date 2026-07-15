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

        $cluster = VenueCluster::query()->where('slug', 'green-sport-ba-dinh')->first();
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
            ['receipt_code' => 'RCPT_FEE_GREEN_202604'],
            [
                'receipt_type' => 'platform_fee',
                'receiptable_type' => VenuePlatformFeeLedger::class,
                'receiptable_id' => $ledger->id,
                'issued_to_user_id' => $cluster->owner_id,
                'issued_by' => $ledger->payment_confirmed_by,
                'title' => 'Phiếu thu phí duy trì Green Sport Ba Đình tháng 04/2026',
                'amount' => $ledger->amount_paid,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $ledger->paid_at ?: now()->subDays(8),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/platform-fee/RCPT_FEE_GREEN_202604.pdf',
                'metadata' => [
                    'period_start' => (string) $ledger->period_start,
                    'period_end' => (string) $ledger->period_end,
                    'court_count' => $ledger->court_count,
                ],
            ],
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

        $request = OwnerWithdrawalRequest::query()->where('request_code', 'WD_OWNER_0002')->first();

        if (! $request) {
            return;
        }

        InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT_WD_OWNER_0002'],
            [
                'receipt_type' => 'withdrawal',
                'receiptable_type' => OwnerWithdrawalRequest::class,
                'receiptable_id' => $request->id,
                'issued_to_user_id' => $request->owner_id,
                'issued_by' => $request->completed_by,
                'title' => 'Phiếu chi rút tiền cho chủ sân Nguyễn Minh Quân',
                'amount' => $request->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $request->completed_at ?: now()->subDays(4),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/withdrawals/RCPT_WD_OWNER_0002.pdf',
                'metadata' => [
                    'request_code' => $request->request_code,
                    'transfer_reference' => $request->transfer_reference,
                ],
            ],
        );
    }

    private function seedPaymentReceipt(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $payment = Payment::query()->where('payment_code', 'PAYMENT_0001')->first();

        if (! $payment) {
            return;
        }

        InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT_PAYMENT_0001'],
            [
                'receipt_type' => 'payment',
                'receiptable_type' => Payment::class,
                'receiptable_id' => $payment->id,
                'issued_to_user_id' => $payment->booking?->customer_id,
                'issued_by' => null,
                'title' => 'Phiếu ghi nhận thanh toán booking BOOKING_0001',
                'amount' => $payment->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $payment->paid_at ?: now(),
                'cancelled_at' => null,
                'cancel_reason' => null,
                'file_path' => 'receipts/payments/RCPT_PAYMENT_0001.pdf',
                'metadata' => [
                    'payment_code' => $payment->payment_code,
                    'booking_code' => $payment->booking?->booking_code,
                ],
            ],
        );
    }
}
