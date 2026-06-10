<?php

namespace App\Services\Finance;

use App\Models\InternalReceipt;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Refund;
use App\Models\VenuePlatformFeeLedger;

class FinanceReceiptService
{
    public function createRefundReceipt(Refund $refund, ?string $issuedBy): InternalReceipt
    {
        $refund->loadMissing(['booking', 'payment']);

        return InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT-RF-'.strtoupper(substr(hash('sha256', $refund->id), 0, 20))],
            [
                'receipt_type' => 'refund',
                'receiptable_type' => Refund::class,
                'receiptable_id' => $refund->id,
                'issued_to_user_id' => $refund->customer_id,
                'issued_by' => $issuedBy,
                'title' => 'Phiếu hoàn tiền booking '.$refund->booking?->booking_code,
                'amount' => $refund->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $refund->admin_confirmed_at ?: now(),
                'metadata' => [
                    'booking_code' => $refund->booking?->booking_code,
                    'payment_code' => $refund->payment?->payment_code,
                    'refund_destination' => $refund->refund_destination,
                    'gateway_refund_txn_id' => $refund->gateway_refund_txn_id,
                ],
            ],
        );
    }

    public function createWithdrawalReceipt(OwnerWithdrawalRequest $withdrawal, ?string $issuedBy): InternalReceipt
    {
        $withdrawal->loadMissing('bankAccount');

        return InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => 'RCPT-WD-'.$withdrawal->request_code],
            [
                'receipt_type' => 'withdrawal',
                'receiptable_type' => OwnerWithdrawalRequest::class,
                'receiptable_id' => $withdrawal->id,
                'issued_to_user_id' => $withdrawal->owner_id,
                'issued_by' => $issuedBy,
                'title' => 'Phiếu chi rút tiền '.$withdrawal->request_code,
                'amount' => $withdrawal->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $withdrawal->completed_at ?: now(),
                'metadata' => [
                    'request_code' => $withdrawal->request_code,
                    'transfer_reference' => $withdrawal->transfer_reference,
                    'bank_name' => $withdrawal->bankAccount?->bank_name,
                    'bank_code' => $withdrawal->bankAccount?->bank_code,
                    'account_number' => $withdrawal->bankAccount?->account_number,
                    'account_holder_name' => $withdrawal->bankAccount?->account_holder_name,
                ],
            ],
        );
    }

    public function createPlatformFeeReceipt(VenuePlatformFeeLedger $ledger, ?string $issuedBy): InternalReceipt
    {
        $ledger->loadMissing('venueCluster');

        $code = 'RCPT-FEE-'.strtoupper(substr(hash('sha256', $ledger->id), 0, 20));

        return InternalReceipt::query()->updateOrCreate(
            ['receipt_code' => $code],
            [
                'receipt_type' => 'platform_fee',
                'receiptable_type' => VenuePlatformFeeLedger::class,
                'receiptable_id' => $ledger->id,
                'issued_to_user_id' => $ledger->venueCluster?->owner_id,
                'issued_by' => $issuedBy,
                'title' => 'Phiếu thu phí duy trì ' . ($ledger->venueCluster?->name ?? '') . ' kỳ ' . $ledger->period_start->format('d/m/Y') . ' - ' . $ledger->period_end->format('d/m/Y'),
                'amount' => $ledger->amount_paid,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $ledger->paid_at ?: now(),
                'metadata' => [
                    'period_start' => (string) $ledger->period_start,
                    'period_end' => (string) $ledger->period_end,
                    'court_count' => $ledger->court_count,
                    'tier_name' => $ledger->tier?->name,
                ],
            ],
        );
    }
}
