<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_court_approval_requests', 'change_type')) {
                $table->string('change_type', 30)->default('add')->after('name');
            }

            if (! Schema::hasColumn('venue_court_approval_requests', 'requested_courts')) {
                $table->json('requested_courts')->nullable()->after('change_type');
            }

            if (! Schema::hasColumn('venue_court_approval_requests', 'removed_court_ids')) {
                $table->json('removed_court_ids')->nullable()->after('requested_courts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venue_court_approval_requests', function (Blueprint $table): void {
            foreach (['removed_court_ids', 'requested_courts', 'change_type'] as $column) {
                if (Schema::hasColumn('venue_court_approval_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
