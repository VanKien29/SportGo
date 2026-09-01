<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_prepay_discount_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_version_id');
            $table->unsignedSmallInteger('months');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_version_id', 'months'], 'pf_prepay_rules_version_month_unique');
            $table->foreign('plan_version_id')->references('id')->on('platform_fee_plan_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_prepay_discount_rules');
    }
};
