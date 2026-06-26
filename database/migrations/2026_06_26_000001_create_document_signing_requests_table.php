<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_documents') && ! Schema::hasColumn('generated_documents', 'document_version')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                $table->unsignedInteger('document_version')->default(1)->after('template_version');
            });
        }

        if (Schema::hasTable('document_signing_requests')) {
            return;
        }

        Schema::create('document_signing_requests', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('generated_document_id', 36);
            $table->unsignedBigInteger('verification_code_id')->nullable();
            $table->char('user_id', 36);
            $table->string('signer_side', 50);
            $table->string('action', 100);
            $table->string('document_type', 100);
            $table->string('document_code', 50);
            $table->unsignedInteger('document_version')->default(1);
            $table->string('file_hash', 128);
            $table->string('file_hash_after', 128)->nullable();
            $table->string('nonce', 100)->unique();
            $table->string('otp_type', 150);
            $table->string('otp_channel', 20)->default('email');
            $table->string('otp_identifier', 255);
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status', 50)->default('otp_sent');
            $table->text('checkbox_text')->nullable();
            $table->longText('signature_image')->nullable();
            $table->char('signed_signature_id', 36)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['generated_document_id', 'signer_side', 'status'], 'doc_sign_req_doc_side_status_index');
            $table->index(['user_id', 'status'], 'doc_sign_req_user_status_index');
            $table->index(['document_type', 'status'], 'doc_sign_req_type_status_index');
            $table->foreign('generated_document_id', 'doc_sign_req_document_foreign')
                ->references('id')->on('generated_documents')->onDelete('cascade');
            $table->foreign('verification_code_id', 'doc_sign_req_otp_foreign')
                ->references('id')->on('verification_codes')->onDelete('set null');
            $table->foreign('user_id', 'doc_sign_req_user_foreign')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('signed_signature_id', 'doc_sign_req_signature_foreign')
                ->references('id')->on('generated_document_signatures')->onDelete('set null');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE generated_documents MODIFY status VARCHAR(80) NOT NULL DEFAULT 'generated'");
            DB::statement("ALTER TABLE verification_codes MODIFY type VARCHAR(150) NOT NULL COMMENT 'Muc dich ma OTP, ho tro type kem nonce cho ky van ban.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signing_requests');

        if (Schema::hasTable('generated_documents') && Schema::hasColumn('generated_documents', 'document_version')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                $table->dropColumn('document_version');
            });
        }
    }
};
