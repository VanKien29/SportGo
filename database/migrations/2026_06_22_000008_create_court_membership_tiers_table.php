<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_membership_tiers', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36)->comment('Cum san cau hinh hang thanh vien.');
            $table->enum('tier', ['standard', 'silver', 'gold', 'diamond'])->comment('Hang thanh vien.');
            $table->decimal('discount_percent', 5, 2)->default(0)->comment('Phan tram giam gia ap dung.');
            $table->unsignedInteger('min_bookings')->default(0)->comment('So booking toi thieu de dat hang.');
            $table->decimal('min_spent_amount', 12, 2)->default(0)->comment('Tong tien toi thieu de dat hang.');
            $table->unsignedInteger('maintain_min_bookings')->nullable()->comment('Booking toi thieu trong ky de duy tri hang.');
            $table->decimal('maintain_min_spent', 12, 2)->nullable()->comment('Tien toi thieu trong ky de duy tri hang.');
            $table->unsignedTinyInteger('maintain_period_months')->nullable()->comment('Do dai ky duy tri tinh theo thang.');
            $table->timestamps();

            $table->unique(['venue_cluster_id', 'tier'], 'court_membership_tiers_cluster_tier_unique');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_membership_tiers');
    }
};
