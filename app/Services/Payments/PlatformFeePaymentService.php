<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SystemBankAccount;
use App\Models\VenueAccessRestriction;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformFeePaymentService
{
    public function createPayment(VenuePlatformFeeLedger $ledger, string $actorId): array
    {
        return DB::transaction(function () use ($ledger, $actorId): array {
            $ledger = VenuePlatformFeeLedger::query()
                ->whereKey($ledger->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($ledger->status, ['paid', 'cancelled'], true)) {
                throw new RuntimeException('Kỳ phí này đã hoàn tất hoặc đã hủy.');
            }

            $amountRemaining = $this->amountRemaining($ledger);
            if ($amountRemaining <= 0) {
                throw new RuntimeException('Kỳ phí này không còn số tiền cần thanh toán.');
            }

            $account = $ledger->system_bank_account_id
                ? SystemBankAccount::query()->whereKey($ledger->system_bank_account_id)->where('status', 'active')->first()
                : null;
            $account ??= $this->resolveSystemBankAccount();

            $ledger->forceFill([
                'system_bank_account_id' => $account->id,
                'payment_code' => $ledger->payment_code ?: $this->paymentCode($ledger),
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $actorId,
                'actor_type' => 'owner',
                'module' => 'platform_fee',
                'action' => 'platform_fee.sepay_qr_created',
                'entity_type' => 'venue_platform_fee_ledgers',
                'entity_id' => $ledger->id,
                'new_values' => [
                    'payment_code' => $ledger->payment_code,
                    'amount' => $amountRemaining,
                    'system_bank_account_id' => $account->id,
                ],
                'context' => 'owner',
                'metadata' => ['venue_cluster_id' => $ledger->venue_cluster_id],
            ]);

            return [
                'ledger' => $ledger->fresh(),
                'payment_account' => $account,
                'transfer_content' => $ledger->payment_code,
                'amount' => $amountRemaining,
                'qr_url' => $this->qrUrl($ledger->payment_code, $amountRemaining, $account),
            ];
        });
    }

    public function handleIpn(array $payload): array
    {
        $normalized = $this->normalizeIpnPayload($payload);
        $paymentCode = $normalized['payment_code'] ?: $this->extractPaymentCode($normalized['content']);
        $ledger = $paymentCode
            ? VenuePlatformFeeLedger::query()->where('payment_code', Str::upper($paymentCode))->first()
            : null;

        if (! $ledger) {
            return [
                'success' => false,
                'error_code' => 'platform_fee_payment_not_found',
                'message' => 'Không tìm thấy kỳ phí tương ứng với giao dịch SePay.',
            ];
        }

        return DB::transaction(function () use ($ledger, $payload, $normalized): array {
            $ledger = VenuePlatformFeeLedger::query()
                ->with('systemBankAccount')
                ->whereKey($ledger->id)
                ->lockForUpdate()
                ->firstOrFail();

            $gatewayTxnId = $normalized['transaction_id'];
            if ($ledger->status === 'paid') {
                return [
                    'success' => $ledger->gateway_txn_id === null || $ledger->gateway_txn_id === $gatewayTxnId,
                    'error_code' => $ledger->gateway_txn_id === null || $ledger->gateway_txn_id === $gatewayTxnId
                        ? null
                        : 'platform_fee_already_paid',
                    'message' => 'Kỳ phí đã được thanh toán.',
                ];
            }

            $errorCode = $this->ipnErrorCode($ledger, $normalized);
            if ($errorCode !== null) {
                $this->auditIpn($ledger, $payload, $gatewayTxnId, $errorCode);

                return [
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => $this->ipnErrorMessage($errorCode),
                ];
            }

            $oldValues = $ledger->only(['amount_paid', 'status', 'paid_at', 'gateway_txn_id']);
            $ledger->forceFill([
                'amount_paid' => $ledger->amount_due,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_confirmed_at' => now(),
                'payment_confirmed_by' => null,
                'gateway_txn_id' => $gatewayTxnId,
                'gateway_response' => $payload,
            ])->save();

            $this->unlockVenueIfFeeWasOnlyLock($ledger);
            $this->auditIpn($ledger, $payload, $gatewayTxnId, null, $oldValues);

            return [
                'success' => true,
                'ledger' => $ledger->fresh(),
            ];
        });
    }

    private function resolveSystemBankAccount(): SystemBankAccount
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->latest()
            ->first();

        if (! $account) {
            throw new RuntimeException('Chưa có tài khoản ngân hàng hệ thống đang hoạt động.');
        }

        return $account;
    }

    private function paymentCode(VenuePlatformFeeLedger $ledger): string
    {
        return 'PF'.Str::upper(str_replace('-', '', $ledger->id));
    }

    private function qrUrl(string $paymentCode, float $amount, SystemBankAccount $account): string
    {
        return rtrim((string) config('services.sepay.qr_base_url', 'https://qr.sepay.vn/img'), '?').'?'.http_build_query([
            'acc' => $account->account_number,
            'bank' => $account->bank_code ?: $account->bank_name,
            'amount' => (int) round($amount),
            'des' => $paymentCode,
            'template' => 'compact',
        ]);
    }

    private function normalizeIpnPayload(array $payload): array
    {
        return [
            'account_number' => (string) ($payload['account_number'] ?? $payload['accountNumber'] ?? ''),
            'payment_code' => $payload['payment_code'] ?? $payload['code'] ?? null,
            'content' => (string) ($payload['content'] ?? ''),
            'transfer_type' => Str::lower((string) ($payload['transfer_type'] ?? $payload['transferType'] ?? '')),
            'amount' => $payload['amount'] ?? $payload['transferAmount'] ?? null,
            'transaction_id' => (string) ($payload['transaction_id'] ?? $payload['id'] ?? $payload['reference_code'] ?? $payload['referenceCode'] ?? ''),
        ];
    }

    private function extractPaymentCode(string $content): ?string
    {
        return preg_match('/\bPF[A-F0-9]{32}\b/i', $content, $matches)
            ? Str::upper($matches[0])
            : null;
    }

    private function ipnErrorCode(VenuePlatformFeeLedger $ledger, array $payload): ?string
    {
        if ($payload['transaction_id'] === '') {
            return 'missing_transaction_id';
        }
        if (! in_array($payload['transfer_type'], ['in', 'credit'], true)) {
            return 'invalid_transfer_type';
        }
        if ((int) round((float) $payload['amount']) !== (int) round($this->amountRemaining($ledger))) {
            return 'invalid_amount';
        }
        if ($payload['account_number'] !== ''
            && $ledger->systemBankAccount
            && $payload['account_number'] !== $ledger->systemBankAccount->account_number) {
            return 'invalid_bank_account';
        }
        if (VenuePlatformFeeLedger::query()
            ->where('gateway_txn_id', $payload['transaction_id'])
            ->whereKeyNot($ledger->id)
            ->exists()
            || Payment::query()->where('gateway_txn_id', $payload['transaction_id'])->exists()) {
            return 'duplicate_gateway_txn_id';
        }

        return null;
    }

    private function ipnErrorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'missing_transaction_id' => 'SePay webhook thiếu mã giao dịch.',
            'invalid_transfer_type' => 'SePay webhook không phải giao dịch tiền vào.',
            'invalid_amount' => 'Số tiền chuyển khoản không khớp số phí còn phải đóng.',
            'invalid_bank_account' => 'Tài khoản nhận tiền không khớp tài khoản đã tạo QR.',
            'duplicate_gateway_txn_id' => 'Mã giao dịch SePay đã được sử dụng.',
            default => 'Không thể xác nhận thanh toán phí nền tảng.',
        };
    }

    private function amountRemaining(VenuePlatformFeeLedger $ledger): float
    {
        return round(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 2);
    }

    private function auditIpn(
        VenuePlatformFeeLedger $ledger,
        array $payload,
        string $gatewayTxnId,
        ?string $errorCode,
        ?array $oldValues = null,
    ): void {
        AuditLog::query()->create([
            'actor_type' => 'system',
            'module' => 'platform_fee',
            'action' => $errorCode ? 'platform_fee.sepay_ipn_rejected' : 'platform_fee.sepay_paid',
            'entity_type' => 'venue_platform_fee_ledgers',
            'entity_id' => $ledger->id,
            'old_values' => $oldValues,
            'new_values' => $ledger->fresh()->toArray(),
            'context' => 'system',
            'metadata' => [
                'gateway_txn_id' => $gatewayTxnId,
                'error_code' => $errorCode,
                'payload' => $payload,
            ],
            'severity' => $errorCode ? 'warning' : 'info',
        ]);
    }

    private function unlockVenueIfFeeWasOnlyLock(VenuePlatformFeeLedger $ledger): void
    {
        $hasOtherDebt = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->whereKeyNot($ledger->id)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereRaw('amount_paid < amount_due')
            ->exists();

        if ($hasOtherDebt) {
            return;
        }

        VenueAccessRestriction::query()
            ->where('venue_cluster_id', $ledger->venue_cluster_id)
            ->where('restriction_type', 'platform_fee_overdue')
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'ends_at' => now(),
            ]);

        $cluster = VenueCluster::query()->find($ledger->venue_cluster_id);
        if ($cluster?->status === 'locked'
            && Str::contains(Str::lower((string) $cluster->status_reason), ['phí nền tảng', 'platform fee'])) {
            $cluster->update([
                'status' => 'active',
                'status_reason' => null,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
            ]);
        }
    }
}
