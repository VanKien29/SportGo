<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_court_approval_requests')) {
            DB::statement("ALTER TABLE venue_court_approval_requests MODIFY status ENUM('pending','approved_pending_appendix','pending_owner_signature','completed','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasTable('venue_location_change_requests')) {
            DB::statement("ALTER TABLE venue_location_change_requests MODIFY status ENUM('pending','approved_pending_appendix','pending_owner_signature','completed','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venue_court_approval_requests')) {
            DB::statement("UPDATE venue_court_approval_requests SET status = 'approved' WHERE status IN ('approved_pending_appendix','pending_owner_signature','completed')");
            DB::statement("ALTER TABLE venue_court_approval_requests MODIFY status ENUM('pending','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasTable('venue_location_change_requests')) {
            DB::statement("UPDATE venue_location_change_requests SET status = 'approved' WHERE status IN ('approved_pending_appendix','pending_owner_signature','completed')");
            DB::statement("ALTER TABLE venue_location_change_requests MODIFY status ENUM('pending','approved','rejected','cancelled','need_supplement') NOT NULL DEFAULT 'pending'");
        }
    }
};
