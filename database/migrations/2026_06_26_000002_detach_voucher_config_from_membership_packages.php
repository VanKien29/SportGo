<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('membership_packages')) {
            return;
        }

        DB::table('membership_packages')->update([
            'voucher_count_per_month' => 0,
            'voucher_discount_percent' => 0,
            'voucher_min_order_amount' => 0,
            'voucher_max_discount_amount' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('membership_packages')) {
            return;
        }

        DB::table('membership_packages')->where('type', 'saving')->update([
            'voucher_count_per_month' => 2,
            'voucher_discount_percent' => 5,
            'voucher_min_order_amount' => 100000,
            'voucher_max_discount_amount' => 30000,
            'updated_at' => now(),
        ]);

        DB::table('membership_packages')->where('type', 'pro')->update([
            'voucher_count_per_month' => 5,
            'voucher_discount_percent' => 10,
            'voucher_min_order_amount' => 100000,
            'voucher_max_discount_amount' => 70000,
            'updated_at' => now(),
        ]);
    }
};
