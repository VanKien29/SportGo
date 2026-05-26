<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_configs', function (Blueprint $table) {
            $table->char('venue_cluster_id', 36)->primary();
            $table->unsignedInteger('min_duration_minutes')->default(30);
            $table->unsignedInteger('max_duration_minutes')->nullable();
            $table->unsignedInteger('slot_hold_minutes')->default(20);
            $table->unsignedInteger('reminder_before_minutes')->default(30);
            $table->boolean('allow_full_payment')->default(true);
            $table->boolean('allow_deposit')->default(true);
            $table->boolean('allow_no_prepay')->default(true);
            $table->boolean('auto_approve_full_payment')->default(false);
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->unsignedInteger('cancel_before_hours')->default(0);
            $table->unsignedInteger('refund_percent')->default(0);
            $table->timestamps();

            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_configs');
    }
};
