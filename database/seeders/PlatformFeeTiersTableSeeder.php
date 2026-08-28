<?php

namespace Database\Seeders;

use App\Models\PlatformFeeTier;
use App\Models\PlatformFeePlanVersion;
use App\Models\PlatformFeePrepayDiscountRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PlatformFeeTiersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('platform_fee_tiers')) {
            return;
        }

        $planVersion = null;
        if (Schema::hasTable('platform_fee_plan_versions')) {
            $planVersion = PlatformFeePlanVersion::query()->updateOrCreate(
                ['code' => 'SPORTGO-2026-01'],
                [
                    'name' => 'Bảng giá SportGo 2026',
                    'status' => 'active',
                    'effective_from' => '2026-01-01',
                    'trial_days' => 30,
                    'invoice_lead_days' => 7,
                    'due_day' => 5,
                    'notice_days' => 30,
                    'notes' => 'Bảng giá mẫu dùng cho dữ liệu phát triển.',
                    'activated_at' => now(),
                ],
            );

            $retiredPlans = PlatformFeePlanVersion::query()
                ->whereKeyNot($planVersion->id)
                ->where('status', 'active')
                ->get();

            foreach ($retiredPlans as $retiredPlan) {
                $effectiveTo = $planVersion->effective_from->copy()->subDay();
                $retiredPlan->forceFill([
                    'status' => 'retired',
                    'effective_from' => $retiredPlan->effective_from?->lte($effectiveTo)
                        ? $retiredPlan->effective_from
                        : null,
                    'effective_to' => $effectiveTo->toDateString(),
                    'retired_at' => now(),
                ])->save();
            }
        }

        $tiers = [
            ['1-3 sân', 1, 3, 100000],
            ['4-7 sân', 4, 7, 90000],
            ['8-11 sân', 8, 11, 80000],
            ['Trên 11 sân', 12, null, 70000],
        ];

        foreach ($tiers as [$name, $minCourts, $maxCourts, $price]) {
            PlatformFeeTier::query()->updateOrCreate(
                [
                    'plan_version_id' => $planVersion?->id,
                    'name' => $name,
                ],
                [
                    'min_courts' => $minCourts,
                    'max_courts' => $maxCourts,
                    'price_per_court_month' => $price,
                    'annual_discount_percent' => 10,
                    'is_active' => true,
                    'effective_from' => now(),
                ]
            );
        }

        if ($planVersion && Schema::hasTable('platform_fee_prepay_discount_rules')) {
            foreach ([1 => 0, 3 => 0, 6 => 0, 9 => 0, 12 => 10] as $months => $percent) {
                PlatformFeePrepayDiscountRule::query()->updateOrCreate(
                    [
                        'plan_version_id' => $planVersion->id,
                        'months' => $months,
                    ],
                    [
                        'discount_percent' => $percent,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
