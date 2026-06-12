<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_application_documents')) {
            return;
        }

        Schema::create('partner_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('partner_application_id', 36);
            $table->char('media_id', 36)->nullable();
            $table->string('document_type', 100);
            $table->string('document_group', 100);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->enum('status', ['uploaded', 'verified', 'rejected'])->default('uploaded');
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['partner_application_id', 'document_group'], 'partner_app_docs_app_group_index');
            $table->index(['document_type', 'status'], 'partner_app_docs_type_status_index');
            $table->foreign('partner_application_id', 'partner_app_docs_app_foreign')
                ->references('id')->on('partner_applications')->onDelete('restrict');
            $table->foreign('media_id', 'partner_app_docs_media_foreign')
                ->references('id')->on('media')->onDelete('set null');
            $table->foreign('reviewed_by', 'partner_app_docs_reviewed_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_application_documents');
    }
};
