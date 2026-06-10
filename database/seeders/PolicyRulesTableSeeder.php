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
                    'rule_name' => 'Bảng mốc thời gian được hủy booking',
                    'condition_json' => ['uses_tier_table' => true],
                    'result_json' => $this->refundPolicies->cancellationResultJson($this->refundPolicies->defaultCancellationTiers(), [
                        'disallow_statuses' => ['checked_in', 'completed'],
                        'owner_cancel_requires_reason' => true,
                        'may_create_refund_request' => true,
                    ]),
                    'constraint_json' => ['tiers' => ['venue_cannot_be_less_favorable_than_system' => true]],
                    'allowed_override_json' => ['tiers' => ['allow_cancel' => 'venue_can_keep_or_be_more_favorable_to_customer']],
                ]),
            ],
            'refund' => [
                $this->fromTemplate($templates['refund_percent_by_cancel_time'], 100, [
                    'rule_name' => 'Bảng mốc hoàn tiền theo thời gian hủy hợp lệ',
                    'condition_json' => ['uses_tier_table' => true],
                    'result_json' => $this->refundPolicies->resultJson($this->refundPolicies->defaultTiers()),
                    'constraint_json' => ['tiers' => ['venue_refund_percent_must_be_at_least_system_percent' => true]],
                    'allowed_override_json' => ['tiers' => ['refund_percent' => 'venue_can_be_more_favorable_to_customer']],
                ]),
                $this->fromTemplate($templates['owner_confirm_required_before_admin_transfer'], 110, [
                    'constraint_json' => ['owner_confirm_required' => ['exact' => true]],
                    'allowed_override_json' => ['owner_confirm_required' => ['exact' => true]],
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
