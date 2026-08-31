<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 70)->unique();
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedBigInteger('ledger_id');
            $table->string('type', 40);
            $table->decimal('amount', 14, 2);
            $table->string('status', 30)->default('draft');
            $table->text('reason');
            $table->string('evidence_reference', 255)->nullable();
            $table->unsignedBigInteger('owner_wallet_ledger_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ledger_id', 'status'], 'pf_adjustments_ledger_status_index');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->restrictOnDelete();
            $table->foreign('owner_wallet_ledger_id')->references('id')->on('owner_wallet_ledgers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_adjustments');
    }
};
