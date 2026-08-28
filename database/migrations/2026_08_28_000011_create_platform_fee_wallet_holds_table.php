<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_wallet_holds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_wallet_id');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('venue_cluster_id');
            $table->unsignedBigInteger('ledger_id')->nullable()->unique();
            $table->unsignedBigInteger('arrangement_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 30)->default('active');
            $table->string('reason', 255);
            $table->timestamp('starts_at');
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_wallet_id', 'status'], 'pf_wallet_holds_wallet_status_index');
            $table->index(['venue_cluster_id', 'status'], 'pf_wallet_holds_cluster_status_index');
            $table->foreign('owner_wallet_id')->references('id')->on('owner_wallets')->restrictOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->restrictOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->nullOnDelete();
            $table->foreign('arrangement_id')->references('id')->on('platform_fee_payment_arrangements')->nullOnDelete();
            $table->foreign('released_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_wallet_holds');
    }
};
