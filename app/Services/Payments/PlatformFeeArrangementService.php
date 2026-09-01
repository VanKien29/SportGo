<?php

namespace App\Services\Payments;

use App\Models\OwnerWallet;
use App\Models\PlatformFeePaymentArrangement;
use App\Models\PlatformFeeServicePeriod;
use App\Models\PlatformFeeWalletHold;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformFeeArrangementService
{
    /** @return array<string,mixed> */
    public function preview(VenueCluster $cluster, int $cycles): array
    {
        if (! in_array($cycles, config('platform_fee.allowed_deferred_months'), true)) {
            throw ValidationException::withMessages(['service_months' => ['Chỉ được chọn hoãn 1, 2 hoặc 3 kỳ.']]);
        }

        $cluster->loadMissing('owner');
        if (! $cluster->owner_id || ! $cluster->owner) {
            throw ValidationException::withMessages(['venue_cluster_id' => ['Cụm sân chưa có chủ sân hợp lệ.']]);
        }

        $start = $this->nextUncoveredDate($cluster);
        $quotes = [];
        $promotionUsage = [];
        $cursor = $start;
        for ($offset = 0; $offset < $cycles; $offset++) {
            $plan = app(PlatformFeePricingService::class)->planFor($cursor);
            if (! $plan) {
                throw ValidationException::withMessages(['service_months' => ['Chưa có phiên bản bảng giá cho kỳ dự kiến.']]);
            }
            $anchor = max(1, min((int) $plan->billing_anchor_day, 28));
            $referenceStart = $cursor->startOfMonth()->day($anchor)->startOfDay();
            if ($cursor->lt($referenceStart)) {
                $referenceStart = $referenceStart->subMonthNoOverflow();
            }
            $referenceEnd = $referenceStart->addMonthNoOverflow()->subDay()->startOfDay();
            $quote = app(PlatformFeePricingService::class)->quote(
                $cluster,
                $cursor,
                $referenceEnd,
                referencePeriodStart: $referenceStart,
                referencePeriodEnd: $referenceEnd,
                purpose: 'deferred',
                reservedPromotionUsage: $promotionUsage,
            );
            if (! ($quote['valid'] ?? false)) {
                throw ValidationException::withMessages([
                    'service_months' => [(string) ($quote['error'] ?? 'Không tính được kỳ trả chậm.')],
                ]);
            }
            $quotes[] = $this->quoteSnapshot($quote);
            $this->reservePromotionUsage($promotionUsage, $quote);
            $cursor = $referenceEnd->addDay();
        }

        $total = round(collect($quotes)->sum('net_amount'), 0, PHP_ROUND_HALF_UP);
        $wallet = OwnerWallet::query()
            ->where('owner_id', $cluster->owner_id)
            ->where('venue_cluster_id', $cluster->id)
            ->first();
        $balance = $wallet
            ? app(PlatformFeeWalletService::class)->balanceBreakdown($wallet)
            : ['recorded_balance' => 0.0, 'future_booking_liability' => 0.0, 'pending_refund_liability' => 0.0, 'platform_fee_held' => 0.0, 'safe_balance' => 0.0];

        return [
            'venue_cluster_id' => $cluster->id,
            'owner_id' => $cluster->owner_id,
            'cycles' => $cycles,
            'service_start' => $quotes[0]['period_start'],
            'service_end' => $quotes[array_key_last($quotes)]['period_end'],
            'quotes' => $quotes,
            'total_amount' => $total,
            'balance' => $balance,
            'secured_amount' => min((float) $balance['safe_balance'], $total),
            'shortfall' => max($total - (float) $balance['safe_balance'], 0),
            'can_accept' => (float) $balance['safe_balance'] + 0.01 >= $total,
            'preview_hash' => $this->previewHash($quotes, $total),
        ];
    }

    public function propose(VenueCluster $cluster, array $data, ?int $adminId): PlatformFeePaymentArrangement
    {
        $cycles = (int) $data['service_months'];
        $dueDate = CarbonImmutable::parse($data['payment_due_date'], config('platform_fee.timezone'))->startOfDay();

        $arrangement = DB::transaction(function () use ($cluster, $data, $adminId, $cycles, $dueDate): PlatformFeePaymentArrangement {
            $cluster = VenueCluster::query()->with('owner')->whereKey($cluster->id)->lockForUpdate()->firstOrFail();
            $this->assertNoOpenArrangement($cluster->id);
            $preview = $this->preview($cluster, $cycles);
            $serviceEnd = CarbonImmutable::parse($preview['service_end'], config('platform_fee.timezone'));
            $maxDueDate = $serviceEnd->addDays(30);
            if ($dueDate->lte($serviceEnd) || $dueDate->gt($maxDueDate)) {
                throw ValidationException::withMessages([
                    'payment_due_date' => [sprintf(
                        'Hạn thanh toán phải sau kỳ cuối và không muộn hơn %s.',
                        $maxDueDate->format('d/m/Y'),
                    )],
                ]);
            }

            $arrangement = PlatformFeePaymentArrangement::query()->create([
                'code' => 'PFA-TMP-'.Str::uuid(),
                'venue_cluster_id' => $cluster->id,
                'owner_id' => $cluster->owner_id,
                'status' => 'pending_owner_acceptance',
                'arrangement_type' => 'secured_deferred',
                'terms_revision' => 1,
                'service_months' => $cycles,
                'service_start' => $preview['service_start'],
                'service_end' => $preview['service_end'],
                'payment_due_date' => $dueDate->toDateString(),
                'expires_at' => now()->addHours((int) config('platform_fee.arrangement_proposal_hours', 48)),
                'credit_limit' => $preview['total_amount'],
                'total_amount' => $preview['total_amount'],
                'secured_amount' => 0,
                'reason' => trim($data['reason']),
                'admin_note' => $this->nullableTrim($data['admin_note'] ?? null),
                'proposed_by' => $adminId,
                'approved_by' => $adminId,
                'approved_at' => now(),
                'metadata' => [
                    'no_prepay_discount' => true,
                    'preview_hash' => $preview['preview_hash'],
                    'quote_snapshot' => $preview['quotes'],
                    'balance_snapshot' => $preview['balance'],
                ],
            ]);
            $arrangement->forceFill([
                'code' => sprintf('PFA-%s-%06d', now()->format('Ymd'), $arrangement->id),
            ])->save();

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers.planVersion', 'holds']);
        }, 3);

        app(PlatformFeeNotificationService::class)->queueArrangementProposal($arrangement);

        return $arrangement;
    }

    public function accept(
        PlatformFeePaymentArrangement $arrangement,
        int $ownerId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PlatformFeePaymentArrangement {
        $expired = PlatformFeePaymentArrangement::query()
            ->whereKey($arrangement->id)
            ->where('status', 'pending_owner_acceptance')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
        if ($expired > 0) {
            abort(409, 'Đề nghị đã hết hạn. Vui lòng yêu cầu Admin tạo lại để cập nhật số tiền.');
        }

        return DB::transaction(function () use ($arrangement, $ownerId, $ipAddress, $userAgent): PlatformFeePaymentArrangement {
            $arrangement = PlatformFeePaymentArrangement::query()->whereKey($arrangement->id)->lockForUpdate()->firstOrFail();
            if ((int) $arrangement->owner_id !== $ownerId) {
                abort(403, 'Bạn không có quyền xác nhận thỏa thuận này.');
            }
            if ($arrangement->status !== 'pending_owner_acceptance') {
                abort(409, 'Thỏa thuận không còn chờ chủ sân xác nhận.');
            }
            if ($arrangement->expires_at && $arrangement->expires_at->isPast()) {
                abort(409, 'Đề nghị đã hết hạn. Vui lòng yêu cầu Admin tạo lại để cập nhật số tiền.');
            }

            $cluster = VenueCluster::query()->with('owner')->whereKey($arrangement->venue_cluster_id)->lockForUpdate()->firstOrFail();
            $preview = $this->preview($cluster, (int) $arrangement->service_months);
            if (! hash_equals((string) data_get($arrangement->metadata, 'preview_hash'), (string) $preview['preview_hash'])) {
                abort(409, 'Giá, số sân hoặc timeline đã thay đổi. Admin cần tạo lại đề nghị với số tiền mới.');
            }
            if (! $preview['can_accept']) {
                throw ValidationException::withMessages([
                    'balance' => ['Số dư an toàn chưa đủ bảo đảm toàn bộ khoản trả chậm; không thể xác nhận.'],
                ]);
            }

            foreach ($preview['quotes'] as $snapshot) {
                $ledger = $this->createLedgerFromSnapshot($arrangement, $snapshot);
                app(PlatformFeePricingService::class)->consumePromotion(
                    $this->hydratePromotionQuote($snapshot, (int) $arrangement->venue_cluster_id),
                );
                if ((float) $ledger->amount_due > 0) {
                    app(PlatformFeeWalletService::class)->ensureLedgerHold(
                        $ledger,
                        'Bảo đảm cho thỏa thuận trả chậm '.$arrangement->code.'.',
                    );
                }
            }

            $arrangement->forceFill([
                'status' => 'active',
                'secured_amount' => $arrangement->total_amount,
                'owner_accepted_by' => $ownerId,
                'owner_accepted_at' => now(),
                'accepted_terms_snapshot' => [
                    'revision' => (int) $arrangement->terms_revision,
                    'quotes' => $preview['quotes'],
                    'total_amount' => $preview['total_amount'],
                    'payment_due_date' => $arrangement->payment_due_date?->toDateString(),
                    'secured_amount' => $arrangement->total_amount,
                ],
                'owner_accepted_ip' => $ipAddress,
                'owner_accepted_user_agent' => $userAgent,
                'metadata' => array_merge($arrangement->metadata ?? [], ['promotions_consumed' => true]),
            ])->save();

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers.planVersion', 'holds']);
        }, 3);
    }

    public function cancel(
        PlatformFeePaymentArrangement $arrangement,
        ?int $actorId,
        string $reason,
        bool $rejected = false,
    ): PlatformFeePaymentArrangement {
        return DB::transaction(function () use ($arrangement, $actorId, $reason, $rejected): PlatformFeePaymentArrangement {
            $arrangement = PlatformFeePaymentArrangement::query()->with('ledgers.servicePeriods')->whereKey($arrangement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($arrangement->status, ['pending_owner_acceptance', 'active'], true)) {
                abort(409, 'Thỏa thuận đã kết thúc nên không thể hủy.');
            }
            if ($arrangement->ledgers->contains(fn ($ledger): bool => (float) $ledger->amount_paid > 0 || $ledger->status === 'paid')) {
                abort(409, 'Thỏa thuận đã phát sinh thanh toán; phải đối soát thay vì hủy.');
            }

            foreach ($arrangement->ledgers as $ledger) {
                $started = $ledger->period_start && $ledger->period_start->lte(today(config('platform_fee.timezone')));
                if ($started) {
                    $ledger->forceFill([
                        'payment_arrangement_id' => null,
                        'due_date' => $ledger->original_due_date,
                        'status' => $ledger->original_due_date && $ledger->original_due_date->isPast() ? 'overdue' : 'pending',
                        'settlement_reason' => 'Thỏa thuận '.$arrangement->code.' đã hủy; kỳ đã bắt đầu trở về hạn chuẩn.',
                    ])->save();
                    continue;
                }

                app(PlatformFeeWalletService::class)->releaseLedgerHold($ledger, $actorId);
                app(PlatformFeePricingService::class)->releasePromotionForLedger($ledger);
                $ledger->servicePeriods()->update(['status' => 'voided']);
                $ledger->forceFill([
                    'status' => 'voided',
                    'voided_by' => $actorId,
                    'voided_at' => now(),
                    'settlement_reason' => 'Đã hủy thỏa thuận trả chậm '.$arrangement->code.'.',
                ])->save();
            }

            $securedAmount = (float) PlatformFeeWalletHold::query()
                ->where('arrangement_id', $arrangement->id)
                ->where('status', 'active')
                ->sum('remaining_amount');
            $arrangement->forceFill([
                'status' => $rejected ? 'rejected' : 'cancelled',
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'rejected_at' => $rejected ? now() : null,
                'cancellation_reason' => trim($reason),
                'secured_amount' => $securedAmount,
            ])->save();

            return $arrangement->fresh(['venueCluster', 'owner', 'ledgers', 'holds']);
        }, 3);
    }

    public function expirePending(): int
    {
        return PlatformFeePaymentArrangement::query()
            ->where('status', 'pending_owner_acceptance')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    public function syncSettlement(VenuePlatformFeeLedger $ledger): void
    {
        if (! $ledger->payment_arrangement_id) {
            return;
        }
        $arrangement = PlatformFeePaymentArrangement::query()->whereKey($ledger->payment_arrangement_id)->lockForUpdate()->first();
        if (! $arrangement || ! in_array($arrangement->status, ['active', 'overdue'], true)) {
            return;
        }
        if (! $arrangement->ledgers()->whereNotIn('venue_platform_fee_ledgers.status', ['paid', 'settled_zero', 'cancelled', 'voided', 'written_off'])->exists()) {
            $arrangement->forceFill(['status' => 'fulfilled', 'secured_amount' => 0, 'fulfilled_at' => now()])->save();
        }
    }

    private function createLedgerFromSnapshot(PlatformFeePaymentArrangement $arrangement, array $snapshot): VenuePlatformFeeLedger
    {
        $key = sprintf('arrangement:%s:%s:%s', $arrangement->id, $snapshot['period_start'], $snapshot['period_end']);
        $standardDue = CarbonImmutable::parse($snapshot['period_start'], config('platform_fee.timezone'))
            ->startOfMonth()
            ->day(max(1, min((int) $snapshot['due_day'], 28)));
        if ($standardDue->lt(CarbonImmutable::parse($snapshot['period_start']))) {
            $standardDue = $standardDue->addMonthNoOverflow();
        }
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $arrangement->venue_cluster_id,
            'creation_source' => 'admin_arrangement',
            'automation_key' => $key,
            'tier_id' => $snapshot['tier_id'],
            'plan_version_id' => $snapshot['plan_version_id'],
            'promotion_id' => $snapshot['promotion_id'],
            'payment_arrangement_id' => $arrangement->id,
            'tier_name_snapshot' => $snapshot['tier_name'],
            'tier_min_courts_snapshot' => $snapshot['tier_min_courts'],
            'tier_max_courts_snapshot' => $snapshot['tier_max_courts'],
            'court_count' => $snapshot['court_count'],
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => $snapshot['period_start'],
            'period_end' => $snapshot['period_end'],
            'due_date' => $arrangement->payment_due_date,
            'original_due_date' => $standardDue->toDateString(),
            'price_per_court_month' => $snapshot['price_per_court_month'],
            'discount_percent' => 0,
            'pricing_snapshotted_at' => now(),
            'base_amount' => $snapshot['base_amount'],
            'prepay_discount_amount' => 0,
            'promotion_discount_amount' => $snapshot['promotion_discount_amount'],
            'waiver_amount' => 0,
            'settlement_type' => 'deferred',
            'settlement_reason' => 'Thỏa thuận trả chậm '.$arrangement->code.'.',
            'amount_due' => $snapshot['net_amount'],
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => (float) $snapshot['net_amount'] > 0 ? 'pending' : 'settled_zero',
        ]);
        PlatformFeeServicePeriod::query()->create([
            'venue_cluster_id' => $arrangement->venue_cluster_id,
            'ledger_id' => $ledger->id,
            'plan_version_id' => $snapshot['plan_version_id'],
            'tier_id' => $snapshot['tier_id'],
            'promotion_id' => $snapshot['promotion_id'],
            'promotion_assignment_id' => $snapshot['promotion_assignment_id'],
            'purpose' => 'deferred',
            'status' => (float) $snapshot['net_amount'] > 0 ? 'issued' : 'settled_zero',
            'period_start' => $snapshot['period_start'],
            'period_end' => $snapshot['period_end'],
            'reference_period_start' => $snapshot['reference_period_start'],
            'reference_period_end' => $snapshot['reference_period_end'],
            'service_days' => $snapshot['service_days'],
            'reference_days' => $snapshot['reference_days'],
            'rounding_rule' => 'half_up_vnd',
            'court_count' => $snapshot['court_count'],
            'price_per_court_month' => $snapshot['price_per_court_month'],
            'base_amount' => $snapshot['base_amount'],
            'promotion_discount_amount' => $snapshot['promotion_discount_amount'],
            'net_amount' => $snapshot['net_amount'],
            'idempotency_key' => $key,
            'calculation_snapshot' => ['arrangement_code' => $arrangement->code, 'no_prepay_discount' => true],
        ]);
        $arrangement->ledgers()->attach($ledger->id, ['original_due_date' => $standardDue->toDateString()]);

        return $ledger;
    }

    private function hydratePromotionQuote(array $snapshot, int $venueClusterId): array
    {
        return [
            'venue_cluster_id' => $venueClusterId,
            'period_start' => CarbonImmutable::parse($snapshot['period_start'], config('platform_fee.timezone')),
            'promotion' => $snapshot['promotion_id'] ? \App\Models\PlatformFeePromotion::query()->find($snapshot['promotion_id']) : null,
            'promotion_assignment' => $snapshot['promotion_assignment_id'] ? \App\Models\PlatformFeePromotionAssignment::query()->find($snapshot['promotion_assignment_id']) : null,
            'promotion_discount_amount' => $snapshot['promotion_discount_amount'],
        ];
    }

    private function quoteSnapshot(array $quote): array
    {
        return [
            'period_start' => $quote['period_start']->toDateString(),
            'period_end' => $quote['period_end']->toDateString(),
            'reference_period_start' => $quote['reference_period_start']->toDateString(),
            'reference_period_end' => $quote['reference_period_end']->toDateString(),
            'service_days' => $quote['service_days'],
            'reference_days' => $quote['reference_days'],
            'plan_version_id' => $quote['plan']->id,
            'plan_code' => $quote['plan']->code,
            'due_day' => (int) $quote['plan']->due_day,
            'tier_id' => $quote['tier']->id,
            'tier_name' => $quote['tier']->name,
            'tier_min_courts' => $quote['tier']->min_courts,
            'tier_max_courts' => $quote['tier']->max_courts,
            'court_count' => $quote['court_count'],
            'price_per_court_month' => (float) $quote['tier']->price_per_court_month,
            'base_amount' => (float) $quote['base_amount'],
            'promotion_id' => $quote['promotion']?->id,
            'promotion_code' => $quote['promotion']?->code,
            'promotion_assignment_id' => $quote['promotion_assignment']?->id,
            'promotion_discount_amount' => (float) $quote['promotion_discount_amount'],
            'net_amount' => (float) $quote['net_amount'],
        ];
    }

    private function nextUncoveredDate(VenueCluster $cluster): CarbonImmutable
    {
        $latest = PlatformFeeServicePeriod::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', '!=', 'voided')
            ->orderByDesc('period_end')
            ->first();
        if ($latest) {
            return CarbonImmutable::instance($latest->period_end)->addDay()->startOfDay();
        }
        $profile = app(PlatformFeeProfileService::class)->ensureProfile($cluster);

        return CarbonImmutable::instance(
            $profile->fee_started_at ?: $profile->trial_ends_at?->addSecond() ?: now(),
        )->startOfDay();
    }

    private function previewHash(array $quotes, float $total): string
    {
        return hash('sha256', json_encode(['quotes' => $quotes, 'total' => $total], JSON_UNESCAPED_UNICODE));
    }

    private function reservePromotionUsage(array &$usage, array $quote): void
    {
        $promotionId = $quote['promotion']?->id;
        if (! $promotionId || (float) $quote['promotion_discount_amount'] <= 0) {
            return;
        }
        $usage[$promotionId] ??= ['cycles' => 0, 'amount' => 0.0];
        $usage[$promotionId]['cycles']++;
        $usage[$promotionId]['amount'] += (float) $quote['promotion_discount_amount'];
    }

    private function assertNoOpenArrangement(int $clusterId): void
    {
        if (PlatformFeePaymentArrangement::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', ['pending_owner_acceptance', 'active', 'overdue'])
            ->lockForUpdate()
            ->first()) {
            throw ValidationException::withMessages(['venue_cluster_id' => ['Cụm sân đang có một thỏa thuận trả chậm chưa kết thúc.']]);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
