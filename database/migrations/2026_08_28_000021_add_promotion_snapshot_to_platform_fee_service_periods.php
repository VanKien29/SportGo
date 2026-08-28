<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_service_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('promotion_id')->nullable()->after('tier_id');
            $table->unsignedBigInteger('promotion_assignment_id')->nullable()->after('promotion_id');

            $table->index('promotion_id', 'pf_service_periods_promotion_index');
            $table->foreign('promotion_id')->references('id')->on('platform_fee_promotions')->nullOnDelete();
            $table->foreign('promotion_assignment_id')->references('id')->on('platform_fee_promotion_assignments')->nullOnDelete();
        });

        DB::table('platform_fee_service_periods')
            ->whereNotNull('ledger_id')
            ->update([
                'promotion_id' => DB::raw('(SELECT promotion_id FROM venue_platform_fee_ledgers WHERE venue_platform_fee_ledgers.id = platform_fee_service_periods.ledger_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('platform_fee_service_periods', function (Blueprint $table): void {
            $table->dropForeign(['promotion_assignment_id']);
            $table->dropForeign(['promotion_id']);
            $table->dropIndex('pf_service_periods_promotion_index');
            $table->dropColumn(['promotion_assignment_id', 'promotion_id']);
        });
    }
};
