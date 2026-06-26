<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_fee_email_logs')) {
            return;
        }

        Schema::create('platform_fee_email_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('ledger_id', 36);
            $table->char('venue_cluster_id', 36);
            $table->string('type', 50);
            $table->string('email')->nullable();
            $table->string('subject');
            $table->text('content')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'skipped'])->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_reason')->nullable();
            $table->char('triggered_by', 36)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->cascadeOnDelete();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->cascadeOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['ledger_id', 'type', 'status'], 'pf_email_logs_ledger_type_status_index');
            $table->index(['status', 'created_at'], 'pf_email_logs_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_email_logs');
    }
};
