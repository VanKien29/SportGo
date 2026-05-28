<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_platform_fee_ledgers', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->unsignedBigInteger('tier_id')->nullable();
            $table->unsignedInteger('court_count');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('price_per_court_month', 12, 2)->default(0.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('amount_due', 12, 2)->default(0.00);
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('billing_cycle', 'venue_platform_fee_ledgers_billing_cycle_index');
            $table->index('period_start', 'venue_platform_fee_ledgers_period_start_index');
            $table->index('period_end', 'venue_platform_fee_ledgers_period_end_index');
            $table->index('status', 'venue_platform_fee_ledgers_status_index');
            $table->index(['venue_cluster_id', 'status'], 'venue_platform_fee_ledgers_venue_cluster_id_status_index');
            $table->foreign('tier_id')->references('id')->on('platform_fee_tiers')->onDelete('set null');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_platform_fee_ledgers');
    }
};
