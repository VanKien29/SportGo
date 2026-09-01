<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_promotion_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedSmallInteger('remaining_cycles');
            $table->string('status', 30)->default('active');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'venue_cluster_id'], 'pf_promotion_assignments_unique');
            $table->index(['venue_cluster_id', 'status'], 'pf_promotion_assignments_cluster_status_index');
            $table->foreign('promotion_id')->references('id')->on('platform_fee_promotions')->cascadeOnDelete();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_promotion_assignments');
    }
};
