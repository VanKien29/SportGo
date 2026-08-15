<?php

namespace App\Services\Payments;

use App\Models\PlatformFeeTier;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformFeePeriodService
{
    public const AUTOMATIC_PERIOD_MONTHS = 1;

    public const AUTOMATIC_LEAD_DAYS = 7;

    /**
     * Generate one missing monthly period for an active cluster.
     *
     * The whole decision is made inside a transaction so a scheduler retry
     * cannot create a second ledger for the same cluster and period.
     *
     * @return array{status:string, reason:string|null, ledger:?VenuePlatformFeeLedger}
     */
    public function generateAutomaticPeriod(VenueCluster $cluster, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        return DB::transaction(function () use ($cluster, $today): array {
            $lockedCluster = VenueCluster::query()
                ->with('owner:id,status,email,full_name,username')
                ->lockForUpdate()
                ->find($cluster->id);

            if (! $lockedCluster) {
                return $this->skipped('cluster_not_found');
            }

            if ($lockedCluster->status !== 'active') {
                return $this->skipped('cluster_not_active');
            }

            if (! $lockedCluster->owner || $lockedCluster->owner->status !== 'active') {
                return $this->skipped('owner_not_active');
            }

            $courtCount = $lockedCluster->venueCourts()->count();
            if ($courtCount < 1) {
                return $this->skipped('no_courts');
            }

            $tier = $this->tierForCourtCount($courtCount);
            if (! $tier) {
                return $this->skipped('no_active_tier');
            }

            $latestLedger = VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $lockedCluster->id)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('period_end')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $threshold = $today->addDays(self::AUTOMATIC_LEAD_DAYS);
            if ($latestLedger?->period_end && $latestLedger->period_end->gt($threshold)) {
                return $this->skipped('future_period_exists');
            }

            $periodStart = $latestLedger?->period_end
                ? CarbonImmutable::instance($latestLedger->period_end)->addDay()
                : $today->startOfMonth();

            // Do not backfill an unlimited number of historical months when
            // an administrator has missed several runs; resume from the
            // current calendar month instead.
            if ($periodStart->lt($today->startOfMonth())) {
                $periodStart = $today->startOfMonth();
            }

            $periodEnd = $periodStart
                ->addMonthsNoOverflow(self::AUTOMATIC_PERIOD_MONTHS)
                ->subDay();
            $automationKey = sprintf(
                'auto:%s:%s:%s',
                $lockedCluster->id,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            );

            if (VenuePlatformFeeLedger::query()->where('automation_key', $automationKey)->exists()) {
                return $this->skipped('automation_key_exists');
            }

            $overlap = VenuePlatformFeeLedger::query()
                ->where('venue_cluster_id', $lockedCluster->id)
                ->where('status', '!=', 'cancelled')
                ->whereDate('period_start', '<=', $periodEnd->toDateString())
                ->whereDate('period_end', '>=', $periodStart->toDateString())
                ->exists();

            if ($overlap) {
                return $this->skipped('period_overlap_exists');
            }

            $baseAmount = round(
                $courtCount * (float) $tier->price_per_court_month * self::AUTOMATIC_PERIOD_MONTHS,
                2,
            );
            $discountPercent = 0.0;
            $amountDue = $baseAmount;

            $ledger = VenuePlatformFeeLedger::query()->create([
                'venue_cluster_id' => $lockedCluster->id,
                'creation_source' => 'system_auto',
                'automation_key' => $automationKey,
                'tier_id' => $tier->id,
                'tier_name_snapshot' => $tier->name,
                'tier_min_courts_snapshot' => $tier->min_courts,
                'tier_max_courts_snapshot' => $tier->max_courts,
                'court_count' => $courtCount,
                'billing_cycle' => 'monthly',
                'period_months' => self::AUTOMATIC_PERIOD_MONTHS,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $periodEnd->toDateString(),
                'price_per_court_month' => $tier->price_per_court_month,
                'discount_percent' => $discountPercent,
                'pricing_snapshotted_at' => now(),
                'amount_due' => $amountDue,
                'amount_paid' => 0,
                'payment_proof_status' => 'none',
                'status' => 'pending',
            ]);

            return [
                'status' => 'created',
                'reason' => null,
                'ledger' => $ledger->fresh(['venueCluster.owner', 'tier']),
            ];
        });
    }

    private function tierForCourtCount(int $courtCount): ?PlatformFeeTier
    {
        return PlatformFeeTier::query()
            ->where('is_active', true)
            ->where('min_courts', '<=', $courtCount)
            ->where(function ($query) use ($courtCount): void {
                $query->whereNull('max_courts')
                    ->orWhere('max_courts', '>=', $courtCount);
            })
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->orderByDesc('min_courts')
            ->first();
    }

    /** @return array{status:string, reason:string, ledger:null} */
    private function skipped(string $reason): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'ledger' => null,
        ];
    }
}
