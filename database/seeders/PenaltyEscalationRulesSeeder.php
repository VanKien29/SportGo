<?php

namespace Database\Seeders;

use App\Models\PenaltyEscalationRule;
use App\Models\SystemPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PenaltyEscalationRulesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('penalty_escalation_rules') || ! Schema::hasTable('system_policies')) {
            return;
        }

        $policy = SystemPolicy::query()
            ->where(function ($query): void {
                $query->whereIn('key', ['content_moderation', 'moderation'])
                    ->orWhereIn('policy_type', ['content_moderation', 'moderation']);
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('version')
            ->first();

        if (! $policy) {
            return;
        }

        $rows = [
            ['target_type' => 'user', 'violation_count' => 1, 'is_catch_all' => false, 'action_type' => 'warn', 'duration_days' => null],
            ['target_type' => 'user', 'violation_count' => 2, 'is_catch_all' => false, 'action_type' => 'lock_temp', 'duration_days' => 7],
            ['target_type' => 'user', 'violation_count' => 3, 'is_catch_all' => false, 'action_type' => 'lock_temp', 'duration_days' => 30],
            ['target_type' => 'user', 'violation_count' => 4, 'is_catch_all' => true, 'action_type' => 'lock_permanent', 'duration_days' => null],
            ['target_type' => 'venue_cluster', 'violation_count' => 1, 'is_catch_all' => false, 'action_type' => 'warn', 'duration_days' => null],
            ['target_type' => 'venue_cluster', 'violation_count' => 2, 'is_catch_all' => false, 'action_type' => 'limit_venue', 'duration_days' => 14],
            ['target_type' => 'venue_cluster', 'violation_count' => 3, 'is_catch_all' => false, 'action_type' => 'block_venue', 'duration_days' => 30],
            ['target_type' => 'venue_cluster', 'violation_count' => 4, 'is_catch_all' => true, 'action_type' => 'terminate_contract', 'duration_days' => null],
        ];

        foreach ($rows as $index => $row) {
            PenaltyEscalationRule::query()->updateOrCreate(
                [
                    'system_policy_id' => $policy->id,
                    'target_type' => $row['target_type'],
                    'violation_count' => $row['violation_count'],
                ],
                $row + [
                    'notify_channels' => ['app', 'email'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
