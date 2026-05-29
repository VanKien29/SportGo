<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SlotLock;
use App\Models\SystemBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SepayPaymentService
{
    public function createPayment(Booking $booking): array
    {
        $account = $this->resolveSystemBankAccount();

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('method', 'sepay')
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment) {
            $payment = Payment::query()->create([
                'payment_code' => 'PM'.Str::upper(Str::random(10)),
                'booking_id' => $booking->id,
                'system_bank_account_id' => $account->id,
                'amount' => $booking->required_payment_amount,
                'payment_kind' => $booking->payment_option === 'full_payment' ? 'full' : 'deposit',
                'method' => 'sepay',
                'status' => 'pending',
            ]);
        } elseif (! $payment->system_bank_account_id) {
            $payment->update([
                'system_bank_account_id' => $account->id,
            ]);
        }

        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'sepay_create_payment',
            'request_payload' => [
                'system_bank_account_id' => $account->id,
                'transfer_content' => $payment->payment_code,
                'qr_url' => $this->qrUrl($payment, $account),
            ],
            'status_before' => $payment->status,
            'status_after' => $payment->status,
        ]);

        return [
            'payment' => $payment->fresh(),
            'payment_account' => $account,
            'system_bank_account' => $account,
            'transfer_content' => $payment->payment_code,
            'qr_url' => $this->qrUrl($payment, $account),
        ];
    }

    public function handleIpn(array $payload): array
    {
        $normalized = $this->normalizeIpnPayload($payload);
        $payment = $this->findPaymentFromIpn($normalized);

        if (! $payment) {
            return [
                'success' => false,
                'error_code' => 'payment_not_found',
                'message' => 'Không tìm thấy payment tương ứng với giao dịch SePay.',
            ];
        }

        $result = DB::transaction(function () use ($payment, $payload, $normalized): array {
            $payment = Payment::query()
                ->with(['booking.venueCluster', 'systemBankAccount'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statusBefore = $payment->status;
            $gatewayTxnId = $normalized['transaction_id'];

            if ($payment->status === 'paid') {
                $this->logIpn($payment, $payload, $statusBefore, 'sepay_ipn_duplicate', $gatewayTxnId, 'duplicate_callback', 'SePay gửi lại webhook cho payment đã thanh toán.');

                return [
                    'success' => true,
                    'payment' => $payment,
                ];
            }

            $isDuplicateGatewayTxn = $this->isDuplicateGatewayTxn($payment, $gatewayTxnId);
            $errorCode = $this->ipnErrorCode($payment, $normalized, $isDuplicateGatewayTxn);

            $payment->gateway_response = $payload;

            if ($gatewayTxnId !== '' && ! $isDuplicateGatewayTxn) {
                $payment->gateway_txn_id = $gatewayTxnId;
            }

            if ($errorCode === null) {
                $payment->status = 'paid';
                $payment->paid_at = now();
                $payment->save();

                $payment->booking()->update([
                    'status' => 'confirmed',
                ]);

                SlotLock::query()
                    ->where('booking_id', $payment->booking_id)
                    ->delete();

                $this->creditOwnerWallet($payment->fresh(['booking.venueCluster']), $normalized);
            } else {
                $payment->status = 'failed';
                $payment->save();
            }

            $this->logIpn(
                $payment->fresh(),
                $payload,
                $statusBefore,
                'sepay_ipn',
                $gatewayTxnId,
                $errorCode,
                $this->ipnErrorMessage($errorCode),
            );

            return [
                'success' => $errorCode === null,
                'error_code' => $errorCode,
                'message' => $this->ipnErrorMessage($errorCode),
                'payment' => $payment->fresh(),
            ];
        });

        return $result;
    }

    public function ipnIsAuthorized(?string $authorization): bool
    {
        $apiKey = config('services.sepay.webhook_api_key');

        if (! $apiKey) {
            return true;
        }

        return hash_equals('Apikey '.$apiKey, (string) $authorization);
    }

    private function resolveSystemBankAccount(): SystemBankAccount
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($account) {
            return $account;
        }

        throw new RuntimeException('Chưa có tài khoản ngân hàng hệ thống đang hoạt động trong bảng system_bank_accounts.');
    }

    private function qrUrl(Payment $payment, SystemBankAccount $account): string
    {
        return rtrim((string) config('services.sepay.qr_base_url', 'https://qr.sepay.vn/img'), '?').'?'.http_build_query([
            'acc' => $account->account_number,
            'bank' => $account->bank_code ?: $account->bank_name,
            'amount' => (int) round((float) $payment->amount),
            'des' => $payment->payment_code,
            'template' => 'compact',
        ]);
    }

    private function normalizeIpnPayload(array $payload): array
    {
        return [
            'gateway' => $payload['gateway'] ?? null,
            'transaction_date' => $payload['transaction_date'] ?? $payload['transactionDate'] ?? null,
            'account_number' => (string) ($payload['account_number'] ?? $payload['accountNumber'] ?? ''),
            'payment_code' => $payload['payment_code'] ?? $payload['code'] ?? null,
            'content' => (string) ($payload['content'] ?? ''),
            'transfer_type' => Str::lower((string) ($payload['transfer_type'] ?? $payload['transferType'] ?? '')),
            'amount' => $payload['amount'] ?? $payload['transferAmount'] ?? null,
            'reference_code' => $payload['reference_code'] ?? $payload['referenceCode'] ?? null,
            'transaction_id' => (string) ($payload['transaction_id'] ?? $payload['id'] ?? $payload['reference_code'] ?? $payload['referenceCode'] ?? ''),
        ];
    }

    private function findPaymentFromIpn(array $payload): ?Payment
    {
        $paymentCode = $payload['payment_code'] ?: $this->extractPaymentCode((string) $payload['content']);

        return $paymentCode
            ? Payment::query()->where('payment_code', Str::upper($paymentCode))->first()
            : null;
    }

    private function extractPaymentCode(string $content): ?string
    {
        if (preg_match('/\bPM[A-Z0-9]{10}\b/i', $content, $matches)) {
            return Str::upper($matches[0]);
        }

        return null;
    }

    private function isDuplicateGatewayTxn(Payment $payment, string $gatewayTxnId): bool
    {
        if ($gatewayTxnId === '') {
            return false;
        }

        return Payment::query()
            ->where('gateway_txn_id', $gatewayTxnId)
            ->whereKeyNot($payment->id)
            ->exists();
    }

    private function ipnErrorCode(Payment $payment, array $payload, bool $isDuplicateGatewayTxn): ?string
    {
        if ($payload['transaction_id'] === '') {
            return 'missing_transaction_id';
        }

        if ($isDuplicateGatewayTxn) {
            return 'duplicate_gateway_txn_id';
        }

        if (! in_array($payload['transfer_type'], ['in', 'credit'], true)) {
            return 'invalid_transfer_type';
        }

        if ((int) round((float) $payload['amount']) !== (int) round((float) $payment->amount)) {
            return 'invalid_amount';
        }

        if (! $this->accountMatchesPayment($payment, $payload)) {
            return 'invalid_bank_account';
        }

        return null;
    }

    private function ipnErrorMessage(?string $errorCode): ?string
    {
        return match ($errorCode) {
            'missing_transaction_id' => 'SePay webhook thiếu mã giao dịch.',
            'duplicate_gateway_txn_id' => 'Mã giao dịch SePay đã tồn tại ở payment khác.',
            'invalid_transfer_type' => 'SePay webhook không phải giao dịch tiền vào.',
            'invalid_amount' => 'Số tiền SePay trả về không khớp với số tiền payment.',
            'invalid_bank_account' => 'Tài khoản nhận tiền SePay không khớp với payment.',
            default => null,
        };
    }

    private function accountMatchesPayment(Payment $payment, array $payload): bool
    {
        $account = $payment->systemBankAccount;

        if (! $account || $payload['account_number'] === '') {
            return true;
        }

        return $payload['account_number'] === $account->account_number;
    }

    private function creditOwnerWallet(Payment $payment, array $payload): void
    {
        $booking = $payment->booking;

        if (! $booking || $booking->payment_option === 'no_prepay') {
            return;
        }

        $cluster = $booking->venueCluster;

        if (! $cluster || ! $cluster->owner_id) {
            return;
        }

        if (OwnerWalletLedger::query()
            ->where('payment_id', $payment->id)
            ->where('type', 'credit')
            ->exists()) {
            return;
        }

        $wallet = OwnerWallet::query()->firstOrCreate(
            ['owner_id' => $cluster->owner_id],
            [
                'available_balance' => 0,
                'pending_withdrawal_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ],
        );

        $wallet = OwnerWallet::query()
            ->whereKey($wallet->id)
            ->lockForUpdate()
            ->firstOrFail();

        $amount = (float) $payment->amount;
        $balanceBefore = (float) $wallet->available_balance;
        $balanceAfter = $balanceBefore + $amount;

        $wallet->available_balance = $balanceAfter;
        $wallet->total_earned = (float) $wallet->total_earned + $amount;
        $wallet->save();

        OwnerWalletLedger::query()->create([
            'owner_wallet_id' => $wallet->id,
            'owner_id' => $cluster->owner_id,
            'venue_cluster_id' => $cluster->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_code' => $payload['transaction_id'] ?: $payment->payment_code,
            'description' => 'Hệ thống thu hộ thanh toán booking '.$booking->booking_code.'.',
            'metadata' => [
                'gateway' => $payload['gateway'],
                'account_number' => $payload['account_number'],
                'reference_code' => $payload['reference_code'],
            ],
        ]);
    }

    private function logIpn(
        Payment $payment,
        array $payload,
        ?string $statusBefore,
        string $eventType,
        string $gatewayTxnId,
        ?string $errorCode,
        ?string $errorMessage
    ): void {
        PaymentLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => $eventType,
            'response_payload' => $payload,
            'status_before' => $statusBefore,
            'status_after' => $payment->status,
            'gateway_txn_id' => $gatewayTxnId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
