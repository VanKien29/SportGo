<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_templates')) {
            return;
        }

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('template_code', 100);
            $table->string('document_type', 100);
            $table->string('template_name', 255);
            $table->unsignedInteger('version')->default(1);
            $table->string('file_name', 255);
            $table->string('file_path', 1000);
            $table->enum('output_format', ['docx', 'pdf'])->default('docx');
            $table->string('mime_type', 150)->default('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $table->string('storage_disk', 50)->default('local');
            $table->json('template_variables')->nullable();
            $table->json('required_fields')->nullable();
            $table->enum('render_engine', ['docx_placeholder', 'manual_upload', 'pdf_static'])->default('docx_placeholder');
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');
            $table->boolean('is_active')->default(false);
            $table->char('created_by', 36)->nullable();
            $table->char('uploaded_by', 36)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->char('replaced_template_id', 36)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['document_type', 'version'], 'document_templates_type_version_unique');
            $table->unique('template_code', 'document_templates_code_unique');
            $table->index(['document_type', 'status', 'is_active'], 'document_templates_type_status_active_index');
            $table->foreign('created_by', 'document_templates_created_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('uploaded_by', 'document_templates_uploaded_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('replaced_template_id', 'document_templates_replaced_foreign')
                ->references('id')->on('document_templates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
