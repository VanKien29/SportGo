<?php

namespace Database\Seeders;

use App\Models\ModerationThreshold;
use App\Models\SystemPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ModerationThresholdsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('moderation_thresholds') || ! Schema::hasTable('system_policies')) {
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
            ['target_type' => 'community_post', 'warning_threshold' => 3, 'action_threshold' => 5, 'unique_reporters_threshold' => 2, 'timeframe_days' => 7],
            ['target_type' => 'venue_post', 'warning_threshold' => 3, 'action_threshold' => 5, 'unique_reporters_threshold' => 2, 'timeframe_days' => 7],
            ['target_type' => 'comment', 'warning_threshold' => 2, 'action_threshold' => 3, 'unique_reporters_threshold' => 2, 'timeframe_days' => 7],
            ['target_type' => 'user', 'warning_threshold' => 5, 'action_threshold' => 10, 'unique_reporters_threshold' => 3, 'timeframe_days' => 30],
            ['target_type' => 'venue_cluster', 'warning_threshold' => 5, 'action_threshold' => 10, 'unique_reporters_threshold' => 3, 'timeframe_days' => 30],
        ];

        foreach ($rows as $row) {
            ModerationThreshold::query()->updateOrCreate(
                ['system_policy_id' => $policy->id, 'target_type' => $row['target_type']],
                $row,
            );
        }
    }
}
