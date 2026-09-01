<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_promotion_assignments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('initial_cycles')->default(1)->after('venue_cluster_id');
        });
        DB::table('platform_fee_promotion_assignments')->update([
            'initial_cycles' => DB::raw('(SELECT duration_cycles FROM platform_fee_promotions WHERE platform_fee_promotions.id = platform_fee_promotion_assignments.promotion_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_fee_promotion_assignments', function (Blueprint $table): void {
            $table->dropColumn('initial_cycles');
        });
    }
};
