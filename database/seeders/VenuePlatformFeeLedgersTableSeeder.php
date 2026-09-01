<?php

namespace Database\Seeders;

use App\Models\PlatformFeeTier;
use App\Models\PlatformFeeServicePeriod;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenuePlatformFeeLedgersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_platform_fee_ledgers') || ! Schema::hasTable('venue_clusters') || ! Schema::hasTable('platform_fee_tiers')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $clusters = VenueCluster::query()
            ->whereIn('slug', ['green-sport-ba-dinh', 'sun-sport-cau-giay'])
            ->withCount('venueCourts')
            ->get()
            ->keyBy('slug');

        $rows = [
            ['green-sport-ba-dinh', 1, '2026-04-01', '2026-04-30', '2026-05-05', 'paid', 'approved', 1],
            ['green-sport-ba-dinh', 1, '2026-05-01', '2026-05-31', '2026-06-05', 'pending', 'none', 0],
            ['sun-sport-cau-giay', 1, '2026-05-01', '2026-05-31', '2026-06-05', 'pending', 'none', 0],
        ];

        foreach ($rows as [$slug, $periodMonths, $start, $end, $dueDate, $status, $proofStatus, $paidRatio]) {
            $cluster = $clusters[$slug] ?? null;

            if (! $cluster) {
                continue;
            }

            $courtCount = max(1, (int) $cluster->venue_courts_count);
            $tier = $this->tierForCourtCount($courtCount);

            if (! $tier) {
                continue;
            }

            $discountPercent = $periodMonths === 12 ? (float) $tier->annual_discount_percent : 0.0;
            $amountDue = $courtCount * (float) $tier->price_per_court_month * $periodMonths;

            if ($discountPercent > 0) {
                $amountDue -= $amountDue * $discountPercent / 100;
            }

            VenuePlatformFeeLedger::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'period_start' => $start,
                    'period_end' => $end,
                ],
                [
                    'creation_source' => 'system',
                    'tier_id' => $tier->id,
                    'plan_version_id' => $tier->plan_version_id,
                    'tier_name_snapshot' => $tier->name,
                    'tier_min_courts_snapshot' => $tier->min_courts,
                    'tier_max_courts_snapshot' => $tier->max_courts,
                    'court_count' => $courtCount,
                    'billing_cycle' => 'monthly',
                    'period_months' => $periodMonths,
                    'due_date' => $dueDate,
                    'original_due_date' => $dueDate,
                    'price_per_court_month' => $tier->price_per_court_month,
                    'discount_percent' => $discountPercent,
                    'pricing_snapshotted_at' => now(),
                    'base_amount' => $courtCount * (float) $tier->price_per_court_month * $periodMonths,
                    'prepay_discount_amount' => max(($courtCount * (float) $tier->price_per_court_month * $periodMonths) - $amountDue, 0),
                    'promotion_discount_amount' => 0,
                    'waiver_amount' => 0,
                    'settlement_type' => 'standard',
                    'amount_due' => $amountDue,
                    'amount_paid' => $paidRatio ? $amountDue : 0,
                    'payment_proof_media_id' => null,
                    'payment_proof_status' => $proofStatus,
                    'payment_proof_note' => null,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? now()->subDays(8) : null,
                    'payment_confirmed_by' => $status === 'paid' ? $admin?->id : null,
                    'payment_confirmed_at' => $status === 'paid' ? now()->subDays(8) : null,
                    'payment_rejected_by' => null,
                    'payment_rejected_at' => null,
                    'payment_reject_reason' => null,
                    'locked_venue_at' => null,
                    'internal_receipt_id' => null,
                ],
            );

            if (Schema::hasTable('platform_fee_service_periods')) {
                $ledger = VenuePlatformFeeLedger::query()
                    ->where('venue_cluster_id', $cluster->id)
                    ->whereDate('period_start', $start)
                    ->whereDate('period_end', $end)
                    ->first();

                if ($ledger) {
                    PlatformFeeServicePeriod::query()->updateOrCreate(
                        ['idempotency_key' => "seed:{$cluster->id}:{$start}:{$end}"],
                        [
                            'venue_cluster_id' => $cluster->id,
                            'ledger_id' => $ledger->id,
                            'plan_version_id' => $tier->plan_version_id,
                            'tier_id' => $tier->id,
                            'purpose' => 'standard',
                            'status' => $status === 'paid' ? 'settled' : 'issued',
                            'period_start' => $start,
                            'period_end' => $end,
                            'court_count' => $courtCount,
                            'price_per_court_month' => $tier->price_per_court_month,
                            'base_amount' => $courtCount * (float) $tier->price_per_court_month * $periodMonths,
                            'prepay_discount_percent' => $discountPercent,
                            'prepay_discount_amount' => max(($courtCount * (float) $tier->price_per_court_month * $periodMonths) - $amountDue, 0),
                            'promotion_discount_amount' => 0,
                            'waiver_amount' => 0,
                            'net_amount' => $amountDue,
                            'calculation_snapshot' => [
                                'source' => 'deterministic_seeder',
                                'tier_name' => $tier->name,
                            ],
                        ],
                    );
                }
            }
        }
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
            ->orderByDesc('min_courts')
            ->first();
    }
}
