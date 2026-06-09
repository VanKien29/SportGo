<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_document_signatures')) {
            return;
        }

        Schema::create('generated_document_signatures', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('generated_document_id', 36);
            $table->enum('signer_side', ['owner', 'sportgo', 'witness', 'system']);
            $table->char('signer_user_id', 36)->nullable();
            $table->string('signer_full_name', 255);
            $table->string('signer_title', 255)->nullable();
            $table->string('signer_organization', 255)->nullable();
            $table->enum('signature_method', ['uploaded_image', 'drawn', 'typed_confirm', 'otp_confirm', 'digital'])->default('typed_confirm');
            $table->char('signature_media_id', 36)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->enum('status', ['pending', 'signed', 'rejected', 'cancelled'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['generated_document_id', 'signer_side', 'status'], 'generated_doc_signatures_doc_side_status_index');
            $table->foreign('generated_document_id', 'generated_doc_signatures_doc_foreign')
                ->references('id')->on('generated_documents')->onDelete('restrict');
            $table->foreign('signer_user_id', 'generated_doc_signatures_user_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('signature_media_id', 'generated_doc_signatures_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_document_signatures');
    }
};
