<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_service_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedBigInteger('ledger_id')->nullable();
            $table->unsignedBigInteger('plan_version_id')->nullable();
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->string('purpose', 40)->default('standard');
            $table->string('status', 30)->default('issued');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('court_count')->default(0);
            $table->decimal('price_per_court_month', 12, 2)->default(0);
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('prepay_discount_percent', 5, 2)->default(0);
            $table->decimal('prepay_discount_amount', 14, 2)->default(0);
            $table->decimal('promotion_discount_amount', 14, 2)->default(0);
            $table->decimal('waiver_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->string('idempotency_key', 190)->unique();
            $table->json('calculation_snapshot')->nullable();
            $table->timestamps();

            $table->index(['venue_cluster_id', 'period_start', 'period_end'], 'pf_service_periods_cluster_dates_index');
            $table->index(['status', 'period_end'], 'pf_service_periods_status_end_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->nullOnDelete();
            $table->foreign('plan_version_id')->references('id')->on('platform_fee_plan_versions')->nullOnDelete();
            $table->foreign('tier_id')->references('id')->on('platform_fee_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_service_periods');
    }
};
