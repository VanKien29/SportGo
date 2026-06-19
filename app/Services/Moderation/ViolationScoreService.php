<?php

namespace App\Services\Moderation;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\ModerationThreshold;
use App\Models\Notification;
use App\Models\PolicyEvaluationLog;
use App\Models\Report;
use App\Models\SeverityLevel;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use App\Models\ViolationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ViolationScoreService
{
    public function handleReportCreated(Report $report): array
    {
        $score = $this->calculateScore($report);
        if ((int) $report->score_contribution !== $score) {
            $report->forceFill(['score_contribution' => $score])->saveQuietly();
            $report = $report->fresh(['violationType']);
        }

        $targetType = $this->normalizeTargetType($report->reportable_type);
        $targetId = (string) $report->reportable_id;
        $currentScore = $this->getAccumulatedScore($targetType, $targetId);
        $result = $this->checkThresholds($targetType, $targetId, $currentScore, $report);
        $target = $this->resolveTarget($report);
        $applied = [];

        if ($target && ($result['isImmediate'] || $result['shouldAutoHide'])) {
            $applied[] = $result['isImmediate'] ? 'immediate_hide' : 'auto_hide';
            $this->hideTarget($target, $result['isImmediate'] ? 'Ẩn ngay do loại vi phạm nghiêm trọng.' : 'Ẩn tạm do đạt ngưỡng điểm vi phạm.');
            $report->forceFill([
                'auto_action_taken' => $result['isImmediate'] ? 'immediate_hide' : 'auto_hide',
                'auto_actioned_at' => now(),
            ])->saveQuietly();
        }

        if ($result['isImmediate'] || $result['shouldAlertAdmin'] || $result['shouldAutoHide']) {
            $this->notifyAdmins($report, $targetType, $currentScore, $result);
            $applied[] = 'notify_admin';
        }

        $this->logEvaluation($report, $targetType, $targetId, $currentScore, $result, $applied);

        return [
            'score' => $score,
            'current_score' => $currentScore,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'result' => $result,
            'applied_actions' => array_values(array_unique($applied)),
        ];
    }

    public function calculateScore(Report $report): int
    {
        $violationType = $report->violationType
            ?: ($report->violation_type_id ? ViolationType::query()->find($report->violation_type_id) : null)
            ?: $this->violationTypeFromReason((string) $report->reason);

        $severity = SeverityLevel::query()
            ->where('code', $report->severity_level ?: 'mild')
            ->first();

        $baseScore = (int) ($violationType?->base_score ?? 1);
        $multiplier = (float) ($severity?->multiplier ?? 1.0);

        return max(1, (int) round($baseScore * $multiplier));
    }

    public function getAccumulatedScore(string $targetType, string $targetId): int
    {
        $threshold = $this->thresholdFor($targetType);
        $windowDays = (int) ($threshold?->score_window_days ?? 30);

        return (int) Report::query()
            ->where('reportable_id', $targetId)
            ->where('status', 'resolved')
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->where(function ($query) use ($targetType): void {
                foreach ($this->reportableTypesFor($targetType) as $type) {
                    $query->orWhere('reportable_type', $type);
                }
            })
            ->sum('score_contribution');
    }

    public function checkThresholds(string $targetType, string $targetId, int $currentScore, ?Report $report = null): array
    {
        $threshold = $this->thresholdFor($targetType);
        $violationType = $report?->violationType
            ?: ($report?->violation_type_id ? ViolationType::query()->find($report->violation_type_id) : null);
        $isImmediate = (bool) ($violationType?->is_immediate ?? false);
        $autoHideScore = (int) ($threshold?->auto_hide_score ?? 10);
        $adminAlertScore = (int) ($threshold?->admin_alert_score ?? 20);

        return [
            'shouldAutoHide' => $isImmediate || $currentScore >= $autoHideScore,
            'shouldAlertAdmin' => $isImmediate || $currentScore >= $adminAlertScore,
            'isImmediate' => $isImmediate,
            'auto_hide_score' => $autoHideScore,
            'admin_alert_score' => $adminAlertScore,
            'score_window_days' => (int) ($threshold?->score_window_days ?? 30),
        ];
    }

    public function normalizeTargetType(string $reportableType): string
    {
        $normalized = strtolower(str_replace('\\', '/', $reportableType));
        $base = basename($normalized);

        return match (true) {
            in_array($normalized, ['community_post', 'community_posts'], true), $base === 'communitypost' => 'community_post',
            in_array($normalized, ['venue_post', 'venue_posts'], true), $base === 'venuepost' => 'venue_post',
            in_array($normalized, ['comment', 'community_post_comments'], true), $base === 'communitypostcomment' => 'comment',
            in_array($normalized, ['venue_cluster', 'venue_clusters', 'venue'], true), $base === 'venuecluster' => 'venue_cluster',
            in_array($normalized, ['user', 'users', 'account'], true), $base === 'user' => 'user',
            default => 'community_post',
        };
    }

    private function thresholdFor(string $targetType): ?ModerationThreshold
    {
        $policy = SystemPolicy::query()
            ->whereIn('key', ['content_moderation', 'moderation'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        return ModerationThreshold::query()
            ->when($policy, fn ($query) => $query->where('system_policy_id', $policy->id))
            ->where('target_type', $targetType)
            ->first();
    }

    private function violationTypeFromReason(string $reason): ?ViolationType
    {
        $code = match ($reason) {
            'spam' => 'spam',
            'offensive', 'harassment' => 'offensive_lang',
            'fake' => 'misinformation',
            default => 'spam',
        };

        return ViolationType::query()->where('code', $code)->first();
    }

    private function reportableTypesFor(string $targetType): array
    {
        return match ($targetType) {
            'community_post' => [CommunityPost::class, 'community_post', 'community_posts', 'post'],
            'venue_post' => [VenuePost::class, 'venue_post', 'venue_posts'],
            'comment' => [CommunityPostComment::class, 'comment', 'community_post_comments'],
            'venue_cluster' => [VenueCluster::class, 'venue_cluster', 'venue_clusters', 'venue'],
            'user' => [User::class, 'user', 'users', 'account'],
            default => [$targetType],
        };
    }

    private function resolveTarget(Report $report): ?Model
    {
        if ($report->relationLoaded('reportable') && $report->reportable instanceof Model) {
            return $report->reportable;
        }

        if (! class_exists($report->reportable_type)) {
            return null;
        }

        return $report->reportable_type::query()->find($report->reportable_id);
    }

    private function hideTarget(Model $target, string $reason): void
    {
        if (! Schema::hasColumn($target->getTable(), 'status')) {
            return;
        }

        if (! in_array($target->getTable(), ['community_posts', 'community_post_comments', 'venue_posts'], true)) {
            return;
        }

        $updates = ['status' => 'hidden'];
        if (Schema::hasColumn($target->getTable(), 'status_reason')) {
            $updates['status_reason'] = $reason;
        }
        if (Schema::hasColumn($target->getTable(), 'reviewed_at')) {
            $updates['reviewed_at'] = now();
        }

        $target->forceFill($updates)->save();
    }

    private function notifyAdmins(Report $report, string $targetType, int $currentScore, array $result): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin']))
            ->select('id')
            ->chunk(100, function ($admins) use ($report, $targetType, $currentScore, $result): void {
                foreach ($admins as $admin) {
                    Notification::query()->create([
                        'user_id' => $admin->id,
                        'type' => 'violation_score_threshold',
                        'title' => $result['isImmediate'] ? 'Báo cáo cần xử lý gấp' : 'Đối tượng đạt ngưỡng vi phạm',
                        'body' => "Đối tượng {$targetType} đang có {$currentScore} điểm vi phạm.",
                        'reference_type' => 'reports',
                        'reference_id' => $report->id,
                        'data' => [
                            'target_type' => $targetType,
                            'target_id' => $report->reportable_id,
                            'current_score' => $currentScore,
                            'threshold' => $result,
                        ],
                    ]);
                }
            });
    }

    private function logEvaluation(Report $report, string $targetType, string $targetId, int $currentScore, array $result, array $applied): void
    {
        if (! Schema::hasTable('policy_evaluation_logs')) {
            return;
        }

        $policy = SystemPolicy::query()
            ->whereIn('key', ['content_moderation', 'moderation'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        PolicyEvaluationLog::query()->create([
            'system_policy_id' => $policy?->id,
            'policy_rule_id' => null,
            'venue_policy_rule_id' => null,
            'entity_type' => $targetType,
            'entity_id' => $targetId,
            'action_code' => 'report.score_evaluated',
            'input_data' => [
                'report_id' => $report->id,
                'score_contribution' => $report->score_contribution,
                'current_score' => $currentScore,
            ],
            'result_data' => [
                'matched' => $result['shouldAutoHide'] || $result['shouldAlertAdmin'],
                'threshold' => $result,
                'applied_actions' => $applied,
            ],
        ]);
    }
}
