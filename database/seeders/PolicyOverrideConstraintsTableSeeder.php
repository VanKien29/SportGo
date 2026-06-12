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

        $platformFeePolicy = SystemPolicy::query()->where('key', 'platform_fee')->where('version', 1)->first();

        $constraints = [
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
