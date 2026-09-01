<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_tiers', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_version_id')->nullable()->after('id');
        });

        $legacyVersionId = DB::table('platform_fee_plan_versions')->insertGetId([
            'code' => 'LEGACY-20260828',
            'name' => 'Bảng giá kế thừa',
            'status' => 'active',
            'effective_from' => DB::table('platform_fee_tiers')->min('effective_from') ?: now()->toDateString(),
            'trial_days' => 30,
            'invoice_lead_days' => 7,
            'due_day' => 5,
            'notice_days' => 30,
            'notes' => 'Phiên bản được tạo tự động khi chuyển đổi dữ liệu bậc phí cũ.',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('platform_fee_tiers')
            ->whereNull('plan_version_id')
            ->update(['plan_version_id' => $legacyVersionId]);

        DB::table('platform_fee_prepay_discount_rules')->insert([
            [
                'plan_version_id' => $legacyVersionId,
                'months' => 1,
                'discount_percent' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_version_id' => $legacyVersionId,
                'months' => 3,
                'discount_percent' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_version_id' => $legacyVersionId,
                'months' => 6,
                'discount_percent' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_version_id' => $legacyVersionId,
                'months' => 9,
                'discount_percent' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_version_id' => $legacyVersionId,
                'months' => 12,
                'discount_percent' => (float) (DB::table('platform_fee_tiers')->max('annual_discount_percent') ?: 0),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('platform_fee_tiers', function (Blueprint $table): void {
            $table->dropUnique('platform_fee_tiers_name_unique');
            $table->unique(['plan_version_id', 'name'], 'pf_tiers_version_name_unique');
            $table->unique(['plan_version_id', 'min_courts'], 'pf_tiers_version_min_unique');
            $table->foreign('plan_version_id')->references('id')->on('platform_fee_plan_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_tiers', function (Blueprint $table): void {
            $table->dropForeign(['plan_version_id']);
            $table->dropUnique('pf_tiers_version_name_unique');
            $table->dropUnique('pf_tiers_version_min_unique');
            $table->dropColumn('plan_version_id');
            $table->unique('name', 'platform_fee_tiers_name_unique');
        });
    }
};
