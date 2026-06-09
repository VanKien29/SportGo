<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_policy_rules')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE venue_policy_rules MODIFY status ENUM('draft','pending_review','active','inactive','rejected','archived') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('venue_policy_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('venue_policy_rules', 'submitted_by')) {
                $table->char('submitted_by', 36)->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'reviewed_by')) {
                $table->char('reviewed_by', 36)->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'effective_from')) {
                $table->timestamp('effective_from')->nullable()->after('reject_reason');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'effective_to')) {
                $table->timestamp('effective_to')->nullable()->after('effective_from');
            }
            if (! Schema::hasColumn('venue_policy_rules', 'constraint_check_result')) {
                $table->json('constraint_check_result')->nullable()->after('effective_to');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venue_policy_rules')) {
            return;
        }

        Schema::table('venue_policy_rules', function (Blueprint $table): void {
            foreach ([
                'constraint_check_result',
                'effective_to',
                'effective_from',
                'reject_reason',
                'reviewed_at',
                'reviewed_by',
                'submitted_at',
                'submitted_by',
            ] as $column) {
                if (Schema::hasColumn('venue_policy_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('venue_policy_rules')
                ->whereIn('status', ['pending_review', 'archived'])
                ->update(['status' => 'draft']);
            DB::statement("ALTER TABLE venue_policy_rules MODIFY status ENUM('draft','active','inactive','rejected') NOT NULL DEFAULT 'draft'");
        }
    }
};
