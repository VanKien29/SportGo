<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_applications') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE partner_applications MODIFY status ENUM('pending','reviewing','approved','rejected','cancelled','draft','submitted','need_supplement','approved_pending_contract','contract_pending_owner_signature','contract_pending_sportgo_signature','completed') NOT NULL DEFAULT 'submitted'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('partner_applications')
            ->whereIn('status', ['draft', 'submitted', 'need_supplement'])
            ->update(['status' => 'pending']);
        DB::table('partner_applications')
            ->whereIn('status', ['approved_pending_contract', 'contract_pending_owner_signature', 'contract_pending_sportgo_signature', 'completed'])
            ->update(['status' => 'approved']);

        DB::statement("ALTER TABLE partner_applications MODIFY status ENUM('pending','reviewing','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
