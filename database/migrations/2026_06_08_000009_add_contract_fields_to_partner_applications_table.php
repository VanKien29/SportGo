<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'current_contract_id')) {
                $table->char('current_contract_id', 36)->nullable()->after('approved_venue_cluster_id');
                $table->index('current_contract_id', 'partner_applications_current_contract_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications') || ! Schema::hasColumn('partner_applications', 'current_contract_id')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            $table->dropIndex('partner_applications_current_contract_index');
            $table->dropColumn('current_contract_id');
        });
    }
};
