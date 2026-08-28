<?php

namespace App\Services\Payments;

use App\Models\PlatformFeeServicePeriod;
use App\Models\PartnerTerminationRequest;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformFeePeriodService
{
    /**
     * Tạo đúng một kỳ dịch vụ còn thiếu. Lệnh scheduler có thể chạy lại an toàn
     * vì cả kỳ dịch vụ và ledger đều có khóa idempotency theo cụm sân + thời gian.
     *
     * @return array{status:string, reason:string|null, ledger:?VenuePlatformFeeLedger}
     */
    public function generateAutomaticPeriod(VenueCluster $cluster, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today(config('platform_fee.timezone'));

        return DB::transaction(function () use ($cluster, $today): array {
            $lockedCluster = VenueCluster::query()
                ->with('owner:id,status,email,full_name,username')
                ->lockForUpdate()
                ->find($cluster->id);

            if (! $lockedCluster) {
                return $this->skipped('cluster_not_found');
            }
            if (! $this->isBillableCluster($lockedCluster, $today)) {
                return $this->skipped('cluster_not_active');
            }
            if (! $lockedCluster->owner || $lockedCluster->owner->status !== 'active') {
                return $this->skipped('owner_not_active');
            }
            if ($lockedCluster->venueCourts()->count() < 1) {
                return $this->skipped('no_courts');
            }

            $profile = app(PlatformFeeProfileService::class)->ensureProfile($lockedCluster, $today->startOfDay());
            $latestPeriod = PlatformFeeServicePeriod::query()
                ->where('venue_cluster_id', $lockedCluster->id)
                ->where('status', '!=', 'voided')
                ->orderByDesc('period_end')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $latestPeriod && (int) $profile->trial_days > 0 && $profile->trial_started_at && $profile->trial_ends_at) {
                return $this->createTrialPeriod($lockedCluster, $profile, $today);
            }

            $periodStart = $latestPeriod
                ? CarbonImmutable::instance($latestPeriod->period_end)->addDay()->startOfDay()
                : CarbonImmutable::instance($profile->fee_started_at ?: $today->startOfMonth())->startOfDay();
            $pricing = app(PlatformFeePricingService::class);
            $plan = $pricing->planFor($periodStart);
            if (! $plan) {
                return $this->skipped('pricing_plan_not_available');
            }
            $leadDays = (int) $plan->invoice_lead_days;

            if ($periodStart->gt($today->addDays($leadDays)->endOfDay())) {
                return $this->skipped('future_period_exists');
            }

            $anchorDay = max(1, min((int) $plan->billing_anchor_day, 28));
            $referenceStart = $periodStart->startOfMonth()->day($anchorDay)->startOfDay();
            if ($periodStart->lt($referenceStart)) {
                $referenceStart = $referenceStart->subMonthNoOverflow();
            }
            $referenceEnd = $referenceStart->addMonthNoOverflow()->subDay()->startOfDay();
            $cutoff = $this->terminationCutoff($lockedCluster);
            if ($cutoff && $periodStart->gt($cutoff)) {
                return $this->skipped('termination_cutoff_reached');
            }
            $periodEnd = $cutoff && $cutoff->lt($referenceEnd) ? $cutoff : $referenceEnd;
            $purpose = match (true) {
                $periodEnd->lt($referenceEnd) => 'termination',
                ! $periodStart->isSameDay($referenceStart) => 'bridge',
                default => 'standard',
            };
            $quote = $pricing->quote(
                $lockedCluster,
                $periodStart,
                $periodEnd,
                referencePeriodStart: $referenceStart,
                referencePeriodEnd: $referenceEnd,
                purpose: $purpose,
            );
            if (! ($quote['valid'] ?? false)) {
                return $this->skipped((string) ($quote['error'] ?? 'pricing_not_available'));
            }

            if ($this->hasActiveOverlap($lockedCluster->id, $periodStart, $periodEnd)) {
                return $this->skipped('period_overlap_exists');
            }

            $idempotencyKey = $this->nextIdempotencyKey($lockedCluster->id, $purpose, $periodStart, $periodEnd);
            $dueDate = $this->dueDate($periodStart, (int) $quote['plan']->due_day);
            $ledger = VenuePlatformFeeLedger::query()->create([
                'venue_cluster_id' => $lockedCluster->id,
                'creation_source' => 'system_auto',
                'automation_key' => $idempotencyKey,
                'tier_id' => $quote['tier']->id,
                'plan_version_id' => $quote['plan']->id,
                'promotion_id' => $quote['promotion']?->id,
                'tier_name_snapshot' => $quote['tier']->name,
                'tier_min_courts_snapshot' => $quote['tier']->min_courts,
                'tier_max_courts_snapshot' => $quote['tier']->max_courts,
                'court_count' => $quote['court_count'],
                'billing_cycle' => 'monthly',
                'period_months' => 1,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'original_due_date' => $dueDate->toDateString(),
                'price_per_court_month' => $quote['tier']->price_per_court_month,
                'discount_percent' => 0,
                'pricing_snapshotted_at' => now(),
                'base_amount' => $quote['base_amount'],
                'prepay_discount_amount' => 0,
                'promotion_discount_amount' => $quote['promotion_discount_amount'],
                'waiver_amount' => 0,
                'settlement_type' => $quote['net_amount'] > 0 ? 'standard' : 'zero',
                'settlement_reason' => $quote['net_amount'] > 0 ? null : 'Khuyến mại đã giảm toàn bộ phí kỳ này.',
                'amount_due' => $quote['net_amount'],
                'amount_paid' => 0,
                'payment_proof_status' => 'none',
                'status' => $quote['net_amount'] > 0 ? 'pending' : 'settled_zero',
            ]);

            $this->createServicePeriod($lockedCluster->id, $ledger, $idempotencyKey, $purpose, $quote);
            app(PlatformFeePricingService::class)->consumePromotion($quote);
            if ((int) $profile->billing_anchor_day !== $anchorDay) {
                $profile->forceFill(['billing_anchor_day' => $anchorDay])->save();
            }

            return $this->created($ledger);
        }, 3);
    }

    private function createTrialPeriod($cluster, $profile, CarbonImmutable $today): array
    {
        $periodStart = CarbonImmutable::instance($profile->trial_started_at)->startOfDay();
        $periodEnd = CarbonImmutable::instance($profile->trial_ends_at)->startOfDay();

        if ($this->hasActiveOverlap($cluster->id, $periodStart, $periodEnd)) {
            return $this->skipped('period_overlap_exists');
        }

        $plan = app(PlatformFeePricingService::class)->planFor($periodStart)
            ?: app(PlatformFeePricingService::class)->planFor($today);
        $courtCount = $cluster->venueCourts()->count();
        $tier = $plan ? app(PlatformFeePricingService::class)->tierFor($plan, $courtCount) : null;
        $idempotencyKey = $this->nextIdempotencyKey($cluster->id, 'trial', $periodStart, $periodEnd);
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $cluster->id,
            'creation_source' => 'system_trial',
            'automation_key' => $idempotencyKey,
            'tier_id' => $tier?->id,
            'plan_version_id' => $plan?->id,
            'tier_name_snapshot' => $tier?->name ?: 'Miễn phí dùng thử',
            'tier_min_courts_snapshot' => $tier?->min_courts,
            'tier_max_courts_snapshot' => $tier?->max_courts,
            'court_count' => $courtCount,
            'billing_cycle' => 'trial',
            'period_months' => 0,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $periodEnd->toDateString(),
            'original_due_date' => $periodEnd->toDateString(),
            'price_per_court_month' => $tier?->price_per_court_month ?: 0,
            'discount_percent' => 100,
            'pricing_snapshotted_at' => now(),
            'base_amount' => 0,
            'waiver_amount' => 0,
            'settlement_type' => 'trial',
            'settlement_reason' => sprintf('Miễn phí dùng thử %d ngày.', (int) $profile->trial_days),
            'amount_due' => 0,
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'settled_zero',
        ]);

        PlatformFeeServicePeriod::query()->create([
            'venue_cluster_id' => $cluster->id,
            'ledger_id' => $ledger->id,
            'plan_version_id' => $plan?->id,
            'tier_id' => $tier?->id,
            'purpose' => 'trial',
            'status' => 'settled_zero',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'court_count' => $courtCount,
            'price_per_court_month' => $tier?->price_per_court_month ?: 0,
            'base_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => 0,
            'idempotency_key' => $idempotencyKey,
            'calculation_snapshot' => [
                'trial_days' => (int) $profile->trial_days,
                'trial_started_at' => $profile->trial_started_at?->toIso8601String(),
                'trial_ends_at' => $profile->trial_ends_at?->toIso8601String(),
            ],
        ]);

        return $this->created($ledger);
    }

    private function createServicePeriod(int $clusterId, VenuePlatformFeeLedger $ledger, string $key, string $purpose, array $quote): void
    {
        PlatformFeeServicePeriod::query()->create([
            'venue_cluster_id' => $clusterId,
            'ledger_id' => $ledger->id,
            'plan_version_id' => $quote['plan']->id,
            'tier_id' => $quote['tier']->id,
            'promotion_id' => $quote['promotion']?->id,
            'promotion_assignment_id' => $quote['promotion_assignment']?->id,
            'purpose' => $purpose,
            'status' => $quote['net_amount'] > 0 ? 'issued' : 'settled_zero',
            'period_start' => $quote['period_start']->toDateString(),
            'period_end' => $quote['period_end']->toDateString(),
            'reference_period_start' => $quote['reference_period_start']->toDateString(),
            'reference_period_end' => $quote['reference_period_end']->toDateString(),
            'service_days' => $quote['service_days'],
            'reference_days' => $quote['reference_days'],
            'rounding_rule' => $quote['rounding_rule'],
            'court_count' => $quote['court_count'],
            'price_per_court_month' => $quote['tier']->price_per_court_month,
            'base_amount' => $quote['base_amount'],
            'prepay_discount_percent' => $quote['prepay_discount_percent'],
            'prepay_discount_amount' => $quote['prepay_discount_amount'],
            'promotion_discount_amount' => $quote['promotion_discount_amount'],
            'waiver_amount' => $quote['waiver_amount'],
            'net_amount' => $quote['net_amount'],
            'idempotency_key' => $key,
            'calculation_snapshot' => [
                'plan_code' => $quote['plan']->code,
                'tier_name' => $quote['tier']->name,
                'service_days' => $quote['service_days'],
                'days_in_month' => $quote['days_in_month'],
                'reference_period_start' => $quote['reference_period_start']->toDateString(),
                'reference_period_end' => $quote['reference_period_end']->toDateString(),
                'reference_days' => $quote['reference_days'],
                'rounding_rule' => $quote['rounding_rule'],
                'promotion_code' => $quote['promotion']?->code,
            ],
        ]);
    }

    private function hasActiveOverlap(int $clusterId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return PlatformFeeServicePeriod::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', '!=', 'voided')
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->lockForUpdate()
            ->exists();
    }

    private function isBillableCluster(VenueCluster $cluster, CarbonImmutable $today): bool
    {
        if ($cluster->status === 'active') {
            return true;
        }
        if ($cluster->status !== 'locked') {
            return false;
        }

        return PartnerTerminationRequest::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', [
                'submitted',
                'reviewing',
                'settlement_processing',
                'pending_signature',
                'transition_period',
                'approved',
            ])
            ->where(function ($query) use ($today): void {
                $query->whereNull('platform_fee_cutoff_at')
                    ->orWhereDate('platform_fee_cutoff_at', '>=', $today->toDateString());
            })
            ->exists();
    }

    private function terminationCutoff(VenueCluster $cluster): ?CarbonImmutable
    {
        $cutoff = PartnerTerminationRequest::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', [
                'submitted',
                'reviewing',
                'settlement_processing',
                'pending_signature',
                'transition_period',
                'approved',
            ])
            ->whereNotNull('platform_fee_cutoff_at')
            ->latest('id')
            ->value('platform_fee_cutoff_at');

        return $cutoff
            ? CarbonImmutable::parse($cutoff, config('platform_fee.timezone'))->startOfDay()
            : null;
    }

    private function nextIdempotencyKey(int $clusterId, string $purpose, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $base = sprintf('auto:%s:%s:%s:%s', $clusterId, $purpose, $start->toDateString(), $end->toDateString());
        $revision = PlatformFeeServicePeriod::query()
            ->where('idempotency_key', 'like', $base.'%')
            ->count() + 1;

        return $base.':v'.$revision;
    }

    private function dueDate(CarbonImmutable $periodStart, int $configuredDay): CarbonImmutable
    {
        $configuredDay = max(1, min($configuredDay, 28));
        $candidate = $periodStart->startOfMonth()->day($configuredDay);

        return $periodStart->day <= $configuredDay
            ? $candidate
            : $candidate->addMonthNoOverflow();
    }

    /** @return array{status:string, reason:null, ledger:VenuePlatformFeeLedger} */
    private function created(VenuePlatformFeeLedger $ledger): array
    {
        return [
            'status' => 'created',
            'reason' => null,
            'ledger' => $ledger->fresh(['venueCluster.owner', 'tier', 'planVersion', 'servicePeriods']),
        ];
    }

    /** @return array{status:string, reason:string, ledger:null} */
    private function skipped(string $reason): array
    {
        return ['status' => 'skipped', 'reason' => $reason, 'ledger' => null];
    }
}
