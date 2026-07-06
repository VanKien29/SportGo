<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_court_approval_requests', 'generated_document_id')) {
                $table->char('generated_document_id', 36)->nullable()->after('signed_at');
                $table->index('generated_document_id', 'venue_court_approval_generated_document_index');
                $table->foreign('generated_document_id', 'venue_court_approval_generated_document_foreign')
                    ->references('id')->on('generated_documents')->nullOnDelete();
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_location_change_requests', 'generated_document_id')) {
                $table->char('generated_document_id', 36)->nullable()->after('signed_at');
                $table->index('generated_document_id', 'venue_location_change_generated_document_index');
                $table->foreign('generated_document_id', 'venue_location_change_generated_document_foreign')
                    ->references('id')->on('generated_documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('venue_court_approval_requests', 'generated_document_id')) {
                $table->dropForeign('venue_court_approval_generated_document_foreign');
                $table->dropIndex('venue_court_approval_generated_document_index');
                $table->dropColumn('generated_document_id');
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('venue_location_change_requests', 'generated_document_id')) {
                $table->dropForeign('venue_location_change_generated_document_foreign');
                $table->dropIndex('venue_location_change_generated_document_index');
                $table->dropColumn('generated_document_id');
            }
        });
    }
};
