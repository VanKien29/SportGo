<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PlatformFeeServicePeriod;
use App\Models\SystemBankAccount;
use App\Models\VenueAccessRestriction;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformFeePaymentService
{
    public function createAdvancePayment(VenueCluster $cluster, int $months, string $actorId): array
    {
        if (! in_array($months, config('platform_fee.allowed_prepay_months'), true)) {
            throw new RuntimeException('Số tháng thanh toán trước không hợp lệ.');
        }

        $ledger = DB::transaction(function () use ($cluster, $months): VenuePlatformFeeLedger {
            $hasOutstandingFee = VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereRaw('amount_paid < amount_due')
                ->lockForUpdate()
                ->exists();

            if ($hasOutstandingFee) {
                throw new RuntimeException('Cụm sân còn kỳ phí chưa thanh toán. Vui lòng hoàn tất các kỳ này trước khi thanh toán trước.');
            }

            $latestPeriod = PlatformFeeServicePeriod::query()
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', '!=', 'voided')
                ->orderByDesc('period_end')
                ->lockForUpdate()
                ->first();
            $latestLegacyLedger = $latestPeriod ? null : VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereNotIn('status', ['cancelled', 'voided'])
                ->orderByDesc('period_end')
                ->lockForUpdate()
                ->first();

            $courtCount = $cluster->venueCourts()->count();
            if ($courtCount < 1) {
                throw new RuntimeException('Cụm sân chưa có sân con để tính phí nền tảng.');
            }

            $profile = app(PlatformFeeProfileService::class)->ensureProfile($cluster);
            $periodStart = $latestPeriod
                ? CarbonImmutable::instance($latestPeriod->period_end)->addDay()->startOfDay()
                : ($latestLegacyLedger
                    ? CarbonImmutable::instance($latestLegacyLedger->period_end)->addDay()->startOfDay()
                    : CarbonImmutable::instance($profile->fee_started_at ?: today()->startOfMonth())->startOfDay());
            $periodEnd = $periodStart->addMonthsNoOverflow($months)->subDay();
            $quotes = [];
            $promotionUsage = [];
            $cursor = $periodStart;
            while ($cursor->lte($periodEnd)) {
                $allocationEnd = $cursor->endOfMonth()->startOfDay()->min($periodEnd);
                $quote = app(PlatformFeePricingService::class)->quote(
                    $cluster,
                    $cursor,
                    $allocationEnd,
                    true,
                    $months,
                    reservedPromotionUsage: $promotionUsage,
                );
                if (! ($quote['valid'] ?? false)) {
                    throw new RuntimeException((string) ($quote['error'] ?? 'Không tính được phí thanh toán trước.'));
                }
                $quotes[] = $quote;
                $promotionId = $quote['promotion']?->id;
                if ($promotionId && (float) $quote['promotion_discount_amount'] > 0) {
                    $promotionUsage[$promotionId] ??= ['cycles' => 0, 'amount' => 0.0];
                    $promotionUsage[$promotionId]['cycles']++;
                    $promotionUsage[$promotionId]['amount'] += (float) $quote['promotion_discount_amount'];
                }
                $cursor = $allocationEnd->addDay();
            }

            $firstQuote = $quotes[0];
            $baseAmount = round((float) collect($quotes)->sum('base_amount'), 2);
            $prepayDiscount = round((float) collect($quotes)->sum('prepay_discount_amount'), 2);
            $promotionDiscount = round((float) collect($quotes)->sum('promotion_discount_amount'), 2);
            $amountDue = round((float) collect($quotes)->sum('net_amount'), 2);
            $discountPercent = $baseAmount > 0 ? round($prepayDiscount * 100 / $baseAmount, 2) : 0.0;
            $automationKey = sprintf('prepay:%s:%s:%s', $cluster->id, $periodStart->toDateString(), $periodEnd->toDateString());

            $ledger = VenuePlatformFeeLedger::query()->create([
                'venue_cluster_id' => $cluster->id,
                'creation_source' => 'owner_prepay',
                'automation_key' => $automationKey,
                'tier_id' => $firstQuote['tier']->id,
                'plan_version_id' => $firstQuote['plan']->id,
                'tier_name_snapshot' => $firstQuote['tier']->name,
                'tier_min_courts_snapshot' => $firstQuote['tier']->min_courts,
                'tier_max_courts_snapshot' => $firstQuote['tier']->max_courts,
                'court_count' => $courtCount,
                'billing_cycle' => $months === 12 ? 'yearly' : 'monthly',
                'period_months' => $months,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => today()->addDays((int) $firstQuote['plan']->invoice_lead_days),
                'original_due_date' => today()->addDays((int) $firstQuote['plan']->invoice_lead_days),
                'price_per_court_month' => $firstQuote['tier']->price_per_court_month,
                'discount_percent' => $discountPercent,
                'pricing_snapshotted_at' => now(),
                'base_amount' => $baseAmount,
                'prepay_discount_amount' => $prepayDiscount,
                'promotion_discount_amount' => $promotionDiscount,
                'waiver_amount' => 0,
                'settlement_type' => 'prepay',
                'settlement_reason' => "Thanh toán trước {$months} tháng; mọi tháng được chốt thành phân bổ riêng.",
                'amount_due' => $amountDue,
                'amount_paid' => 0,
                'payment_proof_status' => 'none',
                'status' => 'pending',
            ]);

            foreach ($quotes as $index => $quote) {
                PlatformFeeServicePeriod::query()->create([
                    'venue_cluster_id' => $cluster->id,
                    'ledger_id' => $ledger->id,
                    'plan_version_id' => $quote['plan']->id,
                    'tier_id' => $quote['tier']->id,
                    'promotion_id' => $quote['promotion']?->id,
                    'promotion_assignment_id' => $quote['promotion_assignment']?->id,
                    'purpose' => 'prepay',
                    'status' => 'issued',
                    'period_start' => $quote['period_start']->toDateString(),
                    'period_end' => $quote['period_end']->toDateString(),
                    'court_count' => $quote['court_count'],
                    'price_per_court_month' => $quote['tier']->price_per_court_month,
                    'base_amount' => $quote['base_amount'],
                    'prepay_discount_percent' => $quote['prepay_discount_percent'],
                    'prepay_discount_amount' => $quote['prepay_discount_amount'],
                    'promotion_discount_amount' => $quote['promotion_discount_amount'],
                    'waiver_amount' => 0,
                    'net_amount' => $quote['net_amount'],
                    'idempotency_key' => $automationKey.':'.($index + 1),
                    'calculation_snapshot' => [
                        'plan_code' => $quote['plan']->code,
                        'tier_name' => $quote['tier']->name,
                        'prepay_months' => $months,
                        'service_days' => $quote['service_days'],
                        'days_in_month' => $quote['days_in_month'],
                    ],
                ]);
                app(PlatformFeePricingService::class)->consumePromotion($quote);
            }

            return $ledger;
        });

        return $this->createPayment($ledger, $actorId);
    }

    public function cancelPendingLedger(VenuePlatformFeeLedger $ledger, ?string $actorId, string $actorType, string $reason): VenuePlatformFeeLedger
    {
        return DB::transaction(function () use ($ledger, $actorId, $actorType, $reason): VenuePlatformFeeLedger {
            $ledger = VenuePlatformFeeLedger::query()
                ->whereKey($ledger->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($ledger->status, ['paid', 'settled_zero'], true) || (float) $ledger->amount_paid > 0) {
                throw new RuntimeException('Kỳ phí đã ghi nhận thanh toán nên không được hủy.');
            }

            if ($ledger->status === 'cancelled') {
                throw new RuntimeException('Kỳ phí đã được hủy trước đó.');
            }

            if ($actorType === 'owner' && $ledger->creation_source !== 'owner_prepay') {
                throw new RuntimeException('Chủ sân chỉ có thể hủy yêu cầu thanh toán trước do mình tạo.');
            }

            $oldValues = $ledger->only(['status', 'payment_code', 'payment_reject_reason']);

            $ledger->forceFill([
                'status' => 'cancelled',
                'payment_rejected_by' => $actorId,
                'payment_rejected_at' => now(),
                'payment_reject_reason' => $reason,
            ])->save();
            $ledger->servicePeriods()->update(['status' => 'voided']);

            app(PlatformFeeWalletService::class)->releaseLedgerHold($ledger, $actorId ? (int) $actorId : null);
            app(PlatformFeePricingService::class)->releasePromotionForLedger($ledger);

            AuditLog::query()->create([
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'module' => 'platform_fee',
                'action' => 'platform_fee.ledger_cancelled',
                'entity_type' => 'venue_platform_fee_ledgers',
                'entity_id' => $ledger->id,
                'old_values' => $oldValues,
                'new_values' => $ledger->fresh()->only(['status', 'payment_code', 'payment_reject_reason']),
                'context' => $actorType,
                'metadata' => ['venue_cluster_id' => $ledger->venue_cluster_id],
            ]);

            $this->unlockVenueIfFeeWasOnlyLock($ledger);

            return $ledger->fresh(['tier', 'systemBankAccount']);
        });
    }

    public function createPayment(VenuePlatformFeeLedger $ledger, string $actorId): array
    {
        return DB::transaction(function () use ($ledger, $actorId): array {
            $ledger = VenuePlatformFeeLedger::query()
                ->whereKey($ledger->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($ledger->status, ['paid', 'settled_zero', 'cancelled', 'voided', 'written_off'], true)) {
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
            if (in_array($ledger->status, ['paid', 'settled_zero'], true)) {
                return [
                    'success' => $ledger->gateway_txn_id === null || $ledger->gateway_txn_id === $gatewayTxnId,
                    'error_code' => $ledger->gateway_txn_id === null || $ledger->gateway_txn_id === $gatewayTxnId
                        ? null
                        : 'platform_fee_already_paid',
                    'message' => 'Kỳ phí đã được thanh toán.',
                ];
            }

            if ($ledger->status === 'cancelled') {
                $this->auditIpn($ledger, $payload, $gatewayTxnId, 'cancelled_ledger');

                return [
                    'success' => false,
                    'error_code' => 'cancelled_ledger',
                    'message' => $this->ipnErrorMessage('cancelled_ledger'),
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

            $ledger->servicePeriods()->update(['status' => 'paid']);

            app(PlatformFeeWalletService::class)->releaseLedgerHold($ledger);
            app(PlatformFeeArrangementService::class)->syncSettlement($ledger);

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
        return rtrim((string) config('services.sepay.qr_base_url', 'https://vietqr.app/img'), '?').'?'.http_build_query([
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
            'cancelled_ledger' => 'Kỳ phí đã hủy, giao dịch cần được đối soát thủ công.',
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
            ->whereIn('status', ['pending', 'overdue'])
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
