<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'expired' and 'left' to participant status enum
        DB::statement("ALTER TABLE player_post_participants MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled','expired','left') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Convert 'expired' back to 'cancelled' and 'left' back to 'cancelled' before shrinking enum
        DB::table('player_post_participants')->where('status', 'expired')->update(['status' => 'cancelled']);
        DB::table('player_post_participants')->where('status', 'left')->update(['status' => 'cancelled']);
        DB::statement("ALTER TABLE player_post_participants MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
