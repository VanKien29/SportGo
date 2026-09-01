<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE venue_platform_fee_ledgers MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE venue_platform_fee_ledgers MODIFY billing_cycle VARCHAR(30) NOT NULL DEFAULT 'monthly'");
        }

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_version_id')->nullable()->after('tier_id');
            $table->unsignedBigInteger('promotion_id')->nullable()->after('plan_version_id');
            $table->unsignedBigInteger('payment_arrangement_id')->nullable()->after('promotion_id');
            $table->decimal('base_amount', 14, 2)->default(0)->after('pricing_snapshotted_at');
            $table->decimal('prepay_discount_amount', 14, 2)->default(0)->after('base_amount');
            $table->decimal('promotion_discount_amount', 14, 2)->default(0)->after('prepay_discount_amount');
            $table->decimal('waiver_amount', 14, 2)->default(0)->after('promotion_discount_amount');
            $table->string('settlement_type', 30)->default('standard')->after('waiver_amount');
            $table->text('settlement_reason')->nullable()->after('settlement_type');
            $table->date('original_due_date')->nullable()->after('due_date');
            $table->unsignedBigInteger('voided_by')->nullable()->after('payment_reject_reason');
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->unsignedBigInteger('replaced_by_ledger_id')->nullable()->after('voided_at');
        });

        DB::table('venue_platform_fee_ledgers')
            ->orderBy('id')
            ->chunkById(200, function ($ledgers): void {
                foreach ($ledgers as $ledger) {
                    $baseAmount = round(
                        (float) $ledger->price_per_court_month
                        * (int) $ledger->court_count
                        * max(1, (int) ($ledger->period_months ?? 1)),
                        2,
                    );

                    DB::table('venue_platform_fee_ledgers')
                        ->where('id', $ledger->id)
                        ->update([
                            'plan_version_id' => $ledger->tier_id
                                ? DB::table('platform_fee_tiers')->where('id', $ledger->tier_id)->value('plan_version_id')
                                : null,
                            'base_amount' => $baseAmount,
                            'prepay_discount_amount' => max($baseAmount - (float) $ledger->amount_due, 0),
                            'settlement_type' => (float) $ledger->amount_due <= 0 ? 'zero' : 'standard',
                            'original_due_date' => $ledger->due_date,
                        ]);
                }
            });

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->index(['plan_version_id', 'period_start'], 'vpfl_plan_version_period_index');
            $table->index(['payment_arrangement_id', 'status'], 'vpfl_arrangement_status_index');
            $table->foreign('plan_version_id')->references('id')->on('platform_fee_plan_versions')->nullOnDelete();
            $table->foreign('promotion_id')->references('id')->on('platform_fee_promotions')->nullOnDelete();
            $table->foreign('payment_arrangement_id')->references('id')->on('platform_fee_payment_arrangements')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('replaced_by_ledger_id')->references('id')->on('venue_platform_fee_ledgers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('venue_platform_fee_ledgers')->where('status', 'settled_zero')->update(['status' => 'paid']);
        DB::table('venue_platform_fee_ledgers')->whereIn('status', ['voided', 'written_off'])->update(['status' => 'cancelled']);
        DB::table('venue_platform_fee_ledgers')->where('billing_cycle', 'trial')->update(['billing_cycle' => 'monthly']);

        Schema::table('venue_platform_fee_ledgers', function (Blueprint $table): void {
            $table->dropForeign(['plan_version_id']);
            $table->dropForeign(['promotion_id']);
            $table->dropForeign(['payment_arrangement_id']);
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['replaced_by_ledger_id']);
            $table->dropIndex('vpfl_plan_version_period_index');
            $table->dropIndex('vpfl_arrangement_status_index');
            $table->dropColumn([
                'plan_version_id',
                'promotion_id',
                'payment_arrangement_id',
                'base_amount',
                'prepay_discount_amount',
                'promotion_discount_amount',
                'waiver_amount',
                'settlement_type',
                'settlement_reason',
                'original_due_date',
                'voided_by',
                'voided_at',
                'replaced_by_ledger_id',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE venue_platform_fee_ledgers MODIFY status ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE venue_platform_fee_ledgers MODIFY billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly'");
        }
    }
};
