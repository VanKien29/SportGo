<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_documents')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('generated_documents', 'generated_pdf_path')) {
                    $table->string('generated_pdf_path', 1000)->nullable()->after('generated_file_path');
                }
                if (! Schema::hasColumn('generated_documents', 'final_pdf_path')) {
                    $table->string('final_pdf_path', 1000)->nullable()->after('final_file_path');
                }
                if (! Schema::hasColumn('generated_documents', 'pdf_hash')) {
                    $table->string('pdf_hash', 128)->nullable()->after('file_hash');
                }
                if (! Schema::hasColumn('generated_documents', 'final_pdf_hash')) {
                    $table->string('final_pdf_hash', 128)->nullable()->after('pdf_hash');
                }
                if (! Schema::hasColumn('generated_documents', 'pdf_generated_at')) {
                    $table->timestamp('pdf_generated_at')->nullable()->after('generated_at');
                }
                if (! Schema::hasColumn('generated_documents', 'pdf_locked_at')) {
                    $table->timestamp('pdf_locked_at')->nullable()->after('locked_at');
                }
            });
        }

        if (Schema::hasTable('partner_application_documents')) {
            Schema::table('partner_application_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('partner_application_documents', 'pdf_file_path')) {
                    $table->string('pdf_file_path', 1000)->nullable()->after('file_path');
                }
                if (! Schema::hasColumn('partner_application_documents', 'pdf_hash')) {
                    $table->string('pdf_hash', 128)->nullable()->after('pdf_file_path');
                }
                if (! Schema::hasColumn('partner_application_documents', 'pdf_generated_at')) {
                    $table->timestamp('pdf_generated_at')->nullable()->after('pdf_hash');
                }
            });
        }

        if (! Schema::hasTable('document_access_logs')) {
            Schema::create('document_access_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generated_document_id')->nullable();
                $table->unsignedBigInteger('partner_application_document_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 30);
                $table->string('delivery', 30)->default('pdf');
                $table->string('file_hash', 128)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['generated_document_id', 'action'], 'document_access_generated_action_index');
                $table->index(['partner_application_document_id', 'action'], 'document_access_uploaded_action_index');
                $table->index(['user_id', 'created_at'], 'document_access_user_created_index');
                $table->foreign('generated_document_id', 'document_access_generated_foreign')
                    ->references('id')->on('generated_documents')->nullOnDelete();
                $table->foreign('partner_application_document_id', 'document_access_uploaded_foreign')
                    ->references('id')->on('partner_application_documents')->nullOnDelete();
                $table->foreign('user_id', 'document_access_user_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');

        if (Schema::hasTable('partner_application_documents')) {
            Schema::table('partner_application_documents', function (Blueprint $table): void {
                foreach (['pdf_generated_at', 'pdf_hash', 'pdf_file_path'] as $column) {
                    if (Schema::hasColumn('partner_application_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('generated_documents')) {
            Schema::table('generated_documents', function (Blueprint $table): void {
                foreach (['pdf_locked_at', 'pdf_generated_at', 'final_pdf_hash', 'pdf_hash', 'final_pdf_path', 'generated_pdf_path'] as $column) {
                    if (Schema::hasColumn('generated_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
