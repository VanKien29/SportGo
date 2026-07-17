<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_termination_requests')) {
            return;
        }

        Schema::create('partner_termination_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('termination_code', 50)->unique();
            $table->unsignedBigInteger('partner_contract_id');
            $table->unsignedBigInteger('partner_application_id')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('venue_cluster_id')->nullable();
            $table->enum('termination_type', ['mutual_agreement', 'unilateral_by_owner', 'unilateral_by_sportgo']);
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('requested_at')->useCurrent();
            $table->text('reason');
            $table->date('requested_effective_date')->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewing', 'approved', 'pending_signature', 'settlement_processing', 'settlement_completed', 'transition_period', 'completed', 'rejected', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('effective_termination_date')->nullable();
            $table->timestamp('transition_end_at')->nullable();
            $table->timestamp('owner_access_revoked_at')->nullable();
            $table->timestamps();

            $table->index(['partner_contract_id', 'status'], 'partner_term_requests_contract_status_index');
            $table->index(['owner_id', 'status'], 'partner_term_requests_owner_status_index');
            $table->index(['termination_type', 'status'], 'partner_term_requests_type_status_index');
            $table->foreign('partner_contract_id', 'partner_term_requests_contract_foreign')
                ->references('id')->on('partner_contracts')->onDelete('restrict');
            $table->foreign('partner_application_id', 'partner_term_requests_app_foreign')
                ->references('id')->on('partner_applications')->onDelete('set null');
            $table->foreign('owner_id', 'partner_term_requests_owner_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('venue_cluster_id', 'partner_term_requests_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('requested_by', 'partner_term_requests_requested_by_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by', 'partner_term_requests_approved_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_termination_requests');
    }
};
