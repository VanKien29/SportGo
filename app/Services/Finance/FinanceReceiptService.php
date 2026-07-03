<?php

namespace App\Services\Finance;

use App\Mail\FinanceReceiptIssuedMail;
use App\Models\InternalReceipt;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Refund;
use App\Models\UserWithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class FinanceReceiptService
{
    public function createRefundReceipt(Refund $refund, ?string $issuedBy): InternalReceipt
    {
        $refund->loadMissing(['booking', 'payment']);

        return $this->updateOrCreateAndNotify(
            [
                'receiptable_type' => Refund::class,
                'receiptable_id' => $refund->id,
            ],
            [
                'receipt_code' => 'INV-RF-'.strtoupper(substr(hash('sha256', $refund->id), 0, 20)),
                'receipt_type' => 'refund',
                'receiptable_type' => Refund::class,
                'receiptable_id' => $refund->id,
                'issued_to_user_id' => $refund->customer_id,
                'issued_by' => $issuedBy,
                'title' => 'Hóa đơn hoàn tiền booking '.$refund->booking?->booking_code,
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

        return $this->updateOrCreateAndNotify(
            [
                'receiptable_type' => OwnerWithdrawalRequest::class,
                'receiptable_id' => $withdrawal->id,
            ],
            [
                'receipt_code' => 'INV-WD-'.$withdrawal->request_code,
                'receipt_type' => 'withdrawal',
                'receiptable_type' => OwnerWithdrawalRequest::class,
                'receiptable_id' => $withdrawal->id,
                'issued_to_user_id' => $withdrawal->owner_id,
                'issued_by' => $issuedBy,
                'title' => 'Hóa đơn chi trả rút tiền '.$withdrawal->request_code,
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

    public function createUserWithdrawalReceipt(UserWithdrawalRequest $withdrawal, ?string $issuedBy): InternalReceipt
    {
        $withdrawal->loadMissing(['payoutAccount', 'user']);
        $requestCode = 'UWD-'.strtoupper(substr(hash('sha256', $withdrawal->id), 0, 10));

        return $this->updateOrCreateAndNotify(
            [
                'receiptable_type' => UserWithdrawalRequest::class,
                'receiptable_id' => $withdrawal->id,
            ],
            [
                'receipt_code' => 'INV-UWD-'.strtoupper(substr(hash('sha256', $withdrawal->id), 0, 18)),
                'receipt_type' => 'withdrawal',
                'receiptable_type' => UserWithdrawalRequest::class,
                'receiptable_id' => $withdrawal->id,
                'issued_to_user_id' => $withdrawal->user_id,
                'issued_by' => $issuedBy,
                'title' => 'Hóa đơn chi trả rút tiền ví người dùng '.$requestCode,
                'amount' => $withdrawal->amount,
                'currency' => 'VND',
                'status' => 'issued',
                'issued_at' => $withdrawal->paid_at ?: now(),
                'metadata' => [
                    'request_code' => $requestCode,
                    'payment_method' => $withdrawal->payment_method,
                    'transfer_reference' => $withdrawal->transfer_reference,
                    'paid_note' => $withdrawal->paid_note,
                    'bank_name' => $withdrawal->payoutAccount?->bank_name,
                    'account_number' => $withdrawal->payoutAccount?->bank_account_number,
                    'account_holder_name' => $withdrawal->payoutAccount?->bank_account_holder,
                ],
            ],
        );
    }

    private function updateOrCreateAndNotify(array $lookup, array $attributes): InternalReceipt
    {
        $existing = InternalReceipt::query()->where($lookup)->first();

        if ($existing) {
            $attributes['metadata'] = $this->mergePersistentMetadata(
                $existing->metadata ?? [],
                $attributes['metadata'] ?? [],
            );
        }

        $receipt = InternalReceipt::query()->updateOrCreate($lookup, $attributes);
        $this->sendMailOnce($receipt);

        return $receipt;
    }

    private function mergePersistentMetadata(array $existing, array $current): array
    {
        foreach (['mail_sent_at', 'mail_to', 'mail_status', 'mail_error', 'receipt_url'] as $key) {
            if (array_key_exists($key, $existing)) {
                $current[$key] = $existing[$key];
            }
        }

        return $current;
    }

    private function sendMailOnce(InternalReceipt $receipt): void
    {
        $receipt->loadMissing('issuedTo');

        $metadata = $receipt->metadata ?? [];
        if (! empty($metadata['mail_sent_at'])) {
            return;
        }

        $recipient = $receipt->issuedTo;
        if (! $recipient?->email) {
            $metadata['mail_status'] = 'skipped_no_email';
            $receipt->forceFill(['metadata' => $metadata])->save();

            return;
        }

        $receiptId = $receipt->getKey();
        $email = $recipient->email;

        DB::afterCommit(function () use ($receiptId, $email): void {
            $receipt = InternalReceipt::query()
                ->with(['issuedTo', 'issuedBy'])
                ->find($receiptId);

            if (! $receipt) {
                return;
            }

            $metadata = $receipt->metadata ?? [];
            if (! empty($metadata['mail_sent_at'])) {
                return;
            }

            $receiptUrl = URL::temporarySignedRoute(
                'invoices.show',
                now()->addDays(30),
                ['receipt' => $receipt->getKey()],
            );

            try {
                Mail::to($email)->send(new FinanceReceiptIssuedMail($receipt, $receiptUrl));

                unset($metadata['mail_error']);
                $metadata['mail_to'] = $email;
                $metadata['mail_status'] = 'sent';
                $metadata['mail_sent_at'] = now()->toDateTimeString();
                $metadata['receipt_url'] = $receiptUrl;
            } catch (Throwable $exception) {
                Log::warning('Không gửi được email hóa đơn tài chính.', [
                    'receipt_id' => $receipt->getKey(),
                    'receipt_code' => $receipt->receipt_code,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);

                $metadata['mail_to'] = $email;
                $metadata['mail_status'] = 'failed';
                $metadata['mail_error'] = $exception->getMessage();
                $metadata['receipt_url'] = $receiptUrl;
            }

            $receipt->forceFill(['metadata' => $metadata])->save();
        });
    }
}
