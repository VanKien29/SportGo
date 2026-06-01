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
                    ['refund', 'refund.owner_confirm', 'Áp dụng khi chủ sân xác nhận hoàn tiền.'],
                    ['refund', 'refund.admin_confirm', 'Áp dụng khi admin xác nhận hoàn tiền hoàn tất.'],
                ],
                'rules' => [
                    [
                        'action_code' => 'booking.cancel',
                        'rule_code' => 'cancel_before_24h_refund_100',
                        'rule_name' => 'Hủy trước 24 giờ hoàn 100%',
                        'rule_type' => 'refund_time_window',
                        'condition_json' => ['hours_before_start' => ['gte' => 24]],
                        'result_json' => ['refund_percent' => 100, 'requires_admin_review' => false],
                        'priority' => 300,
                    ],
                    [
                        'action_code' => 'booking.cancel',
                        'rule_code' => 'cancel_before_6h_refund_50',
                        'rule_name' => 'Hủy trước 6 giờ hoàn 50%',
                        'rule_type' => 'refund_time_window',
                        'condition_json' => ['hours_before_start' => ['gte' => 6, 'lt' => 24]],
                        'result_json' => ['refund_percent' => 50, 'requires_admin_review' => false],
                        'priority' => 200,
                    ],
                    [
                        'action_code' => 'booking.cancel',
                        'rule_code' => 'cancel_under_6h_no_auto_refund',
                        'rule_name' => 'Hủy dưới 6 giờ không tự động hoàn',
                        'rule_type' => 'refund_time_window',
                        'condition_json' => ['hours_before_start' => ['lt' => 6]],
                        'result_json' => ['refund_percent' => 0, 'requires_admin_review' => true],
                        'priority' => 100,
                    ],
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
                    ['moderation', 'complaint.create', 'Áp dụng khi user tạo khiếu nại.'],
                    ['moderation', 'complaint.resolve', 'Áp dụng khi admin xử lý khiếu nại.'],
                    ['moderation', 'report.create', 'Áp dụng khi user gửi report.'],
                    ['moderation', 'report.resolve', 'Áp dụng khi admin xử lý report.'],
                ],
                'rules' => [
                    [
                        'action_code' => 'report.resolve',
                        'rule_code' => 'monthly_reports_auto_lock_suggestion',
                        'rule_name' => 'Gợi ý khóa tài khoản khi đạt ngưỡng report',
                        'rule_type' => 'report_threshold',
                        'condition_json' => ['report_count_in_month' => ['gte' => 10], 'unique_reporters' => ['gte' => 3]],
                        'result_json' => ['suggested_action' => 'account.temporary_lock', 'lock_days' => 7],
                        'priority' => 100,
                    ],
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
                    ['account', 'account.lock', 'Áp dụng khi admin hoặc system khóa tài khoản.'],
                ],
                'rules' => [
                    [
                        'action_code' => 'account.lock',
                        'rule_code' => 'account_lock_requires_reason',
                        'rule_name' => 'Khóa tài khoản phải có lý do',
                        'rule_type' => 'required_reason',
                        'condition_json' => ['reason_required' => true],
                        'result_json' => ['allow_without_reason' => false],
                        'priority' => 100,
                    ],
                ],
            ],
            [
                'key' => 'venue_fee_policy',
                'title' => 'Chính sách khóa cụm sân do quá hạn phí duy trì',
                'type' => 'general',
                'policy_type' => 'platform_fee',
                'content' => 'Quy định khung về xử lý cụm sân quá hạn phí duy trì.',
                'is_overridable' => false,
                'priority' => 100,
                'actions' => [
                    ['venue', 'venue.lock', 'Áp dụng khi admin/system khóa cụm sân.'],
                    ['venue', 'venue.lock_due_fee', 'Áp dụng khi cụm sân quá hạn phí duy trì.'],
                ],
                'rules' => [
                    [
                        'action_code' => 'venue.lock_due_fee',
                        'rule_code' => 'platform_fee_overdue_lock_suggestion',
                        'rule_name' => 'Gợi ý khóa cụm sân khi quá hạn phí duy trì',
                        'rule_type' => 'platform_fee_overdue',
                        'condition_json' => ['overdue_days' => ['gte' => 1], 'amount_due_remaining' => ['gt' => 0]],
                        'result_json' => ['suggested_action' => 'venue.lock', 'reason' => 'Quá hạn phí duy trì nền tảng'],
                        'priority' => 100,
                    ],
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
                    [
                        'action_code' => 'first_login.accept_policy',
                        'rule_code' => 'first_login_accept_required',
                        'rule_name' => 'Bắt buộc chấp nhận điều khoản lần đầu',
                        'rule_type' => 'policy_acceptance_required',
                        'condition_json' => ['accepted_latest_version' => false],
                        'result_json' => ['must_accept' => true],
                        'priority' => 100,
                    ],
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
        $existingId = DB::table('policy_rules')
            ->where('system_policy_id', $policyId)
            ->where('action_code', $rule['action_code'])
            ->where('rule_code', $rule['rule_code'])
            ->value('id');

        $payload = [
            'system_policy_id' => $policyId,
            'action_code' => $rule['action_code'],
            'rule_code' => $rule['rule_code'],
            'rule_name' => $rule['rule_name'],
            'rule_type' => $rule['rule_type'],
            'condition_json' => json_encode($rule['condition_json'], JSON_UNESCAPED_UNICODE),
            'result_json' => json_encode($rule['result_json'], JSON_UNESCAPED_UNICODE),
            'priority' => $rule['priority'],
            'is_active' => true,
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('policy_rules')->where('id', $existingId)->update($payload);
            return;
        }

        $payload['id'] = (string) Str::uuid();
        $payload['created_at'] = now();
        DB::table('policy_rules')->insert($payload);
    }
}
