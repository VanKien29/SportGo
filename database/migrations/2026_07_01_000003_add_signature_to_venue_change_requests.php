<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_court_approval_requests', 'signature_image')) {
                $table->string('signature_image', 500)->nullable()->after('supplementary_documents');
            }
            if (! Schema::hasColumn('venue_court_approval_requests', 'signature_hash')) {
                $table->string('signature_hash', 64)->nullable()->after('signature_image');
            }
            if (! Schema::hasColumn('venue_court_approval_requests', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_hash');
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_location_change_requests', 'signature_image')) {
                $table->string('signature_image', 500)->nullable()->after('supplementary_documents');
            }
            if (! Schema::hasColumn('venue_location_change_requests', 'signature_hash')) {
                $table->string('signature_hash', 64)->nullable()->after('signature_image');
            }
            if (! Schema::hasColumn('venue_location_change_requests', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('signature_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            foreach (['signed_at', 'signature_hash', 'signature_image'] as $column) {
                if (Schema::hasColumn('venue_court_approval_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            foreach (['signed_at', 'signature_hash', 'signature_image'] as $column) {
                if (Schema::hasColumn('venue_location_change_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};