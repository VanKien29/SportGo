<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_settlements')) {
            return;
        }

        Schema::create('partner_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('settlement_code', 50)->unique();
            $table->unsignedBigInteger('partner_termination_request_id');
            $table->unsignedBigInteger('partner_contract_id');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('venue_cluster_id')->nullable();
            $table->decimal('owner_wallet_available_amount', 14, 2)->default(0);
            $table->decimal('owner_wallet_pending_amount', 14, 2)->default(0);
            $table->decimal('platform_fee_remaining_refund_amount', 14, 2)->default(0);
            $table->decimal('unpaid_platform_fee_amount', 14, 2)->default(0);
            $table->decimal('penalty_amount', 14, 2)->default(0);
            $table->decimal('adjustment_amount', 14, 2)->default(0);
            $table->decimal('final_payable_to_owner', 14, 2)->default(0);
            $table->decimal('final_receivable_from_owner', 14, 2)->default(0);
            $table->enum('status', ['draft', 'calculated', 'pending_approval', 'approved', 'payout_created', 'completed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['partner_termination_request_id', 'status'], 'partner_settlements_request_status_index');
            $table->index(['owner_id', 'status'], 'partner_settlements_owner_status_index');
            $table->foreign('partner_termination_request_id', 'partner_settlements_request_foreign')
                ->references('id')->on('partner_termination_requests')->onDelete('restrict');
            $table->foreign('partner_contract_id', 'partner_settlements_contract_foreign')
                ->references('id')->on('partner_contracts')->onDelete('restrict');
            $table->foreign('owner_id', 'partner_settlements_owner_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('venue_cluster_id', 'partner_settlements_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('calculated_by', 'partner_settlements_calculated_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by', 'partner_settlements_approved_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settlements');
    }
};
