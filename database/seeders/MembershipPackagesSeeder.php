<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MembershipPackagesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('membership_packages')) {
            return;
        }

        foreach ($this->packages() as $package) {
            $existingId = DB::table('membership_packages')->where('type', $package['type'])->value('id');
            $payload = [
                ...$package,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('membership_packages')->where('id', $existingId)->update($payload);
                continue;
            }

            $payload['id'] = (string) Str::uuid();
            $payload['created_at'] = now();
            DB::table('membership_packages')->insert($payload);
        }
    }

    private function packages(): array
    {
        return [
            [
                'name' => 'Thuong',
                'type' => 'free',
                'monthly_price' => 0,
                'quarterly_price' => null,
                'yearly_price' => null,
                'voucher_count_per_month' => 0,
                'voucher_discount_percent' => 0,
                'voucher_min_order_amount' => 0,
                'voucher_max_discount_amount' => null,
                'cashback_percent' => 0,
                'match_post_limit_per_month' => 5,
                'priority_complaint' => false,
                'badge_name' => null,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Tiet kiem',
                'type' => 'saving',
                'monthly_price' => null,
                'quarterly_price' => null,
                'yearly_price' => null,
                'voucher_count_per_month' => 2,
                'voucher_discount_percent' => 0,
                'voucher_min_order_amount' => 0,
                'voucher_max_discount_amount' => null,
                'cashback_percent' => 2,
                'match_post_limit_per_month' => 15,
                'priority_complaint' => false,
                'badge_name' => 'SportGo Saving',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Pro',
                'type' => 'pro',
                'monthly_price' => null,
                'quarterly_price' => null,
                'yearly_price' => null,
                'voucher_count_per_month' => 5,
                'voucher_discount_percent' => 0,
                'voucher_min_order_amount' => 0,
                'voucher_max_discount_amount' => null,
                'cashback_percent' => 5,
                'match_post_limit_per_month' => -1,
                'priority_complaint' => true,
                'badge_name' => 'SportGo Pro',
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];
    }
}
