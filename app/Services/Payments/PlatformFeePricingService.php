<?php

namespace App\Services\Payments;

use App\Models\PlatformFeePlanVersion;
use App\Models\PlatformFeePromotion;
use App\Models\PlatformFeePromotionAssignment;
use App\Models\PlatformFeeServicePeriod;
use App\Models\PlatformFeeTier;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformFeePricingService
{
    public function planFor(CarbonImmutable $serviceDate): ?PlatformFeePlanVersion
    {
        $plan = PlatformFeePlanVersion::query()
            ->with(['tiers', 'prepayDiscountRules'])
            ->whereIn('status', ['active', 'scheduled'])
            ->whereDate('effective_from', '<=', $serviceDate->toDateString())
            ->where(function ($query) use ($serviceDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $serviceDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $plan ?: PlatformFeePlanVersion::query()
            ->with(['tiers', 'prepayDiscountRules'])
            ->where('status', 'active')
            ->where('code', 'like', 'LEGACY-%')
            ->orderBy('effective_from')
            ->first();
    }

    public function tierFor(PlatformFeePlanVersion $plan, int $courtCount): ?PlatformFeeTier
    {
        $tiers = $plan->tiers;
        if ($tiers->isEmpty() && str_starts_with($plan->code, 'LEGACY-')) {
            $tiers = PlatformFeeTier::query()
                ->whereNull('plan_version_id')
                ->where('is_active', true)
                ->get();
        }

        return $tiers
            ->where('is_active', true)
            ->filter(fn (PlatformFeeTier $tier): bool => (int) $tier->min_courts <= $courtCount
                && ($tier->max_courts === null || (int) $tier->max_courts >= $courtCount))
            ->sortByDesc('min_courts')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(
        VenueCluster $cluster,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        bool $isPrepay = false,
        int $prepayMonths = 1,
        bool $waived = false,
        ?string $waiverReason = null,
        ?CarbonImmutable $referencePeriodStart = null,
        ?CarbonImmutable $referencePeriodEnd = null,
        string $purpose = 'standard',
        array $reservedPromotionUsage = [],
    ): array {
        $courtCount = $cluster->venueCourts()->count();
        $plan = $this->planFor($periodStart);
        $tier = $plan ? $this->tierFor($plan, $courtCount) : null;

        if (! $plan || ! $tier || $courtCount < 1) {
            return [
                'valid' => false,
                'error' => $courtCount < 1
                    ? 'Cụm sân chưa có sân con để tính phí nền tảng.'
                    : 'Không có phiên bản bảng giá phù hợp tại ngày bắt đầu dịch vụ.',
            ];
        }

        $referencePeriodStart ??= $periodStart->startOfMonth();
        $referencePeriodEnd ??= $periodStart->endOfMonth()->startOfDay();
        $referenceDays = $referencePeriodStart->diffInDays($referencePeriodEnd) + 1;
        $serviceDays = $periodStart->diffInDays($periodEnd) + 1;
        $fullReferencePeriod = $periodStart->isSameDay($referencePeriodStart)
            && $periodEnd->isSameDay($referencePeriodEnd);
        $baseAmount = $fullReferencePeriod
            ? round($courtCount * (float) $tier->price_per_court_month, 0, PHP_ROUND_HALF_UP)
            : round(
                $courtCount * (float) $tier->price_per_court_month * $serviceDays / max($referenceDays, 1),
                0,
                PHP_ROUND_HALF_UP,
            );

        $prepayPercent = 0.0;
        if ($isPrepay) {
            $rule = $plan->prepayDiscountRules
                ->first(fn ($item): bool => $item->is_active && (int) $item->months === $prepayMonths);
            $prepayPercent = (float) ($rule?->discount_percent ?? 0);
            if ($prepayMonths === 12 && $prepayPercent <= 0 && (float) $tier->annual_discount_percent > 0) {
                $prepayPercent = (float) $tier->annual_discount_percent;
            }
        }
        $prepayAmount = round($baseAmount * $prepayPercent / 100, 0, PHP_ROUND_HALF_UP);
        $afterPrepay = max($baseAmount - $prepayAmount, 0);

        $promotion = $waived ? null : $this->bestPromotion(
            $cluster,
            $periodStart,
            $afterPrepay,
            $prepayAmount > 0,
            $purpose,
            $reservedPromotionUsage,
        );
        $promotionAmount = (float) ($promotion['amount'] ?? 0);
        $waiverAmount = $waived ? max($afterPrepay - $promotionAmount, 0) : 0.0;
        $netAmount = round(max($baseAmount - $prepayAmount - $promotionAmount - $waiverAmount, 0), 0, PHP_ROUND_HALF_UP);

        return [
            'valid' => true,
            'venue_cluster_id' => $cluster->id,
            'plan' => $plan,
            'tier' => $tier,
            'court_count' => $courtCount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'service_days' => $serviceDays,
            'days_in_month' => $referenceDays,
            'reference_period_start' => $referencePeriodStart,
            'reference_period_end' => $referencePeriodEnd,
            'reference_days' => $referenceDays,
            'rounding_rule' => 'half_up_vnd',
            'base_amount' => $baseAmount,
            'prepay_discount_percent' => $prepayPercent,
            'prepay_discount_amount' => $prepayAmount,
            'promotion' => $promotion['promotion'] ?? null,
            'promotion_assignment' => $promotion['assignment'] ?? null,
            'promotion_discount_amount' => $promotionAmount,
            'waiver_amount' => $waiverAmount,
            'waiver_reason' => $waiverReason,
            'net_amount' => $netAmount,
        ];
    }

    public function consumePromotion(array $quote): void
    {
        if (! ($quote['promotion'] ?? null) || (float) ($quote['promotion_discount_amount'] ?? 0) <= 0) {
            return;
        }

        DB::transaction(function () use ($quote): void {
            $amount = (float) $quote['promotion_discount_amount'];
            $promotion = PlatformFeePromotion::query()
                ->whereKey($quote['promotion']->id)
                ->lockForUpdate()
                ->firstOrFail();
            $serviceDate = CarbonImmutable::instance($quote['period_start'])->startOfDay();
            if ($promotion->status !== 'active'
                || ($promotion->starts_at && $promotion->starts_at->gt($serviceDate->endOfDay()))
                || ($promotion->ends_at && $promotion->ends_at->lt($serviceDate->startOfDay()))) {
                abort(409, 'Ưu đãi không còn hiệu lực; vui lòng tính lại số tiền.');
            }
            if ($promotion->budget_amount !== null
                && (float) $promotion->spent_amount + $amount > (float) $promotion->budget_amount + 0.01) {
                abort(409, 'Ngân sách ưu đãi vừa được sử dụng hết; vui lòng tính lại số tiền.');
            }

            if ($quote['promotion_assignment'] ?? null) {
                $assignment = PlatformFeePromotionAssignment::query()
                    ->whereKey($quote['promotion_assignment']->id)
                    ->lockForUpdate()
                    ->first();
                if (! $assignment || $assignment->status !== 'active' || (int) $assignment->remaining_cycles < 1) {
                    abort(409, 'Lượt ưu đãi của cụm sân vừa hết; vui lòng tính lại số tiền.');
                }
                $remaining = max((int) $assignment->remaining_cycles - 1, 0);
                $assignment->forceFill([
                    'remaining_cycles' => $remaining,
                    'status' => $remaining === 0 ? 'consumed' : 'active',
                    'consumed_at' => $remaining === 0 ? now() : null,
                ])->save();
            } else {
                $usedCycles = PlatformFeeServicePeriod::query()
                    ->where('venue_cluster_id', $quote['venue_cluster_id'])
                    ->where('promotion_id', $promotion->id)
                    ->where('status', '!=', 'voided')
                    ->lockForUpdate()
                    ->count();
                if (! $promotion->applies_to_all_clusters || $usedCycles > (int) $promotion->duration_cycles) {
                    abort(409, 'Số kỳ được hưởng ưu đãi vừa hết; vui lòng tính lại số tiền.');
                }
            }

            $promotion->forceFill([
                'spent_amount' => round((float) $promotion->spent_amount + $amount, 2),
            ])->save();
        }, 3);
    }

    public function releasePromotionForLedger(VenuePlatformFeeLedger $ledger): void
    {
        $periods = $ledger->servicePeriods()
            ->whereNotNull('promotion_id')
            ->where('promotion_discount_amount', '>', 0)
            ->get();
        if ($periods->isEmpty() && $ledger->promotion_id && (float) $ledger->promotion_discount_amount > 0) {
            $periods = collect([(object) [
                'promotion_id' => $ledger->promotion_id,
                'promotion_assignment_id' => null,
                'promotion_discount_amount' => $ledger->promotion_discount_amount,
                'calculation_snapshot' => [],
            ]]);
        }
        if ($periods->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ledger, $periods): void {
            foreach ($periods as $period) {
                if (data_get($period->calculation_snapshot, 'promotion_released_at')) {
                    continue;
                }
                $promotion = PlatformFeePromotion::query()
                    ->whereKey($period->promotion_id)
                    ->lockForUpdate()
                    ->first();
                if (! $promotion) {
                    continue;
                }

                $amount = (float) $period->promotion_discount_amount;
                $promotion->forceFill([
                    'spent_amount' => round(max((float) $promotion->spent_amount - $amount, 0), 2),
                ])->save();

                $assignment = PlatformFeePromotionAssignment::query()
                    ->when(
                        $period->promotion_assignment_id,
                        fn ($query, $id) => $query->whereKey($id),
                        fn ($query) => $query
                            ->where('promotion_id', $promotion->id)
                            ->where('venue_cluster_id', $ledger->venue_cluster_id),
                    )
                    ->lockForUpdate()
                    ->first();
                if ($assignment) {
                    $remaining = min(
                        (int) $assignment->remaining_cycles + 1,
                        (int) ($assignment->initial_cycles ?: $promotion->duration_cycles),
                    );
                    $assignment->forceFill([
                        'remaining_cycles' => $remaining,
                        'status' => 'active',
                        'consumed_at' => null,
                    ])->save();
                }

                if ($period instanceof PlatformFeeServicePeriod) {
                    $snapshot = $period->calculation_snapshot ?: [];
                    $snapshot['promotion_released_at'] = now()->toIso8601String();
                    $period->forceFill(['calculation_snapshot' => $snapshot])->save();
                }
            }
        }, 3);
    }

    /** @return array{promotion:PlatformFeePromotion,assignment:?PlatformFeePromotionAssignment,amount:float}|null */
    private function bestPromotion(
        VenueCluster $cluster,
        CarbonImmutable $serviceDate,
        float $eligibleAmount,
        bool $hasPrepayDiscount,
        string $purpose,
        array $reservedPromotionUsage,
    ): ?array {
        if ($eligibleAmount <= 0) {
            return null;
        }

        $promotions = PlatformFeePromotion::query()
            ->where('status', 'active')
            ->where(function ($query) use ($serviceDate): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $serviceDate->endOfDay());
            })
            ->where(function ($query) use ($serviceDate): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $serviceDate->startOfDay());
            })
            ->where(function ($query) use ($cluster): void {
                $query->where('applies_to_all_clusters', true)
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($cluster): void {
                        $assignmentQuery
                            ->where('venue_cluster_id', $cluster->id)
                            ->where('status', 'active')
                            ->where('remaining_cycles', '>', 0);
                    });
            })
            ->with(['assignments' => fn ($query) => $query
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'active')])
            ->get();

        return $promotions
            ->filter(fn (PlatformFeePromotion $promotion): bool => ! $hasPrepayDiscount || $promotion->stackable_with_prepay)
            ->filter(fn (PlatformFeePromotion $promotion): bool => match ($purpose) {
                'deferred' => (bool) $promotion->applies_to_deferred,
                'bridge' => (bool) $promotion->applies_to_bridge,
                default => true,
            })
            ->filter(function (PlatformFeePromotion $promotion) use ($cluster, $reservedPromotionUsage): bool {
                $reservedCycles = (int) data_get($reservedPromotionUsage, $promotion->id.'.cycles', 0);
                $assignment = $promotion->assignments->first();
                if ($assignment) {
                    return (int) $assignment->remaining_cycles > $reservedCycles;
                }
                if (! $promotion->applies_to_all_clusters) {
                    return false;
                }

                $usedCycles = PlatformFeeServicePeriod::query()
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('promotion_id', $promotion->id)
                    ->where('status', '!=', 'voided')
                    ->count();

                return $usedCycles + $reservedCycles < (int) $promotion->duration_cycles;
            })
            ->map(function (PlatformFeePromotion $promotion) use ($eligibleAmount, $reservedPromotionUsage): array {
                $amount = $promotion->discount_type === 'percent'
                    ? $eligibleAmount * (float) $promotion->discount_value / 100
                    : (float) $promotion->discount_value;
                if ($promotion->max_discount_amount !== null) {
                    $amount = min($amount, (float) $promotion->max_discount_amount);
                }
                if ($promotion->budget_amount !== null) {
                    $reservedAmount = (float) data_get($reservedPromotionUsage, $promotion->id.'.amount', 0);
                    $amount = min($amount, max(
                        (float) $promotion->budget_amount - (float) $promotion->spent_amount - $reservedAmount,
                        0,
                    ));
                }

                return [
                    'promotion' => $promotion,
                    'assignment' => $promotion->assignments->first(),
                    'amount' => round(min(max($amount, 0), $eligibleAmount), 0, PHP_ROUND_HALF_UP),
                ];
            })
            ->sortByDesc('amount')
            ->first();
    }
}
