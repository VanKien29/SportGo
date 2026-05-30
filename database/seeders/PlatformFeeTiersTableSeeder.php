<?php

namespace Database\Seeders;

use App\Models\PlatformFeeTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PlatformFeeTiersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('platform_fee_tiers')) {
            return;
        }

        $tiers = [
            ['1-3 sân', 1, 3, 100000],
            ['4-7 sân', 4, 7, 90000],
            ['8-11 sân', 8, 11, 80000],
            ['Trên 11 sân', 12, null, 70000],
        ];

        foreach ($tiers as [$name, $minCourts, $maxCourts, $price]) {
            PlatformFeeTier::query()->updateOrCreate(
                ['name' => $name],
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
    }
}
