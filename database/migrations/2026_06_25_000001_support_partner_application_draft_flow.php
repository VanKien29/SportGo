<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('partner_applications', 'submitted_at')) {
            DB::statement("ALTER TABLE partner_applications MODIFY submitted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Thoi diem user gui ho so sau khi da ky don.'");
        }

        if (Schema::hasColumn('notifications', 'type')) {
            DB::statement("ALTER TABLE notifications MODIFY type VARCHAR(100) NOT NULL COMMENT 'Loai thong bao.'");
        }

        if (Schema::hasTable('partner_applications') && ! Schema::hasColumn('partner_applications', 'terminated_at')) {
            Schema::table('partner_applications', function (Blueprint $table): void {
                $table->timestamp('terminated_at')->nullable()->after('reviewed_at');
            });
        }
    }

    public function down(): void
    {
        // These compatibility changes cannot be narrowed safely once draft
        // applications and longer notification types have been stored.
    }
};
