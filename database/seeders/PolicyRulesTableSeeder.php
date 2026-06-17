<?php

namespace Database\Seeders;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Services\Policies\RefundCancellationPolicyService;
use App\Support\PolicyUiText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PolicyRulesTableSeeder extends Seeder
{
    public function __construct(private readonly RefundCancellationPolicyService $refundPolicies)
    {
    }

    public function run(): void
    {
        if (! Schema::hasTable('system_policies') || ! Schema::hasTable('policy_rules')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $templates = PolicyUiText::ruleTemplateOptions();

        foreach ($this->rules($templates) as $policyKey => $rules) {
            $policy = SystemPolicy::query()->where('key', $policyKey)->where('version', 1)->first();

            if (! $policy) {
                continue;
            }

            foreach ($rules as $rule) {
                PolicyRule::query()->updateOrCreate(
                    ['system_policy_id' => $policy->id, 'rule_code' => $rule['rule_code']],
                    [
                        'action_code' => $rule['action_code'],
                        'rule_name' => $rule['rule_name'],
                        'rule_type' => $rule['rule_type'],
                        'decision_key' => $rule['decision_key'],
                        'conflict_group' => $rule['conflict_group'],
                        'condition_json' => $rule['condition_json'],
                        'result_json' => $rule['result_json'],
                        'constraint_json' => $rule['constraint_json'] ?? null,
                        'allowed_override_json' => $rule['allowed_override_json'] ?? null,
                        'priority' => $rule['priority'],
                        'is_active' => true,
                        'created_by' => $admin?->id,
                        'updated_by' => $admin?->id,
                    ],
                );
            }
        }
    }

    private function rules(array $templates): array
    {
        return [
            'terms' => [
                $this->fromTemplate($templates['terms_acceptance_required'], 100),
            ],
            'booking_cancellation' => [
                $this->fromTemplate($templates['cancel_before_hours'], 100, [
                    'rule_name' => 'Bảng mốc hủy & hoàn booking',
                    'condition_json' => ['uses_cancel_refund_tier_table' => true],
                    'result_json' => $this->refundPolicies->cancelRefundResultJson($this->refundPolicies->defaultCancelRefundTiers(), [
                        'refund_basis' => 'paid_amount',
                    ]),
                    'constraint_json' => ['covers_from_hours' => 0, 'covers_to_infinity' => true],
                    'allowed_override_json' => [
                        'venue_can_improve_refund_percent' => true,
                        'venue_can_change_time_ranges' => false,
                        'venue_can_block_system_allowed_cancel' => false,
                    ],
                ]),
            ],
            'platform_fee' => [
                $this->fromTemplate($templates['platform_fee_overdue_warning'], 80),
                $this->fromTemplate($templates['platform_fee_overdue_lock'], 100, [
                    'result_json' => [
                        'action' => 'limit_owner_access',
                        'access_mode' => 'limited',
                        'lock_after_days' => 7,
                        'allowed_owner_actions' => ['pay_platform_fee', 'view_wallet', 'request_withdrawal_if_allowed', 'view_contracts'],
                        'blocked_owner_actions' => ['create_booking', 'update_price', 'create_voucher', 'create_post', 'add_staff'],
                    ],
                    'constraint_json' => ['lock_after_days' => ['exact' => 7]],
                    'allowed_override_json' => ['lock_after_days' => ['exact' => 7]],
                ]),
            ],
            'venue_policy' => [
                $this->fromTemplate($templates['venue_policy_override_limit'], 100),
            ],
            'moderation' => [
                $this->fromTemplate($templates['report_threshold_requires_review'], 90, [
                    'rule_name' => 'Ngưỡng báo cáo đưa nội dung vào chờ kiểm duyệt',
                    'condition_json' => [
                        'target_type' => 'content',
                        'report_count' => ['gte' => 5],
                        'unique_reporters' => ['gte' => 2],
                        'window_days' => 14,
                    ],
                    'result_json' => [
                        'actions' => ['pending_review', 'notify_admin'],
                        'action' => 'pending_review',
                        'summary_vi' => 'Nếu nội dung nhận từ 5 báo cáo hợp lệ bởi ít nhất 2 người khác nhau trong 14 ngày, hệ thống chuyển nội dung sang chờ kiểm duyệt và thông báo admin.',
                    ],
                ]),
                $this->fromTemplate($templates['report_threshold_requires_review'], 80, [
                    'rule_code' => 'user_report_warning_threshold',
                    'rule_name' => 'Ngưỡng cảnh báo tài khoản người dùng',
                    'condition_json' => [
                        'reportable_type' => 'user',
                        'threshold' => 3,
                        'count_mode' => 'distinct_reporters',
                        'window_days' => 7,
                    ],
                    'result_json' => [
                        'action' => 'warning',
                        'notify_admin' => true,
                        'summary_vi' => 'Nếu tài khoản nhận từ 3 người báo cáo khác nhau trong 7 ngày, hệ thống sẽ đưa vào diện cảnh báo và thông báo admin.',
                    ],
                ]),
                $this->fromTemplate($templates['report_threshold_requires_review'], 100, [
                    'rule_code' => 'user_report_lock_threshold',
                    'rule_name' => 'Ngưỡng tự động khóa tài khoản người dùng',
                    'condition_json' => [
                        'reportable_type' => 'user',
                        'threshold' => 10,
                        'count_mode' => 'distinct_reporters',
                        'window_days' => 7,
                    ],
                    'result_json' => [
                        'action' => 'auto_lock',
                        'lock_duration_days' => 7,
                        'notify_admin' => true,
                        'is_auto_lock_enabled' => false,
                        'summary_vi' => 'Nếu tài khoản nhận từ 10 người báo cáo khác nhau trong 7 ngày, hệ thống sẽ tự động khóa tạm thời 7 ngày và thông báo admin.',
                    ],
                ]),
            ],
            'partner_contract' => [
                $this->fromTemplate($templates['partner_application_approve_requires_contract'], 90),
                $this->fromTemplate($templates['contract_signing_required'], 100),
                $this->fromTemplate($templates['partner_termination_transition_30_days'], 95),
            ],
        ];
    }

    private function fromTemplate(array $template, int $priority, array $overrides = []): array
    {
        return array_merge([
            'action_code' => $template['action_code'],
            'rule_code' => $template['rule_code'],
            'rule_name' => $template['label'],
            'rule_type' => $template['rule_type'],
            'decision_key' => $template['decision_key'],
            'conflict_group' => $template['conflict_group'],
            'condition_json' => $template['condition_json'],
            'result_json' => $template['result_json'],
            'priority' => $priority,
        ], $overrides);
    }
}
