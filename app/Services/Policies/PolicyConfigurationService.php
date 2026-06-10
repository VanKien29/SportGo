<?php

namespace App\Services\Policies;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use Illuminate\Validation\ValidationException;

class PolicyConfigurationService
{
    public function extractConfigurationData(SystemPolicy $policy): array
    {
        $type = $this->getConfigurationType($policy);
        
        if ($type === 'text_only') {
            return [];
        }

        if ($type === 'platform_fee') {
            return $this->extractPlatformFeeConfig($policy);
        }
        if ($type === 'permission_revoke') {
            return $this->extractPermissionRevokeConfig($policy);
        }
        if ($type === 'partner_contract') {
            return $this->extractPartnerContractConfig($policy);
        }
        
        return [];
    }

    public function getSupportedActions(SystemPolicy $policy): array
    {
        $type = $this->getConfigurationType($policy);

        if ($type === 'platform_fee') {
            return [
                'notify_owner',
                'notify_admin',
                'lock_venue_cluster',
                'termination_review'
            ];
        }
        if ($type === 'permission_revoke') {
            return [
                'notify_target',
                'notify_admin',
                'revoke_owner_scope',
                'admin_review_permission_revoke'
            ];
        }

        return [];
    }

    public function applyConfigurationData(SystemPolicy $policy, array $data): void
    {
        $type = $this->getConfigurationType($policy);

        if ($type === 'text_only') {
            throw ValidationException::withMessages([
                'configuration_data' => 'Chính sách này là dạng văn bản, không hỗ trợ cấu hình tự động.'
            ]);
        }
        
        if ($type === 'platform_fee') {
            $this->applyPlatformFeeConfig($policy, $data);
            return;
        }

        if ($type === 'permission_revoke') {
            $this->applyPermissionRevokeConfig($policy, $data);
            return;
        }

        if ($type === 'partner_contract') {
            $this->applyPartnerContractConfig($policy, $data);
            return;
        }
    }
    
    public function getConfigurationType(SystemPolicy $policy): string
    {
        return match ($policy->policy_type ?: $policy->type) {
            'booking_cancellation' => 'cancel_refund_tiers',
            'moderation' => 'moderation_thresholds',
            'permission_revoke' => 'permission_revoke',
            'platform_fee' => 'platform_fee',
            'account' => 'account_policy',
            'partner_contract' => 'partner_contract',
            'terms', 'general', 'venue_policy' => 'text_only',
            default => 'text_only',
        };
    }

    public function getSupportedTargets(): array
    {
        return [
            ['value' => 'owner', 'label' => 'Chủ sân'],
        ];
    }

    public function getSupportedReasons(): array
    {
        return [
            ['value' => 'platform_fee_overdue', 'label' => 'Quá hạn phí nền tảng'],
            ['value' => 'manual_admin_review', 'label' => 'Xử lý thủ công bởi admin'],
        ];
    }

    public function getSupportedPermissions(): array
    {
        return [
            ['value' => 'manage_venue_cluster', 'label' => 'Quyền quản lý cụm sân'],
        ];
    }

    public function permissionRevokePayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'permission_revoke') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'permission_revoke_rule');

        return [
            'is_supported' => true,
            'has_rule' => (bool)$rule,
            'summary' => $rule->result_json['summary_vi'] ?? 'Chưa cấu hình xử lý thu hồi quyền.',
        ];
    }

    public function partnerContractPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'partner_contract') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'partner_contract_rule');

        return [
            'is_supported' => true,
            'has_rule' => (bool)$rule,
            'summary' => $rule->result_json['summary_vi'] ?? 'Chưa cấu hình xử lý hợp đồng đối tác.',
        ];
    }

    private function extractPermissionRevokeConfig(SystemPolicy $policy): array
    {
        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'permission_revoke_rule');

        $defaultConfig = [
            'target_type' => 'owner',
            'reason_type' => 'platform_fee_overdue',
            'revoke_after_days' => 30,
            'revoke_duration_days' => null,
            'permissions_to_revoke' => ['manage_venue_cluster'],
            'requires_admin_confirm' => true,
            'notify_target' => true,
            'notify_admin' => true,
            'message_template' => 'Quyền quản lý cụm sân của bạn đã bị thu hồi do quá hạn phí nền tảng.',
        ];

        if (!$rule) {
            return $defaultConfig;
        }

        return [
            'target_type' => $rule->condition_json['target_type'] ?? $defaultConfig['target_type'],
            'reason_type' => $rule->condition_json['reason_type'] ?? $defaultConfig['reason_type'],
            'revoke_after_days' => $rule->condition_json['revoke_after_days'] ?? $defaultConfig['revoke_after_days'],
            'revoke_duration_days' => $rule->result_json['revoke_duration_days'] ?? $defaultConfig['revoke_duration_days'],
            'permissions_to_revoke' => $rule->result_json['permissions_to_revoke'] ?? $defaultConfig['permissions_to_revoke'],
            'requires_admin_confirm' => $rule->result_json['requires_admin_confirm'] ?? $defaultConfig['requires_admin_confirm'],
            'notify_target' => $rule->result_json['notify_target'] ?? $defaultConfig['notify_target'],
            'notify_admin' => $rule->result_json['notify_admin'] ?? $defaultConfig['notify_admin'],
            'message_template' => $rule->result_json['message_template'] ?? $defaultConfig['message_template'],
        ];
    }

    private function applyPermissionRevokeConfig(SystemPolicy $policy, array $data): void
    {
        $validTargets = collect($this->getSupportedTargets())->pluck('value')->toArray();
        $validReasons = collect($this->getSupportedReasons())->pluck('value')->toArray();
        $validPermissions = collect($this->getSupportedPermissions())->pluck('value')->toArray();

        $validator = validator($data, [
            'target_type' => ['required', 'string', 'in:' . implode(',', $validTargets)],
            'reason_type' => ['required', 'string', 'in:' . implode(',', $validReasons)],
            'revoke_after_days' => ['required', 'integer', 'min:0'],
            'revoke_duration_days' => ['nullable', 'integer', 'min:1'],
            'permissions_to_revoke' => ['required', 'array', 'min:1'],
            'permissions_to_revoke.*' => ['string', 'in:' . implode(',', $validPermissions)],
            'requires_admin_confirm' => ['required', 'boolean'],
            'notify_target' => ['required', 'boolean'],
            'notify_admin' => ['required', 'boolean'],
            'message_template' => ['required', 'string', 'max:500'],
        ]);

        $validated = $validator->validate();

        $rule = $policy->rules()->firstOrCreate(
            ['rule_type' => 'permission_revoke_rule'],
            [
                'rule_code' => 'PERMISSION_REVOKE',
                'rule_name' => 'Quy định thu hồi quyền',
                'action_code' => 'auto_revoke_permission',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]
        );

        $rule->update([
            'condition_json' => [
                'target_type' => $validated['target_type'],
                'reason_type' => $validated['reason_type'],
                'revoke_after_days' => $validated['revoke_after_days'],
            ],
            'result_json' => [
                'revoke_duration_days' => $validated['revoke_duration_days'],
                'permissions_to_revoke' => $validated['permissions_to_revoke'],
                'requires_admin_confirm' => $validated['requires_admin_confirm'],
                'notify_target' => $validated['notify_target'],
                'notify_admin' => $validated['notify_admin'],
                'message_template' => $validated['message_template'],
                'summary_vi' => 'Thu hồi quyền sau ' . $validated['revoke_after_days'] . ' ngày vi phạm.',
            ],
            'updated_by' => auth()->id(),
        ]);
        
        $policy->actionBindings()->firstOrCreate(
            ['action_code' => 'auto_revoke_permission'],
            [
                'module' => 'permission',
                'description' => 'Tự động thu hồi quyền hoặc yêu cầu admin xác nhận thu hồi',
                'is_active' => true,
            ]
        );
    }

    private function extractPlatformFeeConfig(SystemPolicy $policy): array
    {
        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'platform_fee_escalation');

        $defaultConfig = [
            'remind_before_days' => 3,
            'warn_overdue_days' => 1,
            'restrict_overdue_days' => 7,
            'lock_overdue_days' => 14,
            'termination_review_overdue_days' => 30,
            'notify_owner' => true,
            'notify_admin' => true,
            'message_template' => 'Cụm sân của bạn đã đến hoặc quá hạn phí nền tảng.',
        ];

        if (!$rule) {
            return $defaultConfig;
        }

        return [
            'remind_before_days' => $rule->condition_json['remind_before_days'] ?? $defaultConfig['remind_before_days'],
            'warn_overdue_days' => $rule->condition_json['warn_overdue_days'] ?? $defaultConfig['warn_overdue_days'],
            'restrict_overdue_days' => $rule->condition_json['restrict_overdue_days'] ?? $defaultConfig['restrict_overdue_days'],
            'lock_overdue_days' => $rule->condition_json['lock_overdue_days'] ?? $defaultConfig['lock_overdue_days'],
            'termination_review_overdue_days' => $rule->condition_json['termination_review_overdue_days'] ?? $defaultConfig['termination_review_overdue_days'],
            'notify_owner' => $rule->result_json['notify_owner'] ?? $defaultConfig['notify_owner'],
            'notify_admin' => $rule->result_json['notify_admin'] ?? $defaultConfig['notify_admin'],
            'message_template' => $rule->result_json['message_template'] ?? $defaultConfig['message_template'],
        ];
    }

    private function extractPartnerContractConfig(SystemPolicy $policy): array
    {
        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'partner_contract_rule');

        $defaultConfig = [
            'warn_before_days' => 30,
            'lock_after_days' => 7,
            'revoke_after_days' => 30,
            'requires_admin_confirm' => true,
            'notify_target' => true,
            'notify_admin' => true,
        ];

        if (!$rule) {
            return $defaultConfig;
        }

        return [
            'warn_before_days' => $rule->condition_json['warn_before_days'] ?? $defaultConfig['warn_before_days'],
            'lock_after_days' => $rule->condition_json['lock_after_days'] ?? $defaultConfig['lock_after_days'],
            'revoke_after_days' => $rule->condition_json['revoke_after_days'] ?? $defaultConfig['revoke_after_days'],
            'requires_admin_confirm' => $rule->result_json['requires_admin_confirm'] ?? $defaultConfig['requires_admin_confirm'],
            'notify_target' => $rule->result_json['notify_target'] ?? $defaultConfig['notify_target'],
            'notify_admin' => $rule->result_json['notify_admin'] ?? $defaultConfig['notify_admin'],
        ];
    }

    private function applyPlatformFeeConfig(SystemPolicy $policy, array $data): void
    {
        $validator = validator($data, [
            'remind_before_days' => ['required', 'integer', 'min:0'],
            'warn_overdue_days' => ['required', 'integer', 'min:0'],
            'restrict_overdue_days' => ['required', 'integer', 'min:0'],
            'lock_overdue_days' => ['required', 'integer', 'min:0'],
            'termination_review_overdue_days' => ['required', 'integer', 'min:0'],
            'notify_owner' => ['required', 'boolean'],
            'notify_admin' => ['required', 'boolean'],
            'message_template' => ['required', 'string', 'max:500'],
        ], [
            'remind_before_days.min' => 'Số ngày phải lớn hơn hoặc bằng 0.',
            'warn_overdue_days.min' => 'Số ngày phải lớn hơn hoặc bằng 0.',
            'restrict_overdue_days.min' => 'Số ngày phải lớn hơn hoặc bằng 0.',
            'lock_overdue_days.min' => 'Số ngày phải lớn hơn hoặc bằng 0.',
            'termination_review_overdue_days.min' => 'Số ngày phải lớn hơn hoặc bằng 0.',
        ]);

        $validator->after(function ($validator) use ($data) {
            if ($data['warn_overdue_days'] > $data['restrict_overdue_days']) {
                $validator->errors()->add('warn_overdue_days', 'Ngày cảnh báo không được lớn hơn ngày hạn chế.');
            }
            if ($data['restrict_overdue_days'] > $data['lock_overdue_days']) {
                $validator->errors()->add('restrict_overdue_days', 'Ngày hạn chế không được lớn hơn ngày khóa.');
            }
            if ($data['lock_overdue_days'] > $data['termination_review_overdue_days']) {
                $validator->errors()->add('lock_overdue_days', 'Ngày khóa không được lớn hơn ngày xem xét chấm dứt.');
            }
        });

        $validated = $validator->validate();

        $rule = $policy->rules()->firstOrCreate(
            ['rule_type' => 'platform_fee_escalation'],
            [
                'rule_code' => 'PLATFORM_FEE_ESCALATION',
                'rule_name' => 'Quy trình xử lý nợ phí nền tảng',
                'action_code' => 'auto_escalate_platform_fee',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]
        );

        $rule->update([
            'condition_json' => [
                'remind_before_days' => $validated['remind_before_days'],
                'warn_overdue_days' => $validated['warn_overdue_days'],
                'restrict_overdue_days' => $validated['restrict_overdue_days'],
                'lock_overdue_days' => $validated['lock_overdue_days'],
                'termination_review_overdue_days' => $validated['termination_review_overdue_days'],
            ],
            'result_json' => [
                'notify_owner' => $validated['notify_owner'],
                'notify_admin' => $validated['notify_admin'],
                'message_template' => $validated['message_template'],
                'summary_vi' => 'Nhắc nhở trước ' . $validated['remind_before_days'] . ' ngày. Khóa sân sau ' . $validated['lock_overdue_days'] . ' ngày.',
            ],
            'updated_by' => auth()->id(),
        ]);
        
        $policy->actionBindings()->firstOrCreate(
            ['action_code' => 'auto_escalate_platform_fee'],
            [
                'module' => 'platform_fee',
                'description' => 'Tự động nhắc nhở, cảnh báo và khóa sân khi nợ phí nền tảng',
                'is_active' => true,
            ]
        );
    }

    private function applyPartnerContractConfig(SystemPolicy $policy, array $data): void
    {
        $validator = validator($data, [
            'warn_before_days' => ['required', 'integer', 'min:0'],
            'lock_after_days' => ['required', 'integer', 'min:0'],
            'revoke_after_days' => ['required', 'integer', 'min:0'],
            'requires_admin_confirm' => ['required', 'boolean'],
            'notify_target' => ['required', 'boolean'],
            'notify_admin' => ['required', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($data) {
            if ($data['lock_after_days'] > $data['revoke_after_days']) {
                $validator->errors()->add('lock_after_days', 'Ngày khóa cụm sân không được lớn hơn ngày thu hồi quyền.');
            }
        });

        $validated = $validator->validate();

        $rule = $policy->rules()->firstOrCreate(
            ['rule_type' => 'partner_contract_rule'],
            [
                'rule_code' => 'PARTNER_CONTRACT_POLICY',
                'rule_name' => 'Quy định xử lý hợp đồng đối tác',
                'action_code' => 'auto_partner_contract',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]
        );

        $rule->update([
            'condition_json' => [
                'warn_before_days' => $validated['warn_before_days'],
                'lock_after_days' => $validated['lock_after_days'],
                'revoke_after_days' => $validated['revoke_after_days'],
            ],
            'result_json' => [
                'requires_admin_confirm' => $validated['requires_admin_confirm'],
                'notify_target' => $validated['notify_target'],
                'notify_admin' => $validated['notify_admin'],
                'summary_vi' => 'Thu hồi quyền sau ' . $validated['revoke_after_days'] . ' ngày hết hạn.',
            ],
            'updated_by' => auth()->id(),
        ]);
        
        $policy->actionBindings()->firstOrCreate(
            ['action_code' => 'auto_partner_contract'],
            [
                'module' => 'contract',
                'description' => 'Tự động nhắc nhở, khóa sân hoặc thu hồi quyền khi hợp đồng hết hạn',
                'is_active' => true,
            ]
        );
    }

    public function accountPolicyPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'account') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', 'account_violation_rule');

        if (!$rule) {
            return [
                'is_supported' => true,
                'has_rule' => false,
                'summary' => 'Chưa có cấu hình chính sách tài khoản.',
                'can_edit' => $policy->status !== 'active',
                'config' => [
                    'warnings_to_restrict' => 3,
                    'violations_to_lock' => 5,
                    'lock_days' => 7,
                    'admin_confirm' => false,
                    'notify_user' => true,
                    'default_reason' => 'Tài khoản của bạn đã vi phạm chính sách nhiều lần.',
                ]
            ];
        }

        return [
            'is_supported' => true,
            'has_rule' => true,
            'rule_id' => $rule->id,
            'rule_name' => $rule->rule_name,
            'summary' => $rule->result_json['summary_vi'] ?? 'Cấu hình chính sách tài khoản.',
            'can_edit' => $policy->status !== 'active',
            'config' => [
                'warnings_to_restrict' => $rule->condition_json['warnings_to_restrict'] ?? 3,
                'violations_to_lock' => $rule->condition_json['violations_to_lock'] ?? 5,
                'lock_days' => $rule->result_json['lock_days'] ?? 7,
                'admin_confirm' => $rule->result_json['admin_confirm'] ?? false,
                'notify_user' => $rule->result_json['notify_user'] ?? true,
                'default_reason' => $rule->result_json['default_reason'] ?? 'Tài khoản của bạn đã vi phạm chính sách nhiều lần.',
            ]
        ];
    }
}
