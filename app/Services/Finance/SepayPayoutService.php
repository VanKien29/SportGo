<?php

namespace App\Services\Finance;

use App\Models\OwnerWithdrawalRequest;
use App\Models\Refund;
use App\Models\UserPayoutAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SepayPayoutService
{
    public function __construct(
        private readonly AdminRefundService $refunds,
        private readonly AdminWithdrawalService $withdrawals,
    ) {}

    public function refundQr(Refund $refund): array
    {
        $refund->loadMissing(['payment:id,payment_code', 'payoutAccount']);

        if (! in_array($refund->status, ['pending_confirmation', 'owner_confirmed', 'admin_processing', 'processing'], true)) {
            throw new RuntimeException('Chỉ tạo QR cho yêu cầu hoàn tiền đã được chủ sân xác nhận và đang chờ chuyển khoản.');
        }

        $payoutAccount = $this->resolveRefundPayoutAccount($refund, true);

        if (! $payoutAccount) {
            throw new RuntimeException('Yêu cầu hoàn tiền chưa có tài khoản nhận tiền hợp lệ.');
        }

        $code = $this->ensureRefundTransferCode($refund);

        return [
            'type' => 'refund',
            'id' => $refund->id,
            'transfer_code' => $code,
            'amount' => (int) round((float) $refund->amount),
            'qr_url' => $this->qrUrl(
                (string) $payoutAccount->bank_account_number,
                $this->bankCodeForQr((string) $payoutAccount->bank_name),
                (int) round((float) $refund->amount),
                $code,
            ),
            'recipient' => [
                'bank_name' => $payoutAccount->bank_name,
                'bank_code' => $this->bankCodeForQr((string) $payoutAccount->bank_name),
                'account_number' => $payoutAccount->bank_account_number,
                'account_holder' => $payoutAccount->bank_account_holder,
            ],
            'sepay_check_available' => $this->apiTokenConfigured(),
        ];
    }

    public function withdrawalQr(OwnerWithdrawalRequest $withdrawal): array
    {
        $withdrawal->loadMissing('bankAccount');

        if (! in_array($withdrawal->status, ['pending', 'reviewing', 'approved'], true)) {
            throw new RuntimeException('Chỉ tạo QR cho yêu cầu rút tiền đang chờ chuyển khoản.');
        }

        if (! $withdrawal->bankAccount || $withdrawal->bankAccount->status !== 'active') {
            throw new RuntimeException('Yêu cầu rút tiền chưa có tài khoản chủ sân hợp lệ.');
        }

        $code = $this->ensureWithdrawalTransferCode($withdrawal);

        return [
            'type' => 'withdrawal',
            'id' => $withdrawal->id,
            'transfer_code' => $code,
            'amount' => (int) round((float) $withdrawal->amount),
            'qr_url' => $this->qrUrl(
                (string) $withdrawal->bankAccount->account_number,
                $this->bankCodeForQr((string) ($withdrawal->bankAccount->bank_code ?: $withdrawal->bankAccount->bank_name)),
                (int) round((float) $withdrawal->amount),
                $code,
            ),
            'recipient' => [
                'bank_name' => $withdrawal->bankAccount->bank_name,
                'bank_code' => $this->bankCodeForQr((string) ($withdrawal->bankAccount->bank_code ?: $withdrawal->bankAccount->bank_name)),
                'account_number' => $withdrawal->bankAccount->account_number,
                'account_holder' => $withdrawal->bankAccount->account_holder_name,
            ],
            'sepay_check_available' => $this->apiTokenConfigured(),
        ];
    }

    public function checkRefund(Refund $refund, ?string $actorId): array
    {
        if ($refund->status === 'completed') {
            return [
                'completed' => true,
                'message' => 'Yêu cầu hoàn tiền đã hoàn tất trước đó.',
                'data' => $refund->fresh(),
            ];
        }

        $terminalStatuses = ['rejected', 'cancelled', 'failed'];
        if (in_array($refund->status, $terminalStatuses, true)) {
            throw new RuntimeException('Yêu cầu hoàn tiền đã kết thúc, không thể kiểm tra SePay.');
        }

        $qr = $this->refundQr($refund);
        $transaction = $this->findOutboundTransaction($qr['transfer_code'], $qr['amount']);

        if (! $transaction) {
            return [
                'completed' => false,
                'message' => 'Chưa tìm thấy giao dịch tiền ra khớp yêu cầu hoàn tiền.',
                'payout' => $qr,
            ];
        }

        return $this->completeRefundFromTransaction($refund, $transaction, $actorId);
    }

    public function checkWithdrawal(OwnerWithdrawalRequest $withdrawal, ?string $actorId): array
    {
        if ($withdrawal->status === 'completed') {
            return [
                'completed' => true,
                'message' => 'Yêu cầu rút tiền đã hoàn tất trước đó.',
                'data' => $withdrawal->fresh(),
            ];
        }

        $terminalStatuses = ['rejected', 'cancelled'];
        if (in_array($withdrawal->status, $terminalStatuses, true)) {
            throw new RuntimeException('Yêu cầu rút tiền đã kết thúc, không thể kiểm tra SePay.');
        }

        $qr = $this->withdrawalQr($withdrawal);
        $transaction = $this->findOutboundTransaction($qr['transfer_code'], $qr['amount']);

        if (! $transaction) {
            return [
                'completed' => false,
                'message' => 'Chưa tìm thấy giao dịch tiền ra khớp yêu cầu rút tiền.',
                'payout' => $qr,
            ];
        }

        return $this->completeWithdrawalFromTransaction($withdrawal, $transaction, $actorId);
    }

    public function handleIpn(array $payload): array
    {
        $transaction = $this->normalizeTransaction($payload);
        $code = $transaction['code'] ?: $this->extractPayoutCode($transaction['content']);

        if (! in_array($transaction['transfer_type'], ['out', 'debit'], true)) {
            return [
                'success' => false,
                'error_code' => 'invalid_transfer_type',
                'message' => 'SePay webhook không phải giao dịch tiền ra.',
            ];
        }

        if (! $code) {
            return [
                'success' => false,
                'error_code' => 'payout_code_not_found',
                'message' => 'Không tìm thấy mã hoàn/rút trong giao dịch SePay.',
            ];
        }

        if (Str::startsWith($code, 'RF')) {
            $refund = Refund::query()->where('payout_transfer_code', $code)->first();

            if (! $refund) {
                return $this->notFoundResult('refund_not_found', 'Không tìm thấy yêu cầu hoàn tiền tương ứng.');
            }

            try {
                return $this->completeRefundFromTransaction($refund, $transaction, null) + ['success' => true];
            } catch (RuntimeException $e) {
                return $this->notFoundResult('refund_payout_mismatch', $e->getMessage());
            }
        }

        if (Str::startsWith($code, 'WD')) {
            $withdrawal = OwnerWithdrawalRequest::query()->where('payout_transfer_code', $code)->first();

            if (! $withdrawal) {
                return $this->notFoundResult('withdrawal_not_found', 'Không tìm thấy yêu cầu rút tiền tương ứng.');
            }

            try {
                return $this->completeWithdrawalFromTransaction($withdrawal, $transaction, null) + ['success' => true];
            } catch (RuntimeException $e) {
                return $this->notFoundResult('withdrawal_payout_mismatch', $e->getMessage());
            }
        }

        return $this->notFoundResult('payout_not_found', 'Không tìm thấy nghiệp vụ hoàn/rút tương ứng.');
    }

    public function ensureRefundTransferCode(Refund $refund): string
    {
        if ($refund->payout_transfer_code) {
            return $refund->payout_transfer_code;
        }

        do {
            $code = 'RF'.Str::upper(Str::random(10));
        } while (Refund::query()->where('payout_transfer_code', $code)->exists());

        $refund->forceFill([
            'payout_transfer_code' => $code,
            'payout_qr_created_at' => now(),
        ])->save();

        return $code;
    }

    public function ensureWithdrawalTransferCode(OwnerWithdrawalRequest $withdrawal): string
    {
        if ($withdrawal->payout_transfer_code) {
            return $withdrawal->payout_transfer_code;
        }

        do {
            $code = 'WD'.Str::upper(Str::random(10));
        } while (OwnerWithdrawalRequest::query()->where('payout_transfer_code', $code)->exists());

        $withdrawal->forceFill([
            'payout_transfer_code' => $code,
            'payout_qr_created_at' => now(),
        ])->save();

        return $code;
    }

    private function resolveRefundPayoutAccount(Refund $refund, bool $persist = false): ?UserPayoutAccount
    {
        if (! in_array($refund->refund_destination, ['bank_account', 'original_payment'], true)) {
            return null;
        }

        $account = $refund->payoutAccount;

        if (! $account || $account->status !== 'active' || blank($account->bank_account_number)) {
            $customerId = $refund->customer_id ?: $refund->booking?->customer_id;

            if (! $customerId && ! $refund->relationLoaded('booking')) {
                $customerId = $refund->booking()->value('customer_id');
            }

            $account = $customerId ? UserPayoutAccount::query()
                ->where('user_id', $customerId)
                ->where('status', 'active')
                ->whereNotNull('bank_account_number')
                ->orderByDesc('is_default')
                ->latest()
                ->first() : null;
        }

        if (! $account || $account->status !== 'active' || blank($account->bank_account_number)) {
            return null;
        }

        if ($persist && ($refund->refund_destination !== 'bank_account' || $refund->user_payout_account_id !== $account->id)) {
            $refund->forceFill([
                'refund_destination' => 'bank_account',
                'user_payout_account_id' => $account->id,
            ])->save();
            $refund->setRelation('payoutAccount', $account);
        }

        return $account;
    }

    private function completeRefundFromTransaction(Refund $refund, array $transaction, ?string $actorId): array
    {
        $refund = Refund::query()->whereKey($refund->id)->firstOrFail();

        if ($refund->status === 'completed') {
            return ['completed' => true, 'transaction' => $transaction, 'data' => $refund->fresh()];
        }

        $this->assertOutboundTransactionMatches($refund->payout_transfer_code, (float) $refund->amount, $transaction);
        $this->assertTransactionReferenceUnused($transaction['transaction_id'], 'refund', $refund->id);

        $updated = $this->refunds->updateStatus($refund, 'completed', [
            'actor_id' => $actorId,
            'reason' => 'SePay xác nhận giao dịch tiền ra khớp yêu cầu hoàn tiền.',
            'source' => 'sepay_outbound',
            'gateway_refund_txn_id' => $transaction['transaction_id'],
        ]);

        return [
            'completed' => true,
            'transaction' => $transaction,
            'data' => $updated,
        ];
    }

    private function completeWithdrawalFromTransaction(OwnerWithdrawalRequest $withdrawal, array $transaction, ?string $actorId): array
    {
        $withdrawal = OwnerWithdrawalRequest::query()->whereKey($withdrawal->id)->firstOrFail();

        if ($withdrawal->status === 'completed') {
            return ['completed' => true, 'transaction' => $transaction, 'data' => $withdrawal->fresh()];
        }

        $this->assertOutboundTransactionMatches($withdrawal->payout_transfer_code, (float) $withdrawal->amount, $transaction);
        $this->assertTransactionReferenceUnused($transaction['transaction_id'], 'withdrawal', $withdrawal->id);

        $updated = $this->withdrawals->updateStatus($withdrawal, 'completed', [
            'actor_id' => $actorId,
            'reason' => 'SePay xác nhận giao dịch tiền ra khớp yêu cầu rút tiền.',
            'source' => 'sepay_outbound',
            'transfer_reference' => $transaction['transaction_id'],
        ]);

        return [
            'completed' => true,
            'transaction' => $transaction,
            'data' => $updated,
        ];
    }

    private function findOutboundTransaction(string $transferCode, int $amount): ?array
    {
        $token = (string) config('services.sepay.api_token');

        if ($token === '') {
            throw new RuntimeException('Chưa cấu hình SEPAY_API_TOKEN nên chưa thể gọi API lịch sử giao dịch SePay.');
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->get(rtrim((string) config('services.sepay.api_base_url', 'https://userapi.sepay.vn/v2'), '/').'/transactions', [
                'q' => $transferCode,
                'transfer_type' => 'out',
                'amount_out_min' => $amount,
                'amount_out_max' => $amount,
                'transaction_date_from' => now()->subDays(7)->format('Y-m-d 00:00:00'),
                'transaction_date_to' => now()->format('Y-m-d 23:59:59'),
                'transaction_date_sort' => 'desc',
                'per_page' => 20,
                'timestamp_format' => 'iso8601',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Không gọi được API lịch sử giao dịch SePay.');
        }

        foreach (($response->json('data') ?? []) as $item) {
            $transaction = $this->normalizeTransaction($item);

            if ($this->transactionMatches($transferCode, $amount, $transaction)) {
                return $transaction;
            }
        }

        return null;
    }

    private function qrUrl(string $accountNumber, string $bank, int $amount, string $content): string
    {
        return rtrim((string) config('services.sepay.qr_base_url', 'https://qr.sepay.vn/img'), '?').'?'.http_build_query([
            'acc' => $accountNumber,
            'bank' => $bank,
            'amount' => $amount,
            'des' => $content,
            'template' => 'compact',
        ]);
    }

    private function bankCodeForQr(string $value): string
    {
        $normalized = Str::upper(trim($value));
        $normalized = str_replace([' ', '-', '_', '.'], '', $normalized);

        $map = [
            'ABBANK' => 'ABBANK',
            'ACB' => 'ACB',
            'AGRIBANK' => 'AGRIBANK',
            'BIDV' => 'BIDV',
            'EIB' => 'EIB',
            'EXIMBANK' => 'EIB',
            'HDBANK' => 'HDB',
            'HDB' => 'HDB',
            'MB' => 'MB',
            'MBBANK' => 'MB',
            'MSB' => 'MSB',
            'NCB' => 'NCB',
            'OCB' => 'OCB',
            'SACOMBANK' => 'STB',
            'STB' => 'STB',
            'SHB' => 'SHB',
            'TCB' => 'TCB',
            'TECHCOMBANK' => 'TCB',
            'TPB' => 'TPB',
            'TPBANK' => 'TPB',
            'VCB' => 'VCB',
            'VIETCOMBANK' => 'VCB',
            'VIB' => 'VIB',
            'VIETINBANK' => 'VIETINBANK',
            'VPB' => 'VPB',
            'VPBANK' => 'VPB',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        throw new RuntimeException('Tài khoản nhận tiền chưa có mã ngân hàng hợp lệ để tạo QR.');
    }

    private function normalizeTransaction(array $payload): array
    {
        $transferType = Str::lower((string) ($payload['transfer_type'] ?? $payload['transferType'] ?? ''));
        $amount = $payload['amount_out'] ?? null;

        if ($amount === null && in_array($transferType, ['out', 'debit'], true)) {
            $amount = $payload['amount'] ?? $payload['transferAmount'] ?? 0;
        }

        return [
            'transaction_id' => (string) ($payload['id'] ?? $payload['reference_number'] ?? $payload['referenceCode'] ?? ''),
            'reference_number' => (string) ($payload['reference_number'] ?? $payload['referenceCode'] ?? ''),
            'transaction_date' => $payload['transaction_date'] ?? $payload['transactionDate'] ?? null,
            'account_number' => (string) ($payload['account_number'] ?? $payload['accountNumber'] ?? ''),
            'bank_brand_name' => (string) ($payload['bank_brand_name'] ?? $payload['gateway'] ?? ''),
            'transfer_type' => $transferType,
            'amount' => (int) round((float) $amount),
            'content' => (string) ($payload['transaction_content'] ?? $payload['content'] ?? ''),
            'code' => ($payload['code'] ?? null) ? Str::upper((string) $payload['code']) : null,
            'raw' => $payload,
        ];
    }

    private function assertOutboundTransactionMatches(?string $transferCode, float $amount, array $transaction): void
    {
        if (! $transferCode || ! $this->transactionMatches($transferCode, (int) round($amount), $transaction)) {
            throw new RuntimeException('Giao dịch SePay không khớp mã hoặc số tiền yêu cầu.');
        }

        if ($transaction['transaction_id'] === '') {
            throw new RuntimeException('Giao dịch SePay thiếu mã tham chiếu.');
        }
    }

    private function transactionMatches(string $transferCode, int $amount, array $transaction): bool
    {
        return in_array($transaction['transfer_type'], ['out', 'debit'], true)
            && $transaction['amount'] === $amount
            && ($transaction['code'] === $transferCode || Str::contains(Str::upper($transaction['content']), $transferCode));
    }

    private function assertTransactionReferenceUnused(string $transactionId, string $type, string $id): void
    {
        if ($transactionId === '') {
            return;
        }

        $usedByRefund = Refund::query()
            ->where('gateway_refund_txn_id', $transactionId)
            ->when($type === 'refund', fn ($query) => $query->whereKeyNot($id))
            ->exists();

        $usedByWithdrawal = OwnerWithdrawalRequest::query()
            ->where('transfer_reference', $transactionId)
            ->when($type === 'withdrawal', fn ($query) => $query->whereKeyNot($id))
            ->exists();

        if ($usedByRefund || $usedByWithdrawal) {
            throw new RuntimeException('Mã giao dịch SePay đã được dùng cho yêu cầu hoàn/rút khác.');
        }
    }

    private function extractPayoutCode(string $content): ?string
    {
        if (preg_match('/\b(RF|WD)[A-Z0-9]{10}\b/i', $content, $matches)) {
            return Str::upper($matches[0]);
        }

        return null;
    }

    private function notFoundResult(string $errorCode, string $message): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ];
    }

    private function apiTokenConfigured(): bool
    {
        return (string) config('services.sepay.api_token') !== '';
    }
}
