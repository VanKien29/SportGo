<?php

namespace Database\Seeders;

use App\Models\PlatformFeeTier;
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
            ->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])
            ->withCount('venueCourts')
            ->get()
            ->keyBy('slug');

        $rows = [
            ['sportgo-cau-giay', 1, '2026-04-01', '2026-04-30', '2026-05-05', 'paid', 'approved', 1],
            ['sportgo-cau-giay', 1, '2026-05-01', '2026-05-31', '2026-06-05', 'pending', 'none', 0],
            ['sportgo-my-dinh', 3, '2026-02-01', '2026-04-30', '2026-05-05', 'overdue', 'rejected', 0],
            ['sportgo-my-dinh', 12, '2026-01-01', '2026-12-31', '2026-01-10', 'paid', 'approved', 1],
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
                    'tier_id' => $tier->id,
                    'court_count' => $courtCount,
                    'billing_cycle' => $periodMonths === 12 ? 'yearly' : 'monthly',
                    'period_months' => $periodMonths,
                    'due_date' => $dueDate,
                    'price_per_court_month' => $tier->price_per_court_month,
                    'discount_percent' => $discountPercent,
                    'amount_due' => $amountDue,
                    'amount_paid' => $paidRatio ? $amountDue : 0,
                    'payment_proof_media_id' => null,
                    'payment_proof_status' => $proofStatus,
                    'payment_proof_note' => $proofStatus === 'rejected' ? 'Ảnh chuyển khoản không đọc được nội dung giao dịch.' : null,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? now()->subDays(8) : null,
                    'payment_confirmed_by' => $status === 'paid' ? $admin?->id : null,
                    'payment_confirmed_at' => $status === 'paid' ? now()->subDays(8) : null,
                    'payment_rejected_by' => $proofStatus === 'rejected' ? $admin?->id : null,
                    'payment_rejected_at' => $proofStatus === 'rejected' ? now()->subDays(12) : null,
                    'payment_reject_reason' => $proofStatus === 'rejected' ? 'Ảnh chuyển khoản không đọc được nội dung giao dịch.' : null,
                    'locked_venue_at' => null,
                    'internal_receipt_id' => null,
                ]
            );
        }
    }

    private function tierForCourtCount(int $courtCount): ?PlatformFeeTier
    {
        return PlatformFeeTier::query()
            ->where('is_active', true)
            ->where('min_courts', '<=', $courtCount)
            ->where(function ($query) use ($courtCount) {
                $query->whereNull('max_courts')
                    ->orWhere('max_courts', '>=', $courtCount);
            })
            ->orderByDesc('min_courts')
            ->first();
    }
}
