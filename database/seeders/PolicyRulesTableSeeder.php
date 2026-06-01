<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PolicyRulesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('system_policies')
            || ! Schema::hasTable('policy_action_bindings')
            || ! Schema::hasTable('policy_rules')) {
            return;
        }

        $adminId = DB::table('users')->where('username', 'admin')->value('id');

        $policies = [
            [
                'key' => 'refund_policy',
                'title' => 'Chính sách hủy và hoàn tiền',
                'type' => 'refund',
                'policy_type' => 'refund',
                'content' => 'Quy định khung về hủy booking và hoàn tiền trên SportGo.',
                'is_overridable' => true,
                'priority' => 100,
                'actions' => [
                    ['refund', 'booking.cancel', 'Áp dụng khi khách hoặc sân hủy booking.'],
                    ['refund', 'refund.request', 'Áp dụng khi khách gửi yêu cầu hoàn tiền.'],
                    ['refund', 'refund.owner_confirm', 'Áp dụng khi chủ sân xác nhận yêu cầu hoàn tiền.'],
                    ['refund', 'refund.admin_confirm', 'Áp dụng khi admin xác nhận hoàn tiền hoàn tất.'],
                ],
                'rules' => [
                    ['booking.cancel', 'cancel_before_24h_refund_100', 'Hủy trước 24 giờ hoàn 100%', 'refund_time_window', ['hours_before_start' => ['gte' => 24]], ['refund_percent' => 100, 'requires_admin_review' => false], 300],
                    ['booking.cancel', 'cancel_before_6h_refund_50', 'Hủy trước 6 giờ hoàn 50%', 'refund_time_window', ['hours_before_start' => ['gte' => 6, 'lt' => 24]], ['refund_percent' => 50, 'requires_admin_review' => false], 200],
                    ['booking.cancel', 'cancel_under_6h_no_auto_refund', 'Hủy dưới 6 giờ không tự động hoàn', 'refund_time_window', ['hours_before_start' => ['lt' => 6]], ['refund_percent' => 0, 'requires_admin_review' => true], 100],
                ],
            ],
            [
                'key' => 'moderation_policy',
                'title' => 'Chính sách xử lý báo cáo vi phạm',
                'type' => 'moderation',
                'policy_type' => 'moderation',
                'content' => 'Quy định xử lý report, complaint và nội dung vi phạm.',
                'is_overridable' => false,
                'priority' => 90,
                'actions' => [
                    ['moderation', 'complaint.create', 'Áp dụng khi người dùng tạo khiếu nại.'],
                    ['moderation', 'complaint.resolve', 'Áp dụng khi admin xử lý khiếu nại.'],
                    ['moderation', 'report.create', 'Áp dụng khi người dùng gửi báo cáo vi phạm.'],
                    ['moderation', 'report.resolve', 'Áp dụng khi admin xử lý báo cáo vi phạm.'],
                ],
                'rules' => [
                    ['report.resolve', 'monthly_reports_auto_lock_suggestion', 'Gợi ý khóa tài khoản khi đạt ngưỡng report', 'report_threshold', ['report_count' => ['gte' => 10], 'unique_reporters' => ['gte' => 3], 'window_days' => 30], ['action' => 'temporary_lock', 'lock_days' => 7], 100],
                ],
            ],
            [
                'key' => 'account_lock_policy',
                'title' => 'Chính sách khóa tài khoản',
                'type' => 'moderation',
                'policy_type' => 'account',
                'content' => 'Quy định khung về khóa/mở khóa tài khoản.',
                'is_overridable' => false,
                'priority' => 95,
                'actions' => [
                    ['account', 'account.lock', 'Áp dụng khi admin hoặc hệ thống khóa tài khoản.'],
                    ['account', 'account.unlock', 'Áp dụng khi mở khóa tài khoản.'],
                ],
                'rules' => [
                    ['account.lock', 'account_lock_requires_reason', 'Khóa tài khoản phải có lý do', 'account_lock_manual', ['reason_required' => true], ['allow_without_reason' => false], 100],
                ],
            ],
            [
                'key' => 'venue_fee_policy',
                'title' => 'Chính sách phí duy trì cụm sân',
                'type' => 'general',
                'policy_type' => 'platform_fee',
                'content' => 'Quy định khung về xử lý cụm sân quá hạn phí duy trì nền tảng.',
                'is_overridable' => false,
                'priority' => 100,
                'actions' => [
                    ['venue', 'venue.lock', 'Áp dụng khi admin/hệ thống khóa cụm sân.'],
                    ['venue', 'venue.lock_due_fee', 'Áp dụng khi cụm sân quá hạn phí duy trì.'],
                ],
                'rules' => [
                    ['venue.lock_due_fee', 'platform_fee_overdue_lock_suggestion', 'Gợi ý khóa cụm sân khi quá hạn phí duy trì', 'platform_fee_overdue', ['overdue_days' => ['gte' => 1], 'amount_due_remaining' => ['gt' => 0]], ['action' => 'lock_venue', 'reason' => 'Quá hạn phí duy trì nền tảng'], 100],
                ],
            ],
            [
                'key' => 'terms_of_service',
                'title' => 'Điều khoản sử dụng SportGo',
                'type' => 'general',
                'policy_type' => 'terms',
                'content' => 'Điều khoản sử dụng cần được chấp nhận khi đăng nhập lần đầu hoặc khi có phiên bản mới.',
                'is_overridable' => false,
                'priority' => 80,
                'actions' => [
                    ['auth', 'first_login.accept_policy', 'Áp dụng khi user cần chấp nhận điều khoản.'],
                ],
                'rules' => [
                    ['first_login.accept_policy', 'first_login_accept_required', 'Bắt buộc chấp nhận điều khoản lần đầu', 'first_login_accept_required', ['accepted_latest_version' => false], ['must_accept' => true], 100],
                ],
            ],
        ];

        foreach ($policies as $policy) {
            $policyId = $this->upsertPolicy($policy, $adminId);

            foreach ($policy['actions'] as [$module, $actionCode, $description]) {
                $this->upsertBinding($policyId, $module, $actionCode, $description);
            }

            foreach ($policy['rules'] as $rule) {
                $this->upsertRule($policyId, $rule);
            }
        }
    }

    private function upsertPolicy(array $policy, ?string $adminId): string
    {
        $existingId = DB::table('system_policies')
            ->where('key', $policy['key'])
            ->where('version', 1)
            ->value('id');

        $id = $existingId ?: (string) Str::uuid();
        $payload = [
            'id' => $id,
            'key' => $policy['key'],
            'version' => 1,
            'title' => $policy['title'],
            'content' => $policy['content'],
            'type' => $policy['type'],
            'is_active' => true,
            'effective_from' => now(),
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach (['policy_type', 'is_overridable', 'priority'] as $column) {
            if (Schema::hasColumn('system_policies', $column)) {
                $payload[$column] = $policy[$column];
            }
        }

        if (Schema::hasColumn('system_policies', 'status')) {
            $payload['status'] = 'active';
        }

        if (Schema::hasColumn('system_policies', 'published_at')) {
            $payload['published_at'] = now();
        }

        if (Schema::hasColumn('system_policies', 'published_by')) {
            $payload['published_by'] = $adminId;
        }

        if (Schema::hasColumn('system_policies', 'require_reaccept')) {
            $payload['require_reaccept'] = $policy['key'] === 'terms_of_service';
        }

        if (Schema::hasColumn('system_policies', 'change_summary')) {
            $payload['change_summary'] = 'Dữ liệu mẫu chuẩn tiếng Việt cho module chính sách.';
        }

        if ($existingId) {
            unset($payload['id'], $payload['created_at']);
            DB::table('system_policies')->where('id', $id)->update($payload);
        } else {
            DB::table('system_policies')->insert($payload);
        }

        return $id;
    }

    private function upsertBinding(string $policyId, string $module, string $actionCode, string $description): void
    {
        $existingId = DB::table('policy_action_bindings')
            ->where('system_policy_id', $policyId)
            ->where('action_code', $actionCode)
            ->value('id');

        $payload = [
            'system_policy_id' => $policyId,
            'module' => $module,
            'action_code' => $actionCode,
            'description' => $description,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('policy_action_bindings')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('policy_action_bindings')->insert($payload);
    }

    private function upsertRule(string $policyId, array $rule): void
    {
        [$actionCode, $ruleCode, $ruleName, $ruleType, $condition, $result, $priority] = $rule;

        $existingId = DB::table('policy_rules')
            ->where('system_policy_id', $policyId)
            ->where('action_code', $actionCode)
            ->where('rule_code', $ruleCode)
            ->value('id');

        $payload = [
            'system_policy_id' => $policyId,
            'action_code' => $actionCode,
            'rule_code' => $ruleCode,
            'rule_name' => $ruleName,
            'rule_type' => $ruleType,
            'condition_json' => json_encode($condition, JSON_UNESCAPED_UNICODE),
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'priority' => $priority,
            'is_active' => true,
            'updated_at' => now(),
        ];

        foreach ($this->ruleDefaults($ruleType) as $column => $value) {
            if (Schema::hasColumn('policy_rules', $column)) {
                $payload[$column] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }
        }

        foreach (['created_by', 'updated_by'] as $column) {
            if (Schema::hasColumn('policy_rules', $column)) {
                $payload[$column] = DB::table('system_policies')->where('id', $policyId)->value('updated_by');
            }
        }

        if ($existingId) {
            DB::table('policy_rules')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('policy_rules')->insert($payload);
    }

    private function ruleDefaults(string $ruleType): array
    {
        return match ($ruleType) {
            'refund_time_window' => [
                'decision_key' => 'refund_percent',
                'conflict_group' => 'booking_cancel_refund',
                'constraint_json' => ['refund_percent' => ['min' => 0, 'max' => 100]],
                'allowed_override_json' => ['refund_percent' => ['min' => 0, 'max' => 100]],
            ],
            'report_threshold' => [
                'decision_key' => 'moderation_action',
                'conflict_group' => 'report_auto_lock',
                'constraint_json' => ['lock_days' => ['min' => 1, 'max' => 30]],
                'allowed_override_json' => null,
            ],
            'account_lock_manual' => [
                'decision_key' => 'account_lock_requirement',
                'conflict_group' => 'manual_account_lock',
                'constraint_json' => null,
                'allowed_override_json' => null,
            ],
            'platform_fee_overdue' => [
                'decision_key' => 'venue_fee_action',
                'conflict_group' => 'platform_fee_overdue',
                'constraint_json' => null,
                'allowed_override_json' => null,
            ],
            'first_login_accept_required' => [
                'decision_key' => 'must_accept_policy',
                'conflict_group' => 'policy_acceptance',
                'constraint_json' => null,
                'allowed_override_json' => null,
            ],
            default => [],
        };
    }
}
