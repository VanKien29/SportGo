<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_termination_documents')) {
            return;
        }

        Schema::create('partner_termination_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('partner_termination_request_id', 36);
            $table->char('generated_document_id', 36)->nullable();
            $table->enum('document_type', ['owner_termination_request', 'mutual_liquidation_minutes', 'unilateral_notice', 'settlement_minutes', 'final_termination_file']);
            $table->char('media_id', 36)->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->enum('status', ['generated', 'pending_signature', 'signed', 'completed', 'cancelled'])->default('generated');
            $table->char('generated_by', 36)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['partner_termination_request_id', 'document_type'], 'partner_term_docs_request_type_index');
            $table->foreign('partner_termination_request_id', 'partner_term_docs_request_foreign')
                ->references('id')->on('partner_termination_requests')->onDelete('restrict');
            $table->foreign('generated_document_id', 'partner_term_docs_generated_doc_foreign')
                ->references('id')->on('generated_documents')->onDelete('restrict');
            $table->foreign('media_id', 'partner_term_docs_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
            $table->foreign('generated_by', 'partner_term_docs_generated_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_termination_documents');
    }
};
