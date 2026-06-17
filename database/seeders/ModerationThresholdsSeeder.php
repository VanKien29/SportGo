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
            ['target_type' => 'community_post', 'auto_hide_score' => 20, 'admin_alert_score' => 10, 'score_window_days' => 30, 'score_reset_days' => 90],
            ['target_type' => 'venue_post', 'auto_hide_score' => 15, 'admin_alert_score' => 8, 'score_window_days' => 30, 'score_reset_days' => 90],
            ['target_type' => 'comment', 'auto_hide_score' => 5, 'admin_alert_score' => 3, 'score_window_days' => 30, 'score_reset_days' => 60],
            ['target_type' => 'user', 'auto_hide_score' => 50, 'admin_alert_score' => 30, 'score_window_days' => 30, 'score_reset_days' => 180],
            ['target_type' => 'venue_cluster', 'auto_hide_score' => 40, 'admin_alert_score' => 25, 'score_window_days' => 30, 'score_reset_days' => 180],
        ];

        foreach ($rows as $row) {
            ModerationThreshold::query()->updateOrCreate(
                ['system_policy_id' => $policy->id, 'target_type' => $row['target_type']],
                $row,
            );
        }
    }
}
