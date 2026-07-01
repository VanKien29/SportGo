<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_court_approval_requests', 'supplementary_documents')) {
                $table->json('supplementary_documents')->nullable()->after('evidence_image');
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_location_change_requests', 'supplementary_documents')) {
                $table->json('supplementary_documents')->nullable()->after('new_map_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('venue_court_approval_requests', 'supplementary_documents')) {
                $table->dropColumn('supplementary_documents');
            }
        });

        Schema::table('venue_location_change_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('venue_location_change_requests', 'supplementary_documents')) {
                $table->dropColumn('supplementary_documents');
            }
        });
    }
};
