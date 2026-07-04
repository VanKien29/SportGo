<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE venue_court_approval_requests MODIFY status ENUM('pending','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE venue_location_change_requests MODIFY status ENUM('pending','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE venue_court_approval_requests SET status = 'rejected' WHERE status = 'need_supplement'");
        DB::statement("UPDATE venue_location_change_requests SET status = 'rejected' WHERE status = 'need_supplement'");
        DB::statement("ALTER TABLE venue_court_approval_requests MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE venue_location_change_requests MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
