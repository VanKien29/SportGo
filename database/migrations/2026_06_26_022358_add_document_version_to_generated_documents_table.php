<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('generated_documents', 'document_version')) {
                $table->unsignedInteger('document_version')->default(1)->after('document_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            if (Schema::hasColumn('generated_documents', 'document_version')) {
                $table->dropColumn('document_version');
            }
        });
    }
};
