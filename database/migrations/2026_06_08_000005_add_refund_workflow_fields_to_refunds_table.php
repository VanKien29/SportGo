<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE refunds MODIFY status ENUM('pending_confirmation','processing','completed','failed','rejected','pending_owner_confirmation','owner_confirmed','owner_rejected','admin_processing','cancelled') NOT NULL DEFAULT 'pending_owner_confirmation'");
        }

        Schema::table('refunds', function (Blueprint $table): void {
            if (! Schema::hasColumn('refunds', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('admin_confirmed_at');
            }
            if (! Schema::hasColumn('refunds', 'policy_id')) {
                $table->char('policy_id', 36)->nullable()->after('owner_wallet_ledger_id');
            }
            if (! Schema::hasColumn('refunds', 'policy_rule_id')) {
                $table->char('policy_rule_id', 36)->nullable()->after('policy_id');
            }
            if (! Schema::hasColumn('refunds', 'policy_evaluation_log_id')) {
                $table->char('policy_evaluation_log_id', 36)->nullable()->after('policy_rule_id');
            }
        });

        Schema::table('refunds', function (Blueprint $table): void {
            if (Schema::hasColumn('refunds', 'policy_id')) {
                $table->index('policy_id', 'refunds_policy_id_index');
                $table->foreign('policy_id', 'refunds_policy_foreign')
                    ->references('id')->on('system_policies')->onDelete('set null');
            }
            if (Schema::hasColumn('refunds', 'policy_rule_id')) {
                $table->index('policy_rule_id', 'refunds_policy_rule_id_index');
                $table->foreign('policy_rule_id', 'refunds_policy_rule_foreign')
                    ->references('id')->on('policy_rules')->onDelete('set null');
            }
            if (Schema::hasColumn('refunds', 'policy_evaluation_log_id')) {
                $table->index('policy_evaluation_log_id', 'refunds_policy_evaluation_log_id_index');
                $table->foreign('policy_evaluation_log_id', 'refunds_policy_eval_log_foreign')
                    ->references('id')->on('policy_evaluation_logs')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('refunds')) {
            return;
        }

        Schema::table('refunds', function (Blueprint $table): void {
            foreach ([
                'refunds_policy_foreign',
                'refunds_policy_rule_foreign',
                'refunds_policy_eval_log_foreign',
            ] as $foreign) {
                try {
                    $table->dropForeign($foreign);
                } catch (Throwable) {
                    // Foreign key may not exist on partially migrated databases.
                }
            }

            foreach ([
                'refunds_policy_id_index',
                'refunds_policy_rule_id_index',
                'refunds_policy_evaluation_log_id_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    // Index may not exist on partially migrated databases.
                }
            }
        });

        Schema::table('refunds', function (Blueprint $table): void {
            foreach (['policy_evaluation_log_id', 'policy_rule_id', 'policy_id', 'completed_at'] as $column) {
                if (Schema::hasColumn('refunds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('refunds')
                ->whereIn('status', ['pending_owner_confirmation', 'owner_confirmed'])
                ->update(['status' => 'pending_confirmation']);
            DB::table('refunds')
                ->where('status', 'admin_processing')
                ->update(['status' => 'processing']);
            DB::table('refunds')
                ->whereIn('status', ['owner_rejected', 'cancelled'])
                ->update(['status' => 'rejected']);
            DB::statement("ALTER TABLE refunds MODIFY status ENUM('pending_confirmation','processing','completed','failed','rejected') NOT NULL DEFAULT 'pending_confirmation'");
        }
    }
};
