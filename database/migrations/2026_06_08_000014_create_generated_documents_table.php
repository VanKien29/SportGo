<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_documents')) {
            return;
        }

        Schema::create('generated_documents', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('document_code', 50)->unique();
            $table->string('document_type', 100);
            $table->char('template_id', 36);
            $table->unsignedInteger('template_version');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 100)->nullable();
            $table->char('partner_application_id', 36)->nullable();
            $table->char('partner_contract_id', 36)->nullable();
            $table->char('partner_termination_request_id', 36)->nullable();
            $table->char('partner_settlement_id', 36)->nullable();
            $table->char('owner_id', 36)->nullable();
            $table->char('venue_cluster_id', 36)->nullable();
            $table->string('title', 255)->nullable();
            $table->enum('status', ['draft', 'generated', 'pending_owner_signature', 'pending_sportgo_signature', 'signed', 'completed', 'cancelled'])->default('generated');
            $table->json('render_data');
            $table->char('generated_file_media_id', 36)->nullable();
            $table->char('signed_file_media_id', 36)->nullable();
            $table->char('final_file_media_id', 36)->nullable();
            $table->string('generated_file_path', 1000)->nullable();
            $table->string('final_file_path', 1000)->nullable();
            $table->string('file_hash', 128)->nullable();
            $table->char('generated_by', 36)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'generated_documents_reference_index');
            $table->index(['entity_type', 'entity_id'], 'generated_documents_entity_index');
            $table->index('partner_application_id', 'generated_documents_application_index');
            $table->index('partner_contract_id', 'generated_documents_contract_index');
            $table->index('partner_termination_request_id', 'generated_documents_termination_index');
            $table->index('partner_settlement_id', 'generated_documents_settlement_index');
            $table->index(['owner_id', 'venue_cluster_id'], 'generated_documents_owner_cluster_index');
            $table->index(['document_type', 'status'], 'generated_documents_type_status_index');
            $table->foreign('template_id', 'generated_documents_template_foreign')
                ->references('id')->on('document_templates')->onDelete('restrict');
            $table->foreign('partner_application_id', 'generated_documents_application_foreign')
                ->references('id')->on('partner_applications')->onDelete('set null');
            $table->foreign('owner_id', 'generated_documents_owner_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('venue_cluster_id', 'generated_documents_cluster_foreign')
                ->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('generated_file_media_id', 'generated_documents_generated_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
            $table->foreign('signed_file_media_id', 'generated_documents_signed_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
            $table->foreign('final_file_media_id', 'generated_documents_final_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
            $table->foreign('generated_by', 'generated_documents_generated_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
