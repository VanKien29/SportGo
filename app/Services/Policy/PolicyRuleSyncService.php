<?php

namespace App\Services\Policy;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;

class PolicyRuleSyncService
{
    public function syncFromThresholds(SystemPolicy $policy): void
    {
        $policy->loadMissing('moderationThresholds');

        foreach ($policy->moderationThresholds as $threshold) {
            $existingRule = PolicyRule::query()
                ->where('system_policy_id', $policy->id)
                ->where('rule_code', 'moderation_score_' . $threshold->target_type)
                ->first();

            $existingResult = $existingRule ? ($existingRule->result_json ?? []) : [];

            $resultJson = array_merge($existingResult, [
                'action_threshold' => $threshold->action_threshold,
                'warning_threshold' => $threshold->warning_threshold,
                'unique_reporters_threshold' => $threshold->unique_reporters_threshold,
            ]);

            PolicyRule::query()->updateOrCreate(
                [
                    'system_policy_id' => $policy->id,
                    'rule_code' => 'moderation_score_' . $threshold->target_type,
                ],
                [
                    'rule_name' => 'Ngưỡng điểm vi phạm: ' . $threshold->target_type,
                    'rule_type' => 'moderation_score_threshold',
                    'action_code' => 'report.score_evaluated',
                    'decision_key' => 'moderation_score',
                    'conflict_group' => 'moderation_score_' . $threshold->target_type,
                    'condition_json' => [
                        'target_type' => $threshold->target_type,
                        'timeframe_days' => $threshold->timeframe_days,
                    ],
                    'result_json' => $resultJson,
                    'priority' => $policy->priority,
                    'is_active' => true,
                ]
            );
        }
    }

    public function syncFromEscalationRules(SystemPolicy $policy): void
    {
        $policy->loadMissing('penaltyEscalationRules');

        foreach ($policy->penaltyEscalationRules as $rule) {
            PolicyRule::query()->updateOrCreate(
                [
                    'system_policy_id' => $policy->id,
                    'rule_code' => 'penalty_' . $rule->target_type . '_' . $rule->violation_count,
                ],
                [
                    'rule_name' => 'Leo thang xử lý: ' . $rule->target_type . ' lần ' . $rule->violation_count,
                    'rule_type' => 'penalty_escalation',
                    'action_code' => 'report.resolve',
                    'decision_key' => 'penalty_action',
                    'conflict_group' => 'penalty_' . $rule->target_type,
                    'condition_json' => [
                        'target_type' => $rule->target_type,
                        'violation_count' => $rule->violation_count,
                        'is_catch_all' => $rule->is_catch_all,
                    ],
                    'result_json' => [
                        'action_type' => $rule->action_type,
                        'duration_days' => $rule->duration_days,
                        'notify_channels' => $rule->notify_channels,
                    ],
                    'priority' => $policy->priority,
                    'is_active' => true,
                ]
            );
        }
    }

    public function syncFromPercentageTable(SystemPolicy $policy, array $rows): void
    {
        PolicyRule::query()->updateOrCreate(
            [
                'system_policy_id' => $policy->id,
                'rule_code' => 'percentage_table_' . $policy->key,
            ],
            [
                'rule_name' => 'Bảng phần trăm xử lý: ' . $policy->title,
                'rule_type' => 'percentage_table',
                'action_code' => $policy->key . '.evaluate',
                'decision_key' => 'percentage_table',
                'conflict_group' => 'percentage_table_' . $policy->key,
                'condition_json' => ['uses_percentage_table' => true],
                'result_json' => ['rows' => array_values($rows)],
                'priority' => $policy->priority,
                'is_active' => true,
            ]
        );
    }

    public function syncFromNumericThresholds(SystemPolicy $policy, array $rows): void
    {
        PolicyRule::query()->updateOrCreate(
            [
                'system_policy_id' => $policy->id,
                'rule_code' => 'numeric_threshold_' . $policy->key,
            ],
            [
                'rule_name' => 'Ngưỡng số: ' . $policy->title,
                'rule_type' => 'numeric_threshold',
                'action_code' => $policy->key . '.evaluate',
                'decision_key' => 'numeric_threshold',
                'conflict_group' => 'numeric_threshold_' . $policy->key,
                'condition_json' => ['uses_numeric_thresholds' => true],
                'result_json' => ['rows' => array_values($rows)],
                'priority' => $policy->priority,
                'is_active' => true,
            ]
        );
    }
}
