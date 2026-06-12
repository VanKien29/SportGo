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
                if (! Schema::hasColumn('system_policies', 'policy_type')) {
                    $table->string('policy_type', 50)->nullable()->after('type')
                        ->comment('Nhóm nghiệp vụ của chính sách dùng cho rule engine.');
                }

                if (! Schema::hasColumn('system_policies', 'is_overridable')) {
                    $table->boolean('is_overridable')->default(false)->after('policy_type')
                        ->comment('Cho phép sân cấu hình rule override trong phạm vi hệ thống cho phép.');
                }

                if (! Schema::hasColumn('system_policies', 'priority')) {
                    $table->integer('priority')->default(0)->after('is_overridable')
                        ->comment('Độ ưu tiên khi nhiều chính sách cùng áp dụng.');
                }

                if (! Schema::hasColumn('system_policies', 'status')) {
                    $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('active')->after('is_active')
                        ->comment('Trạng thái vòng đời của chính sách.');
                }
            });
        }

        if (! Schema::hasTable('policy_action_bindings')) {
            Schema::create('policy_action_bindings', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('system_policy_id', 36)->comment('Chính sách hệ thống được bind với action.');
                $table->string('module', 50)->comment('Module nghiệp vụ như booking, refund, report.');
                $table->string('action_code', 100)->comment('Mã action như booking.cancel, refund.request.');
                $table->text('description')->nullable()->comment('Mô tả ngắn action/policy binding.');
                $table->boolean('is_active')->default(true)->comment('Binding đang có hiệu lực.');
                $table->timestamps();

                $table->unique(['system_policy_id', 'action_code'], 'policy_action_bindings_policy_action_unique');
                $table->index(['module', 'action_code'], 'policy_action_bindings_module_action_index');
                $table->index('is_active', 'policy_action_bindings_is_active_index');
                $table->foreign('system_policy_id', 'policy_action_bindings_policy_foreign')
                    ->references('id')->on('system_policies')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('policy_rules')) {
            Schema::create('policy_rules', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('system_policy_id', 36)->comment('Chính sách hệ thống sở hữu rule.');
                $table->string('action_code', 100)->comment('Action mà rule áp dụng.');
                $table->string('rule_code', 100)->comment('Mã rule duy nhất trong cùng policy.');
                $table->string('rule_name', 255)->comment('Tên rule dễ đọc.');
                $table->string('rule_type', 50)->comment('Loại evaluator xử lý rule.');
                $table->json('condition_json')->nullable()->comment('Điều kiện có cấu trúc để backend evaluate.');
                $table->json('result_json')->nullable()->comment('Kết quả/gợi ý xử lý khi rule match.');
                $table->integer('priority')->default(0)->comment('Độ ưu tiên rule.');
                $table->boolean('is_active')->default(true)->comment('Rule đang có hiệu lực.');
                $table->timestamps();

                $table->unique(['system_policy_id', 'rule_code'], 'policy_rules_policy_rule_code_unique');
                $table->index(['action_code', 'is_active'], 'policy_rules_action_active_index');
                $table->index(['rule_type', 'priority'], 'policy_rules_type_priority_index');
                $table->foreign('system_policy_id', 'policy_rules_policy_foreign')
                    ->references('id')->on('system_policies')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('venue_policy_rules')) {
            Schema::create('venue_policy_rules', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('venue_cluster_id', 36)->comment('Cụm sân cấu hình rule riêng.');
                $table->char('base_policy_rule_id', 36)->nullable()->comment('Rule hệ thống được override nếu được phép.');
                $table->string('action_code', 100)->comment('Action mà rule sân áp dụng.');
                $table->string('rule_code', 100)->comment('Mã rule sân.');
                $table->string('rule_name', 255)->comment('Tên rule sân.');
                $table->string('rule_type', 50)->comment('Loại evaluator xử lý rule.');
                $table->json('condition_json')->nullable()->comment('Điều kiện do owner cấu hình qua form.');
                $table->json('result_json')->nullable()->comment('Kết quả/gợi ý xử lý khi rule sân match.');
                $table->enum('status', ['draft', 'active', 'inactive', 'rejected'])->default('draft')
                    ->comment('Trạng thái duyệt rule sân.');
                $table->char('approved_by', 36)->nullable()->comment('Admin duyệt rule sân.');
                $table->timestamp('approved_at')->nullable()->comment('Thời điểm duyệt rule sân.');
                $table->text('rejected_reason')->nullable()->comment('Lý do từ chối rule sân.');
                $table->char('created_by', 36)->nullable()->comment('Owner/nhân viên tạo rule sân.');
                $table->timestamps();

                $table->index(['venue_cluster_id', 'status'], 'venue_policy_rules_cluster_status_index');
                $table->index(['action_code', 'status'], 'venue_policy_rules_action_status_index');
                $table->index('base_policy_rule_id', 'venue_policy_rules_base_rule_index');
                $table->foreign('venue_cluster_id', 'venue_policy_rules_cluster_foreign')
                    ->references('id')->on('venue_clusters')->onDelete('cascade');
                $table->foreign('base_policy_rule_id', 'venue_policy_rules_base_rule_foreign')
                    ->references('id')->on('policy_rules')->onDelete('set null');
                $table->foreign('approved_by', 'venue_policy_rules_approved_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'venue_policy_rules_created_by_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('policy_evaluation_logs')) {
            Schema::create('policy_evaluation_logs', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('system_policy_id', 36)->nullable()->comment('Chính sách hệ thống đã evaluate.');
                $table->char('policy_rule_id', 36)->nullable()->comment('Rule hệ thống đã evaluate.');
                $table->char('venue_policy_rule_id', 36)->nullable()->comment('Rule sân đã evaluate nếu có.');
                $table->string('action_code', 100)->comment('Action được evaluate.');
                $table->string('entity_type', 100)->comment('Loại đối tượng nghiệp vụ được evaluate.');
                $table->string('entity_id', 100)->comment('ID đối tượng nghiệp vụ được evaluate.');
                $table->json('input_data')->nullable()->comment('Dữ liệu đầu vào của lần evaluate.');
                $table->json('result_data')->nullable()->comment('Kết quả evaluate.');
                $table->enum('evaluated_by_type', ['user', 'owner', 'venue_staff', 'admin', 'super_admin', 'system'])
                    ->default('system')->comment('Loại actor kích hoạt evaluate.');
                $table->char('evaluated_by_id', 36)->nullable()->comment('User kích hoạt evaluate, nullable nếu system.');
                $table->timestamp('created_at')->nullable();

                $table->index(['action_code', 'created_at'], 'policy_eval_logs_action_created_index');
                $table->index(['entity_type', 'entity_id'], 'policy_eval_logs_entity_index');
                $table->index('system_policy_id', 'policy_eval_logs_policy_index');
                $table->index('policy_rule_id', 'policy_eval_logs_rule_index');
                $table->index('venue_policy_rule_id', 'policy_eval_logs_venue_rule_index');
                $table->index(['evaluated_by_type', 'created_at'], 'policy_eval_logs_actor_type_created_index');
                $table->foreign('system_policy_id', 'policy_eval_logs_policy_foreign')
                    ->references('id')->on('system_policies')->onDelete('set null');
                $table->foreign('policy_rule_id', 'policy_eval_logs_rule_foreign')
                    ->references('id')->on('policy_rules')->onDelete('set null');
                $table->foreign('venue_policy_rule_id', 'policy_eval_logs_venue_rule_foreign')
                    ->references('id')->on('venue_policy_rules')->onDelete('set null');
                $table->foreign('evaluated_by_id', 'policy_eval_logs_actor_foreign')
                    ->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_evaluation_logs');
        Schema::dropIfExists('venue_policy_rules');
        Schema::dropIfExists('policy_rules');
        Schema::dropIfExists('policy_action_bindings');

        if (Schema::hasTable('system_policies')) {
            Schema::table('system_policies', function (Blueprint $table) {
                foreach (['status', 'priority', 'is_overridable', 'policy_type'] as $column) {
                    if (Schema::hasColumn('system_policies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
