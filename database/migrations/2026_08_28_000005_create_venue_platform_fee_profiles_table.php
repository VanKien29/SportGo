<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_platform_fee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venue_cluster_id')->unique();
            $table->unsignedBigInteger('trial_plan_version_id')->nullable();
            $table->string('trial_status', 30)->default('eligible');
            $table->unsignedSmallInteger('trial_days')->default(30);
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('fee_started_at')->nullable();
            $table->unsignedTinyInteger('billing_anchor_day')->default(1);
            $table->boolean('auto_pay_from_balance')->default(false);
            $table->timestamp('last_fee_cutoff_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['trial_status', 'trial_ends_at'], 'venue_pf_profiles_trial_status_end_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->foreign('trial_plan_version_id')->references('id')->on('platform_fee_plan_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_platform_fee_profiles');
    }
};
