<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'actor_type')) {
                $table->enum('actor_type', ['user', 'owner', 'venue_staff', 'admin', 'super_admin', 'system'])
                    ->nullable()->after('actor_id')
                    ->comment('Loại actor thực hiện hành động; actor_id nullable nếu system.');
            }

            if (! Schema::hasColumn('audit_logs', 'module')) {
                $table->string('module', 50)->nullable()->after('action')
                    ->comment('Module nghiệp vụ như auth, booking, payment, policy.');
            }

            if (! Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('new_values')
                    ->comment('Dữ liệu ngữ cảnh bổ sung cho audit.');
            }

            if (! Schema::hasColumn('audit_logs', 'reason')) {
                $table->text('reason')->nullable()->after('metadata')
                    ->comment('Lý do thao tác, đặc biệt cho từ chối/khóa/hủy.');
            }

            if (! Schema::hasColumn('audit_logs', 'policy_id')) {
                $table->char('policy_id', 36)->nullable()->after('reason')
                    ->comment('Chính sách chi phối hành động nếu có.');
            }

            if (! Schema::hasColumn('audit_logs', 'policy_rule_id')) {
                $table->char('policy_rule_id', 36)->nullable()->after('policy_id')
                    ->comment('Rule chi phối hành động nếu có.');
            }

            if (! Schema::hasColumn('audit_logs', 'policy_evaluation_log_id')) {
                $table->char('policy_evaluation_log_id', 36)->nullable()->after('policy_rule_id')
                    ->comment('Lần evaluate policy tạo ra hành động nếu có.');
            }

            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('policy_evaluation_log_id')
                    ->comment('ID request để trace log cùng một request.');
            }

            if (! Schema::hasColumn('audit_logs', 'severity')) {
                $table->enum('severity', ['info', 'warning', 'critical'])->default('info')->after('request_id')
                    ->comment('Mức độ nghiêm trọng của audit log.');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'actor_type')) {
                $table->index('actor_type', 'audit_logs_actor_type_index');
            }
            if (Schema::hasColumn('audit_logs', 'module')) {
                $table->index('module', 'audit_logs_module_index');
            }
            if (Schema::hasColumn('audit_logs', 'policy_id')) {
                $table->index('policy_id', 'audit_logs_policy_id_index');
                $table->foreign('policy_id', 'audit_logs_policy_foreign')
                    ->references('id')->on('system_policies')->onDelete('set null');
            }
            if (Schema::hasColumn('audit_logs', 'policy_rule_id')) {
                $table->index('policy_rule_id', 'audit_logs_policy_rule_id_index');
                $table->foreign('policy_rule_id', 'audit_logs_policy_rule_foreign')
                    ->references('id')->on('policy_rules')->onDelete('set null');
            }
            if (Schema::hasColumn('audit_logs', 'policy_evaluation_log_id')) {
                $table->index('policy_evaluation_log_id', 'audit_logs_policy_eval_id_index');
                $table->foreign('policy_evaluation_log_id', 'audit_logs_policy_eval_foreign')
                    ->references('id')->on('policy_evaluation_logs')->onDelete('set null');
            }
            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->index('request_id', 'audit_logs_request_id_index');
            }
            if (Schema::hasColumn('audit_logs', 'severity')) {
                $table->index('severity', 'audit_logs_severity_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            foreach ([
                'audit_logs_policy_foreign',
                'audit_logs_policy_rule_foreign',
                'audit_logs_policy_eval_foreign',
            ] as $foreign) {
                try {
                    $table->dropForeign($foreign);
                } catch (Throwable) {
                }
            }

            foreach ([
                'audit_logs_actor_type_index',
                'audit_logs_module_index',
                'audit_logs_policy_id_index',
                'audit_logs_policy_rule_id_index',
                'audit_logs_policy_eval_id_index',
                'audit_logs_request_id_index',
                'audit_logs_severity_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                }
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            foreach ([
                'severity',
                'request_id',
                'policy_evaluation_log_id',
                'policy_rule_id',
                'policy_id',
                'reason',
                'metadata',
                'module',
                'actor_type',
            ] as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
