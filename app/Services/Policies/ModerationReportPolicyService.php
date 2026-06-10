<?php

namespace App\Services\Policies;

use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ModerationReportPolicyService
{
    public const RULE_TYPE = 'report_threshold_requires_review';

    public function defaultConfig(): array
    {
        return [
            'target_type' => 'content',
            'minimum_reports' => 5,
            'minimum_unique_reporters' => 2,
            'window_days' => 14,
            'actions' => ['pending_review', 'notify_admin'],
        ];
    }

    public function configFromRule(?PolicyRule $rule): array
    {
        if (! $rule) {
            return $this->normalizeConfig($this->defaultConfig());
        }

        return $this->normalizeConfig([
            'target_type' => $rule->condition_json['target_type'] ?? $rule->result_json['target_type'] ?? 'content',
            'minimum_reports' => $rule->condition_json['report_count']['gte'] ?? $rule->condition_json['report_count'] ?? null,
            'minimum_unique_reporters' => $rule->condition_json['unique_reporters']['gte'] ?? $rule->condition_json['unique_reporters'] ?? null,
            'window_days' => $rule->condition_json['window_days'] ?? null,
            'actions' => $rule->result_json['actions'] ?? [$rule->result_json['action'] ?? 'pending_review'],
        ]);
    }

    public function validateConfig(array $config): array
    {
        $normalized = $this->normalizeConfig($config);
        $errors = [];

        foreach (['minimum_reports', 'minimum_unique_reporters', 'window_days'] as $field) {
            if (! is_numeric($normalized[$field]) || (int) $normalized[$field] <= 0) {
                $errors[$field] = 'Giá trị phải lớn hơn 0.';
            }
        }

        if ((int) $normalized['minimum_unique_reporters'] > (int) $normalized['minimum_reports']) {
            $errors['minimum_unique_reporters'] = 'Số người báo cáo khác nhau không được lớn hơn tổng số báo cáo.';
        }

        if (! array_key_exists($normalized['target_type'], $this->targetTypeLabels())) {
            $errors['target_type'] = 'Đối tượng áp dụng không hợp lệ.';
        }

        $allowedActions = array_keys($this->actionLabels());
        foreach ($normalized['actions'] as $action) {
            if (! in_array($action, $allowedActions, true)) {
                $errors['actions'] = 'Hành động khi đạt ngưỡng không hợp lệ.';
                break;
            }
        }

        if ($normalized['actions'] === []) {
            $errors['actions'] = 'Vui lòng chọn ít nhất một hành động khi đạt ngưỡng.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public function conditionJson(array $config): array
    {
        $normalized = $this->validateConfig($config);

        return [
            'target_type' => $normalized['target_type'],
            'report_count' => ['gte' => (int) $normalized['minimum_reports']],
            'unique_reporters' => ['gte' => (int) $normalized['minimum_unique_reporters']],
            'window_days' => (int) $normalized['window_days'],
        ];
    }

    public function resultJson(array $config): array
    {
        $normalized = $this->validateConfig($config);

        return [
            'actions' => $normalized['actions'],
            'action' => $normalized['actions'][0],
            'summary_vi' => $this->summary($normalized),
        ];
    }

    public function payload(?PolicyRule $rule, bool $canEdit): array
    {
        $config = $this->configFromRule($rule);

        return [
            'is_supported' => true,
            'has_rule' => (bool) $rule,
            'rule_id' => $rule?->id,
            'rule_name' => $rule?->rule_name ?: 'Ngưỡng xử lý báo cáo',
            'summary' => $this->summary($config),
            'config' => $config,
            'target_type_label' => $this->targetTypeLabels()[$config['target_type']] ?? 'Nội dung',
            'action_labels' => collect($config['actions'])
                ->map(fn (string $action): string => $this->actionLabels()[$action] ?? $action)
                ->values()
                ->all(),
            'target_type_options' => $this->options($this->targetTypeLabels()),
            'action_options' => $this->options($this->actionLabels()),
            'can_edit' => $canEdit,
        ];
    }

    public function evaluate(Model|string $reportable, string $reportableId = null, ?User $actor = null): array
    {
        [$type, $id, $target] = $this->resolveTarget($reportable, $reportableId);
        $policy = SystemPolicy::query()
            ->where('key', 'moderation')
            ->where('status', 'active')
            ->where('is_active', true)
            ->with(['rules' => fn ($query) => $query->where('rule_type', self::RULE_TYPE)->where('is_active', true)->orderByDesc('priority')])
            ->orderByDesc('version')
            ->first();

        $rule = $policy?->rules->first();
        $config = $this->configFromRule($rule);
        $reportStats = $this->reportStats($type, $id, (int) $config['window_days']);
        $matched = $reportStats['total'] >= $config['minimum_reports']
            && $reportStats['unique_reporters'] >= $config['minimum_unique_reporters'];

        $applied = [];
        if ($matched && $target) {
            $applied = $this->applyActions($target, $config['actions']);
        }

        $result = [
            'matched' => $matched,
            'reportable_type' => $type,
            'reportable_id' => $id,
            'report_count' => $reportStats['total'],
            'unique_reporters' => $reportStats['unique_reporters'],
            'window_days' => $config['window_days'],
            'actions' => $config['actions'],
            'applied_actions' => $applied,
            'summary' => $matched
                ? $this->matchedSummary($config, $reportStats)
                : $this->unmatchedSummary($config, $reportStats),
        ];

        $this->logEvaluation($policy, $rule, $type, $id, $actor, $result);

        return $result;
    }

    public function summary(array $config): string
    {
        $normalized = $this->normalizeConfig($config);
        $target = $this->targetTypeLabels()[$normalized['target_type']] ?? 'nội dung';
        $actions = collect($normalized['actions'])
            ->map(fn (string $action): string => mb_strtolower($this->actionLabels()[$action] ?? $action))
            ->implode(' và ');

        return "Nếu {$target} nhận từ {$normalized['minimum_reports']} báo cáo hợp lệ bởi ít nhất {$normalized['minimum_unique_reporters']} người khác nhau trong {$normalized['window_days']} ngày, hệ thống {$actions}.";
    }

    public function targetTypeLabels(): array
    {
        return [
            'content' => 'nội dung',
            'post' => 'bài viết',
            'comment' => 'bình luận',
            'account' => 'tài khoản',
        ];
    }

    public function actionLabels(): array
    {
        return [
            'pending_review' => 'chuyển sang chờ kiểm duyệt',
            'hide_temporarily' => 'ẩn tạm nội dung',
            'notify_admin' => 'thông báo admin',
            'temporary_lock' => 'khóa tạm nếu hệ thống hỗ trợ',
        ];
    }

    private function normalizeConfig(array $config): array
    {
        $default = $this->defaultConfig();
        $actions = $config['actions'] ?? $default['actions'];
        if (is_string($actions)) {
            $actions = [$actions];
        }

        return [
            'target_type' => (string) ($config['target_type'] ?? $default['target_type']),
            'minimum_reports' => (int) ($config['minimum_reports'] ?? $default['minimum_reports']),
            'minimum_unique_reporters' => (int) ($config['minimum_unique_reporters'] ?? $default['minimum_unique_reporters']),
            'window_days' => (int) ($config['window_days'] ?? $default['window_days']),
            'actions' => array_values(array_unique(array_filter((array) $actions))),
        ];
    }

    private function options(array $labels): array
    {
        return collect($labels)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => ucfirst($label)])
            ->values()
            ->all();
    }

    private function resolveTarget(Model|string $reportable, ?string $reportableId): array
    {
        if ($reportable instanceof Model) {
            return [get_class($reportable), (string) $reportable->getKey(), $reportable];
        }

        $target = class_exists($reportable) && $reportableId
            ? $reportable::query()->find($reportableId)
            : null;

        return [$reportable, (string) $reportableId, $target];
    }

    private function reportStats(string $type, string $id, int $windowDays): array
    {
        if (! Schema::hasTable('reports')) {
            return ['total' => 0, 'unique_reporters' => 0];
        }

        $query = DB::table('reports')
            ->where('reportable_type', $type)
            ->where('reportable_id', $id)
            ->where('created_at', '>=', now()->subDays($windowDays));

        return [
            'total' => (clone $query)->count(),
            'unique_reporters' => (clone $query)->distinct('reporter_id')->count('reporter_id'),
        ];
    }

    private function applyActions(Model $target, array $actions): array
    {
        $applied = [];

        if (in_array('pending_review', $actions, true) && Schema::hasColumn($target->getTable(), 'status')) {
            $reviewStatus = $target->getTable() === 'community_post_comments' ? 'hidden' : 'pending_review';
            $target->forceFill(['status' => $reviewStatus])->save();
            $applied[] = 'pending_review';
        }

        if (in_array('hide_temporarily', $actions, true) && Schema::hasColumn($target->getTable(), 'status')) {
            $target->forceFill(['status' => 'hidden'])->save();
            $applied[] = 'hide_temporarily';
        }

        if (in_array('notify_admin', $actions, true)) {
            $applied[] = 'notify_admin';
        }

        return $applied;
    }

    private function matchedSummary(array $config, array $stats): string
    {
        return "Đã đạt ngưỡng: {$stats['total']} báo cáo bởi {$stats['unique_reporters']} người trong {$config['window_days']} ngày.";
    }

    private function unmatchedSummary(array $config, array $stats): string
    {
        return "Chưa đạt ngưỡng: {$stats['total']}/{$config['minimum_reports']} báo cáo và {$stats['unique_reporters']}/{$config['minimum_unique_reporters']} người báo cáo khác nhau.";
    }

    private function logEvaluation(?SystemPolicy $policy, ?PolicyRule $rule, string $type, string $id, ?User $actor, array $result): void
    {
        if (! Schema::hasTable('policy_evaluation_logs') || ! $policy) {
            return;
        }

        PolicyEvaluationLog::query()->create([
            'system_policy_id' => $policy->id,
            'policy_rule_id' => $rule?->id,
            'action_code' => $rule?->action_code ?: 'post.report',
            'entity_type' => $type,
            'entity_id' => $id,
            'input_data' => [
                'report_count' => $result['report_count'],
                'unique_reporters' => $result['unique_reporters'],
                'window_days' => $result['window_days'],
            ],
            'result_data' => $result,
            'policy_version_snapshot' => $policy->only(['id', 'key', 'version', 'title', 'policy_type']),
            'rule_snapshot' => $rule?->only(['id', 'rule_code', 'rule_name', 'rule_type', 'condition_json', 'result_json']),
            'evaluated_by_type' => $actor ? 'user' : 'system',
            'evaluated_by_id' => $actor?->id,
        ]);
    }
}
