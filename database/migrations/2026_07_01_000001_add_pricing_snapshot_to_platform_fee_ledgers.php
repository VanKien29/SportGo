<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->string('creation_source', 30)->default('system')->after('venue_cluster_id');
            $table->string('tier_name_snapshot', 100)->nullable()->after('tier_id');
            $table->unsignedInteger('tier_min_courts_snapshot')->nullable()->after('tier_name_snapshot');
            $table->unsignedInteger('tier_max_courts_snapshot')->nullable()->after('tier_min_courts_snapshot');
            $table->timestamp('pricing_snapshotted_at')->nullable()->after('discount_percent');
        });

        DB::table('venue_platform_fee_ledgers')
            ->orderBy('id')
            ->chunk(200, function ($ledgers): void {
                $tiers = DB::table('platform_fee_tiers')
                    ->whereIn('id', $ledgers->pluck('tier_id')->filter()->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($ledgers as $ledger) {
                    $tier = $tiers->get($ledger->tier_id);

                    DB::table('venue_platform_fee_ledgers')
                        ->where('id', $ledger->id)
                        ->update([
                            'tier_name_snapshot' => $tier?->name
                                ?? ($ledger->tier_id ? 'Bậc phí #'.$ledger->tier_id : 'Theo cấu hình'),
                            'tier_min_courts_snapshot' => $tier?->min_courts,
                            'tier_max_courts_snapshot' => $tier?->max_courts,
                            'pricing_snapshotted_at' => $ledger->created_at ?? now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->dropColumn([
                'tier_name_snapshot',
                'tier_min_courts_snapshot',
                'tier_max_courts_snapshot',
                'pricing_snapshotted_at',
                'creation_source',
            ]);
        });
    }
};
