<?php

namespace Database\Seeders;

use App\Models\PolicyOverrideConstraint;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PolicyOverrideConstraintsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('policy_override_constraints')) {
            return;
        }

        $refundPolicy = SystemPolicy::query()->where('key', 'refund')->where('version', 1)->first();
        $platformFeePolicy = SystemPolicy::query()->where('key', 'platform_fee')->where('version', 1)->first();

        $constraints = [
            [
                $refundPolicy,
                $this->findRule($refundPolicy, 'refund_percent_by_cancel_time'),
                'refund_percent_by_cancel_time',
                'refund_percent_minimum',
                'Mức hoàn tiền tối thiểu theo từng mốc',
                'venue_can_be_more_favorable_to_customer',
                null,
                100,
                null,
                'Chính sách sân không được hoàn thấp hơn mức tối thiểu của từng mốc trong chính sách hệ thống.',
            ],
            [
                $refundPolicy,
                $this->findRule($refundPolicy, 'owner_confirm_required_before_admin_transfer'),
                'owner_confirm_required_before_admin_transfer',
                'owner_confirm_required',
                'Chủ sân phải xác nhận trước khi admin hoàn tiền',
                'exact_only',
                null,
                null,
                [true],
                'Chủ sân phải xác nhận yêu cầu hoàn trước khi admin xử lý chuyển tiền.',
            ],
            [
                $platformFeePolicy,
                $this->findRule($platformFeePolicy, 'platform_fee_overdue_lock'),
                'platform_fee_overdue_lock',
                'platform_fee_lock_after_days',
                'Thời hạn khóa do quá hạn phí',
                'exact_only',
                null,
                null,
                [7],
                'Chủ sân không được tự thay đổi thời hạn khóa do quá hạn phí nền tảng.',
            ],
        ];

        foreach ($constraints as [$policy, $rule, $ruleCode, $key, $name, $direction, $min, $max, $allowedValues, $message]) {
            if (! $policy) {
                continue;
            }

            PolicyOverrideConstraint::query()->updateOrCreate(
                ['system_policy_id' => $policy->id, 'constraint_key' => $key],
                [
                    'policy_rule_id' => $rule?->id,
                    'rule_code' => $ruleCode,
                    'constraint_name' => $name,
                    'comparison_direction' => $direction,
                    'min_value' => $min,
                    'max_value' => $max,
                    'allowed_values' => $allowedValues,
                    'message_vi' => $message,
                    'is_active' => true,
                ],
            );
        }
    }

    private function findRule(?SystemPolicy $policy, string $ruleCode): ?PolicyRule
    {
        if (! $policy) {
            return null;
        }

        return PolicyRule::query()
            ->where('system_policy_id', $policy->id)
            ->where('rule_code', $ruleCode)
            ->first();
    }
}
