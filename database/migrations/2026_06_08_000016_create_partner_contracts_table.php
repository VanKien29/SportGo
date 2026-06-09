<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_contracts')) {
            return;
        }

        Schema::create('partner_contracts', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('contract_code', 50)->unique();
            $table->char('partner_application_id', 36);
            $table->char('owner_id', 36);
            $table->char('venue_cluster_id', 36)->nullable();
            $table->string('contract_title', 255);
            $table->enum('status', ['draft', 'generated', 'pending_owner_signature', 'pending_sportgo_signature', 'signed_active', 'cancelled', 'terminated'])->default('draft');
            $table->char('generated_document_id', 36)->nullable();
            $table->char('generated_file_media_id', 36)->nullable();
            $table->char('signed_file_media_id', 36)->nullable();
            $table->char('final_file_media_id', 36)->nullable();
            $table->char('generated_by', 36)->nullable();
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('owner_signed_at')->nullable();
            $table->timestamp('sportgo_signed_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['partner_application_id', 'status'], 'partner_contracts_app_status_index');
            $table->index(['owner_id', 'status'], 'partner_contracts_owner_status_index');
            $table->foreign('partner_application_id', 'partner_contracts_app_foreign')
                ->references('id')->on('partner_applications')->onDelete('restrict');
            $table->foreign('owner_id', 'partner_contracts_owner_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('venue_cluster_id', 'partner_contracts_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('generated_document_id', 'partner_contracts_generated_doc_foreign')
                ->references('id')->on('generated_documents')->onDelete('restrict');
            $table->foreign('generated_by', 'partner_contracts_generated_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by', 'partner_contracts_approved_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contracts');
    }
};
