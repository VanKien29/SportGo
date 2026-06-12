<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_policies')) {
            Schema::table('system_policies', function (Blueprint $table) {
                if (! Schema::hasColumn('system_policies', 'effective_to')) {
                    $table->timestamp('effective_to')->nullable()->after('effective_from');
                }

                if (! Schema::hasColumn('system_policies', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('effective_to');
                }

                if (! Schema::hasColumn('system_policies', 'published_by')) {
                    $table->char('published_by', 36)->nullable()->after('published_at');
                }

                if (! Schema::hasColumn('system_policies', 'replaced_policy_id')) {
                    $table->char('replaced_policy_id', 36)->nullable()->after('published_by');
                }

                if (! Schema::hasColumn('system_policies', 'require_reaccept')) {
                    $table->boolean('require_reaccept')->default(false)->after('replaced_policy_id');
                }

                if (! Schema::hasColumn('system_policies', 'change_summary')) {
                    $table->text('change_summary')->nullable()->after('require_reaccept');
                }
            });

            Schema::table('system_policies', function (Blueprint $table) {
                if (Schema::hasColumn('system_policies', 'published_by')) {
                    $table->index('published_by', 'system_policies_published_by_index');
                    $table->foreign('published_by', 'system_policies_published_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }

                if (Schema::hasColumn('system_policies', 'replaced_policy_id')) {
                    $table->index('replaced_policy_id', 'system_policies_replaced_policy_id_index');
                    $table->foreign('replaced_policy_id', 'system_policies_replaced_policy_foreign')
                        ->references('id')->on('system_policies')->onDelete('set null');
                }

                if (Schema::hasColumn('system_policies', 'status')) {
                    $table->index(['status', 'is_active'], 'system_policies_status_active_index');
                }
            });
        }

        if (Schema::hasTable('policy_rules')) {
            Schema::table('policy_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('policy_rules', 'decision_key')) {
                    $table->string('decision_key', 100)->nullable()->after('rule_type');
                }

                if (! Schema::hasColumn('policy_rules', 'conflict_group')) {
                    $table->string('conflict_group', 100)->nullable()->after('decision_key');
                }

                if (! Schema::hasColumn('policy_rules', 'constraint_json')) {
                    $table->json('constraint_json')->nullable()->after('result_json');
                }

                if (! Schema::hasColumn('policy_rules', 'allowed_override_json')) {
                    $table->json('allowed_override_json')->nullable()->after('constraint_json');
                }

                if (! Schema::hasColumn('policy_rules', 'created_by')) {
                    $table->char('created_by', 36)->nullable()->after('is_active');
                }

                if (! Schema::hasColumn('policy_rules', 'updated_by')) {
                    $table->char('updated_by', 36)->nullable()->after('created_by');
                }
            });

            Schema::table('policy_rules', function (Blueprint $table) {
                if (Schema::hasColumn('policy_rules', 'decision_key')) {
                    $table->index(['action_code', 'rule_type', 'is_active', 'priority'], 'policy_rules_action_type_active_priority_index');
                    $table->index(['action_code', 'decision_key', 'conflict_group'], 'policy_rules_conflict_lookup_index');
                }

                if (Schema::hasColumn('policy_rules', 'created_by')) {
                    $table->foreign('created_by', 'policy_rules_created_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }

                if (Schema::hasColumn('policy_rules', 'updated_by')) {
                    $table->foreign('updated_by', 'policy_rules_updated_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('venue_policy_rules')) {
            Schema::table('venue_policy_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('venue_policy_rules', 'updated_by')) {
                    $table->char('updated_by', 36)->nullable()->after('created_by');
                }
            });

            Schema::table('venue_policy_rules', function (Blueprint $table) {
                if (Schema::hasColumn('venue_policy_rules', 'updated_by')) {
                    $table->foreign('updated_by', 'venue_policy_rules_updated_by_foreign')
                        ->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        if (Schema::hasTable('policy_evaluation_logs')) {
            Schema::table('policy_evaluation_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('policy_evaluation_logs', 'policy_version_snapshot')) {
                    $table->json('policy_version_snapshot')->nullable()->after('result_data');
                }

                if (! Schema::hasColumn('policy_evaluation_logs', 'rule_snapshot')) {
                    $table->json('rule_snapshot')->nullable()->after('policy_version_snapshot');
                }
            });

            Schema::table('policy_evaluation_logs', function (Blueprint $table) {
                $table->index(['action_code', 'entity_type', 'entity_id', 'created_at'], 'policy_eval_action_entity_created_index');
            });
        }

        if (Schema::hasTable('user_policy_acceptances')) {
            Schema::table('user_policy_acceptances', function (Blueprint $table) {
                if (! Schema::hasColumn('user_policy_acceptances', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('accepted_at');
                }

                if (! Schema::hasColumn('user_policy_acceptances', 'user_agent')) {
                    $table->string('user_agent', 500)->nullable()->after('ip_address');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_policy_acceptances')) {
            Schema::table('user_policy_acceptances', function (Blueprint $table) {
                foreach (['user_agent', 'ip_address'] as $column) {
                    if (Schema::hasColumn('user_policy_acceptances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('policy_evaluation_logs')) {
            Schema::table('policy_evaluation_logs', function (Blueprint $table) {
                try {
                    $table->dropIndex('policy_eval_action_entity_created_index');
                } catch (Throwable) {
                }

                foreach (['rule_snapshot', 'policy_version_snapshot'] as $column) {
                    if (Schema::hasColumn('policy_evaluation_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('venue_policy_rules')) {
            Schema::table('venue_policy_rules', function (Blueprint $table) {
                try {
                    $table->dropForeign('venue_policy_rules_updated_by_foreign');
                } catch (Throwable) {
                }

                if (Schema::hasColumn('venue_policy_rules', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }

        if (Schema::hasTable('policy_rules')) {
            Schema::table('policy_rules', function (Blueprint $table) {
                foreach ([
                    'policy_rules_created_by_foreign',
                    'policy_rules_updated_by_foreign',
                ] as $foreign) {
                    try {
                        $table->dropForeign($foreign);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'policy_rules_action_type_active_priority_index',
                    'policy_rules_conflict_lookup_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'updated_by',
                    'created_by',
                    'allowed_override_json',
                    'constraint_json',
                    'conflict_group',
                    'decision_key',
                ] as $column) {
                    if (Schema::hasColumn('policy_rules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('system_policies')) {
            Schema::table('system_policies', function (Blueprint $table) {
                foreach ([
                    'system_policies_published_by_foreign',
                    'system_policies_replaced_policy_foreign',
                ] as $foreign) {
                    try {
                        $table->dropForeign($foreign);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'system_policies_status_active_index',
                    'system_policies_published_by_index',
                    'system_policies_replaced_policy_id_index',
                ] as $index) {
                    try {
                        $table->dropIndex($index);
                    } catch (Throwable) {
                    }
                }

                foreach ([
                    'change_summary',
                    'require_reaccept',
                    'replaced_policy_id',
                    'published_by',
                    'published_at',
                    'effective_to',
                ] as $column) {
                    if (Schema::hasColumn('system_policies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
