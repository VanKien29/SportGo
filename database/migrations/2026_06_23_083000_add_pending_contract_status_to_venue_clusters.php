<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('venue_clusters')) {
            DB::statement("ALTER TABLE venue_clusters MODIFY COLUMN status ENUM('pending', 'active', 'locked', 'pending_contract') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('venue_clusters')) {
            DB::table('venue_clusters')->where('status', 'pending_contract')->update(['status' => 'pending']);
            DB::statement("ALTER TABLE venue_clusters MODIFY COLUMN status ENUM('pending', 'active', 'locked') NOT NULL DEFAULT 'pending'");
        }
    }
};
