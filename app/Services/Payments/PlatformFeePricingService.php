<?php

namespace App\Services\Payments;

use App\Models\PlatformFeePlanVersion;
use App\Models\PlatformFeePromotion;
use App\Models\PlatformFeePromotionAssignment;
use App\Models\PlatformFeeTier;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;

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

        $daysInMonth = $periodStart->daysInMonth;
        $serviceDays = $periodStart->diffInDays($periodEnd) + 1;
        $fullCalendarMonth = $periodStart->isStartOfMonth() && $periodEnd->isSameDay($periodStart->endOfMonth());
        $baseAmount = $fullCalendarMonth
            ? round($courtCount * (float) $tier->price_per_court_month, 2)
            : round($courtCount * (float) $tier->price_per_court_month * $serviceDays / $daysInMonth, 2);

        $prepayPercent = 0.0;
        if ($isPrepay) {
            $rule = $plan->prepayDiscountRules
                ->first(fn ($item): bool => $item->is_active && (int) $item->months === $prepayMonths);
            $prepayPercent = (float) ($rule?->discount_percent ?? 0);
            if ($prepayMonths === 12 && $prepayPercent <= 0 && (float) $tier->annual_discount_percent > 0) {
                $prepayPercent = (float) $tier->annual_discount_percent;
            }
        }
        $prepayAmount = round($baseAmount * $prepayPercent / 100, 2);
        $afterPrepay = max($baseAmount - $prepayAmount, 0);

        $promotion = $waived ? null : $this->bestPromotion($cluster, $periodStart, $afterPrepay, $prepayAmount > 0);
        $promotionAmount = (float) ($promotion['amount'] ?? 0);
        $waiverAmount = $waived ? max($afterPrepay - $promotionAmount, 0) : 0.0;
        $netAmount = round(max($baseAmount - $prepayAmount - $promotionAmount - $waiverAmount, 0), 2);

        return [
            'valid' => true,
            'plan' => $plan,
            'tier' => $tier,
            'court_count' => $courtCount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'service_days' => $serviceDays,
            'days_in_month' => $daysInMonth,
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

        PlatformFeePromotion::query()
            ->whereKey($quote['promotion']->id)
            ->increment('spent_amount', (float) $quote['promotion_discount_amount']);

        if ($quote['promotion_assignment'] ?? null) {
            $assignment = PlatformFeePromotionAssignment::query()
                ->whereKey($quote['promotion_assignment']->id)
                ->lockForUpdate()
                ->first();
            if ($assignment) {
                $remaining = max((int) $assignment->remaining_cycles - 1, 0);
                $assignment->forceFill([
                    'remaining_cycles' => $remaining,
                    'status' => $remaining === 0 ? 'consumed' : 'active',
                    'consumed_at' => $remaining === 0 ? now() : null,
                ])->save();
            }
        }
    }

    /** @return array{promotion:PlatformFeePromotion,assignment:?PlatformFeePromotionAssignment,amount:float}|null */
    private function bestPromotion(
        VenueCluster $cluster,
        CarbonImmutable $serviceDate,
        float $eligibleAmount,
        bool $hasPrepayDiscount,
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
            ->filter(function (PlatformFeePromotion $promotion) use ($cluster): bool {
                if (! $promotion->applies_to_all_clusters || $promotion->assignments->isNotEmpty()) {
                    return true;
                }

                return VenuePlatformFeeLedger::query()
                    ->where('venue_cluster_id', $cluster->id)
                    ->where('promotion_id', $promotion->id)
                    ->whereNotIn('status', ['cancelled', 'voided'])
                    ->count() < (int) $promotion->duration_cycles;
            })
            ->map(function (PlatformFeePromotion $promotion) use ($eligibleAmount): array {
                $amount = $promotion->discount_type === 'percent'
                    ? $eligibleAmount * (float) $promotion->discount_value / 100
                    : (float) $promotion->discount_value;
                if ($promotion->max_discount_amount !== null) {
                    $amount = min($amount, (float) $promotion->max_discount_amount);
                }
                if ($promotion->budget_amount !== null) {
                    $amount = min($amount, max((float) $promotion->budget_amount - (float) $promotion->spent_amount, 0));
                }

                return [
                    'promotion' => $promotion,
                    'assignment' => $promotion->assignments->first(),
                    'amount' => round(min(max($amount, 0), $eligibleAmount), 2),
                ];
            })
            ->sortByDesc('amount')
            ->first();
    }
}
