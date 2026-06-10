<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_access_restrictions')) {
            return;
        }

        Schema::create('venue_access_restrictions', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('venue_cluster_id', 36);
            $table->enum('restriction_type', ['platform_fee_overdue', 'contract_termination', 'admin_manual']);
            $table->enum('access_mode', ['full', 'limited', 'transition', 'blocked'])->default('limited');
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->char('created_by', 36)->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();

            $table->index(['venue_cluster_id', 'status'], 'venue_access_restrictions_cluster_status_index');
            $table->index(['restriction_type', 'access_mode'], 'venue_access_restrictions_type_mode_index');
            $table->foreign('venue_cluster_id', 'venue_access_restrictions_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('restrict');
            $table->foreign('created_by', 'venue_access_restrictions_created_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_access_restrictions');
    }
};
