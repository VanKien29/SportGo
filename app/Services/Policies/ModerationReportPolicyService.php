<?php

namespace App\Services\Policies;

use App\Models\AuditLog;
use App\Models\Notification;
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
            'target_type' => 'post',
            'minimum_reports' => 5,
            'minimum_unique_reporters' => 2,
            'window_days' => 14,
            'actions' => ['pending_review', 'notify_admin'],
        ];
    }

    public function defaultThresholds(): array
    {
        return [
            [
                'key' => 'post_default',
                'object_type' => 'post',
                'object_type_label' => 'Bài viết cộng đồng',
                'min_reports' => 5,
                'min_distinct_reporters' => 2,
                'within_days' => 14,
                'action' => 'pending_review',
                'action_label' => $this->actionLabels()['pending_review'],
                'notify_admin' => true,
                'notify_reported_user' => false,
                'is_active' => true,
            ],
            [
                'key' => 'comment_default',
                'object_type' => 'comment',
                'object_type_label' => 'Bình luận',
                'min_reports' => 3,
                'min_distinct_reporters' => 2,
                'within_days' => 7,
                'action' => 'hide_temporarily',
                'action_label' => $this->actionLabels()['hide_temporarily'],
                'notify_admin' => true,
                'notify_reported_user' => false,
                'is_active' => true,
            ],
            [
                'key' => 'venue_default',
                'object_type' => 'venue',
                'object_type_label' => 'Sân / cụm sân',
                'min_reports' => 5,
                'min_distinct_reporters' => 2,
                'within_days' => 30,
                'action' => 'manual_review',
                'action_label' => $this->actionLabels()['manual_review'],
                'notify_admin' => true,
                'notify_reported_user' => false,
                'is_active' => true,
            ],
            [
                'key' => 'owner_default',
                'object_type' => 'owner',
                'object_type_label' => 'Chủ sân',
                'min_reports' => 5,
                'min_distinct_reporters' => 2,
                'within_days' => 30,
                'action' => 'mark_warning',
                'action_label' => $this->actionLabels()['mark_warning'],
                'notify_admin' => true,
                'notify_reported_user' => true,
                'is_active' => true,
            ],
            [
                'key' => 'user_default',
                'object_type' => 'user',
                'object_type_label' => 'Người dùng',
                'min_reports' => 5,
                'min_distinct_reporters' => 3,
                'within_days' => 30,
                'action' => 'mark_warning',
                'action_label' => $this->actionLabels()['mark_warning'],
                'notify_admin' => true,
                'notify_reported_user' => true,
                'is_active' => true,
            ],
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

    public function thresholdsFromRule(?PolicyRule $rule): array
    {
        if (! $rule) {
            return $this->validateThresholds($this->defaultThresholds());
        }

        $c = $rule->condition_json ?? [];
        $r = $rule->result_json ?? [];

        if (isset($c['threshold']) || isset($c['count_mode'])) {
            $action = $r['action'] ?? 'warning';
            return $this->validateThresholds([[
                'key' => 'rule_' . $rule->id,
                'object_type' => $c['reportable_type'] ?? 'user',
                'min_reports' => ($c['count_mode'] ?? 'distinct_reporters') === 'total_reports' ? (int) ($c['threshold'] ?? 1) : 1,
                'min_distinct_reporters' => ($c['count_mode'] ?? 'distinct_reporters') === 'distinct_reporters' ? (int) ($c['threshold'] ?? 1) : 1,
                'within_days' => (int) ($c['window_days'] ?? 7),
                'action' => $action,
                'lock_duration_days' => $r['lock_duration_days'] ?? null,
                'notify_admin' => $r['notify_admin'] ?? false,
                'is_active' => $rule->is_active ?? true,
                'summary' => $r['summary_vi'] ?? '',
            ]]);
        }

        $result = $rule->result_json ?: [];
        if (isset($result['thresholds']) && is_array($result['thresholds'])) {
            return $this->validateThresholds($result['thresholds']);
        }

        return $this->validateThresholds([$this->thresholdFromLegacyConfig($this->configFromRule($rule))]);
    }

    public function validateThresholds(array $thresholds): array
    {
        $errors = [];
        $normalized = collect($thresholds)
            ->values()
            ->map(function (array $threshold, int $index) use (&$errors): array {
                $objectType = (string) ($threshold['object_type'] ?? $threshold['target_type'] ?? 'post');
                $action = (string) ($threshold['action'] ?? ($threshold['actions'][0] ?? 'notify_admin'));
                if ($action === 'require_admin_review') {
                    $action = 'pending_review';
                }
                $minReports = (int) ($threshold['min_reports'] ?? $threshold['minimum_reports'] ?? 0);
                $minDistinct = (int) ($threshold['min_distinct_reporters'] ?? $threshold['minimum_unique_reporters'] ?? 0);
                $withinDays = (int) ($threshold['within_days'] ?? $threshold['window_days'] ?? 0);
                $isActive = array_key_exists('is_active', $threshold) ? (bool) $threshold['is_active'] : true;
                $notifyAdmin = array_key_exists('notify_admin', $threshold)
                    ? (bool) $threshold['notify_admin']
                    : in_array('notify_admin', (array) ($threshold['actions'] ?? []), true);

                if (! array_key_exists($objectType, $this->targetTypeLabels())) {
                    $errors["thresholds.{$index}.object_type"] = 'Đối tượng áp dụng không hợp lệ.';
                }

                if ($minReports <= 0) {
                    $errors["thresholds.{$index}.min_reports"] = 'Số báo cáo tối thiểu phải lớn hơn 0.';
                }

                if ($minDistinct <= 0) {
                    $errors["thresholds.{$index}.min_distinct_reporters"] = 'Số người báo cáo khác nhau phải lớn hơn 0.';
                }

                if ($withinDays <= 0) {
                    $errors["thresholds.{$index}.within_days"] = 'Khoảng thời gian xét phải lớn hơn 0 ngày.';
                }

                if ($minDistinct > $minReports && $minReports > 1) {
                    $errors["thresholds.{$index}.min_distinct_reporters"] = 'Số người báo cáo khác nhau không được lớn hơn số báo cáo.';
                }

                if (! in_array($action, $this->allowedActionsForObject($objectType), true)) {
                    $errors["thresholds.{$index}.action"] = 'Hành động này chưa được backend hỗ trợ cho đối tượng đã chọn.';
                }

                return [
                    'key' => (string) ($threshold['key'] ?? "{$objectType}_{$action}_{$index}"),
                    'object_type' => $objectType,
                    'object_type_label' => $this->targetTypeLabels()[$objectType] ?? $objectType,
                    'min_reports' => $minReports,
                    'min_distinct_reporters' => $minDistinct,
                    'within_days' => $withinDays,
                    'action' => $action,
                    'action_label' => $this->actionLabels()[$action] ?? $action,
                    'lock_duration_days' => $threshold['lock_duration_days'] ?? null,
                    'notify_admin' => $notifyAdmin,
                    'notify_reported_user' => (bool) ($threshold['notify_reported_user'] ?? false),
                    'is_active' => $isActive,
                    'summary' => $this->thresholdSummary([
                        'object_type' => $objectType,
                        'min_reports' => $minReports,
                        'min_distinct_reporters' => $minDistinct,
                        'within_days' => $withinDays,
                        'action' => $action,
                        'notify_admin' => $notifyAdmin,
                    ]),
                ];
            })
            ->all();

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    public function thresholdConditionJson(array $thresholds): array
    {
        $normalized = $this->validateThresholds($thresholds);

        return [
            'uses_moderation_threshold_table' => true,
            'thresholds' => collect($normalized)
                ->map(fn (array $threshold): array => [
                    'key' => $threshold['key'],
                    'object_type' => $threshold['object_type'],
                    'report_count' => ['gte' => $threshold['min_reports']],
                    'unique_reporters' => ['gte' => $threshold['min_distinct_reporters']],
                    'window_days' => $threshold['within_days'],
                    'is_active' => $threshold['is_active'],
                ])
                ->values()
                ->all(),
        ];
    }

    public function thresholdResultJson(array $thresholds): array
    {
        $normalized = $this->validateThresholds($thresholds);

        return [
            'thresholds' => $normalized,
            'summary_vi' => collect($normalized)->map(fn (array $threshold): string => $threshold['summary'])->implode(' '),
        ];
    }

    public function payload(?PolicyRule $rule, bool $canEdit): array
    {
        $config = $this->configFromRule($rule);
        $thresholds = $this->thresholdsFromRule($rule);

        return [
            'is_supported' => true,
            'has_rule' => (bool) $rule,
            'rule_id' => $rule?->id,
            'rule_name' => $rule?->rule_name ?: 'Ngưỡng xử lý báo cáo',
            'summary' => collect($thresholds)->map(fn (array $threshold): string => $threshold['summary'])->implode(' '),
            'config' => $config,
            'thresholds' => $thresholds,
            'target_type_label' => $this->targetTypeLabels()[$config['target_type']] ?? 'Nội dung',
            'action_labels' => collect($config['actions'])
                ->map(fn (string $action): string => $this->actionLabels()[$action] ?? $action)
                ->values()
                ->all(),
            'target_type_options' => $this->options($this->targetTypeLabels()),
            'action_options' => $this->actionOptionsByObject(),
            'can_edit' => $canEdit,
        ];
    }

    public function evaluate(Model|string $reportable, ?string $reportableId = null, ?User $actor = null): array
    {
        [$type, $id, $target] = $this->resolveTarget($reportable, $reportableId);
        $objectType = $this->objectTypeAlias($type);
        $policy = SystemPolicy::query()
            ->where('key', 'moderation')
            ->where('status', 'active')
            ->where('is_active', true)
            ->with(['rules' => fn ($query) => $query->whereIn('rule_type', [self::RULE_TYPE, 'moderation_score_threshold'])->where('is_active', true)->orderByDesc('priority')])
            ->orderByDesc('version')
            ->first();

        $thresholds = collect();

        if ($policy) {
            $dbThresholds = \App\Models\ModerationThreshold::where('system_policy_id', $policy->id)->get();
            foreach ($dbThresholds as $dbThreshold) {
                $rule = $policy->rules->firstWhere('rule_code', 'moderation_score_' . $dbThreshold->target_type);
                $isActionActive = true;
                if ($dbThreshold->target_type === 'user') {
                    $isActionActive = $rule ? ($rule->result_json['is_auto_lock_enabled'] ?? false) : false;
                }

                // Action threshold
                $action = in_array($dbThreshold->target_type, ['community_post', 'venue_post', 'comment']) ? 'hide_temporarily' : 'auto_lock';
                $thresholds->push([
                    'key' => "mod_{$dbThreshold->target_type}_action",
                    'object_type' => $dbThreshold->target_type,
                    'object_type_label' => $this->targetTypeLabels()[$dbThreshold->target_type] ?? $dbThreshold->target_type,
                    'min_reports' => $dbThreshold->action_threshold,
                    'min_distinct_reporters' => $dbThreshold->unique_reporters_threshold,
                    'within_days' => $dbThreshold->timeframe_days,
                    'action' => $action,
                    'lock_duration_days' => $rule ? ($rule->result_json['lock_duration_days'] ?? 7) : 7,
                    'notify_admin' => true,
                    'is_active' => $isActionActive,
                    'summary' => $this->thresholdSummary([
                        'object_type' => $dbThreshold->target_type,
                        'min_reports' => $dbThreshold->action_threshold,
                        'min_distinct_reporters' => $dbThreshold->unique_reporters_threshold,
                        'within_days' => $dbThreshold->timeframe_days,
                        'action' => $action,
                        'notify_admin' => true,
                    ]),
                    '_rule' => $rule,
                ]);

                // Warning threshold
                $warnAction = in_array($dbThreshold->target_type, ['community_post', 'venue_post', 'comment']) ? 'notify_admin' : 'warning';
                $thresholds->push([
                    'key' => "mod_{$dbThreshold->target_type}_warning",
                    'object_type' => $dbThreshold->target_type,
                    'object_type_label' => $this->targetTypeLabels()[$dbThreshold->target_type] ?? $dbThreshold->target_type,
                    'min_reports' => $dbThreshold->warning_threshold,
                    'min_distinct_reporters' => $dbThreshold->unique_reporters_threshold,
                    'within_days' => $dbThreshold->timeframe_days,
                    'action' => $warnAction,
                    'notify_admin' => true,
                    'is_active' => true,
                    'summary' => $this->thresholdSummary([
                        'object_type' => $dbThreshold->target_type,
                        'min_reports' => $dbThreshold->warning_threshold,
                        'min_distinct_reporters' => $dbThreshold->unique_reporters_threshold,
                        'within_days' => $dbThreshold->timeframe_days,
                        'action' => $warnAction,
                        'notify_admin' => true,
                    ]),
                    '_rule' => $rule,
                ]);
            }
            
            if ($thresholds->isEmpty()) {
                $rules = $policy->rules;
                foreach ($rules as $rule) {
                    if ($rule->rule_type === self::RULE_TYPE) {
                        foreach ($this->thresholdsFromRule($rule) as $t) {
                            $t['_rule'] = $rule;
                            $thresholds->push($t);
                        }
                    }
                }
            }
        }

        $thresholds = $thresholds
            ->filter(fn (array $threshold): bool => (bool) ($threshold['is_active'] ?? true))
            ->filter(fn (array $threshold): bool => $this->thresholdMatchesObject($threshold['object_type'], $objectType, $target))
            ->sortByDesc(fn ($t) => max($t['min_reports'], $t['min_distinct_reporters']))
            ->values();

        $results = [];
        foreach ($thresholds as $threshold) {
            $rule = $threshold['_rule'] ?? null;
            $reportStats = $this->reportStats($type, $id, (int) $threshold['within_days']);
            $matched = $reportStats['total'] >= $threshold['min_reports']
                && $reportStats['unique_reporters'] >= $threshold['min_distinct_reporters'];
            $alreadyApplied = $matched && $this->alreadyApplied($rule, $type, $id, $threshold);
            $applied = [];

            if ($matched && ! $alreadyApplied && $target) {
                $applied = $this->applyThresholdAction($target, $threshold, $policy, $rule, $actor);
            }

            $result = [
                'matched' => $matched,
                'already_applied' => $alreadyApplied,
                'threshold_key' => $threshold['key'],
                'object_type' => $objectType,
                'reportable_type' => $type,
                'reportable_id' => $id,
                'report_count' => $reportStats['total'],
                'unique_reporters' => $reportStats['unique_reporters'],
                'window_days' => $threshold['within_days'],
                'action' => $threshold['action'],
                'notify_admin' => (bool) $threshold['notify_admin'],
                'applied_actions' => $applied,
                'summary' => $matched
                    ? $this->matchedThresholdSummary($threshold, $reportStats, $alreadyApplied)
                    : $this->unmatchedThresholdSummary($threshold, $reportStats),
            ];

            $this->logEvaluation($policy, $rule, $type, $id, $actor, $result);
            $results[] = $result;
        }

        return [
            'matched' => collect($results)->contains(fn (array $result): bool => (bool) $result['matched']),
            'reportable_type' => $type,
            'reportable_id' => $id,
            'object_type' => $objectType,
            'results' => $results,
            'applied_actions' => collect($results)->flatMap(fn (array $result): array => $result['applied_actions'])->values()->all(),
        ];
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
            'venue' => 'cụm sân / sân',
            'owner' => 'chủ sân',
            'user' => 'người dùng',
            'account' => 'tài khoản',
        ];
    }

    public function actionLabels(): array
    {
        return [
            'pending_review' => 'chuyển sang chờ kiểm duyệt',
            'hide_temporarily' => 'ẩn tạm nội dung',
            'notify_admin' => 'thông báo admin',
            'manual_review' => 'chuyển admin xử lý thủ công',
            'mark_warning' => 'đưa vào diện cảnh báo',
            'temporary_lock' => 'khóa tạm nếu hệ thống hỗ trợ',
            'warning' => 'cảnh báo',
            'auto_lock' => 'khóa tự động',
        ];
    }

    private function thresholdFromLegacyConfig(array $config): array
    {
        $normalized = $this->normalizeConfig($config);
        $action = collect($normalized['actions'])
            ->first(fn (string $action): bool => $action !== 'notify_admin') ?: 'notify_admin';

        return [
            'object_type' => $normalized['target_type'] === 'content' ? 'post' : $normalized['target_type'],
            'min_reports' => $normalized['minimum_reports'],
            'min_distinct_reporters' => $normalized['minimum_unique_reporters'],
            'within_days' => $normalized['window_days'],
            'action' => $action,
            'notify_admin' => in_array('notify_admin', $normalized['actions'], true),
            'notify_reported_user' => false,
            'is_active' => true,
        ];
    }

    private function actionOptionsByObject(): array
    {
        return collect(array_keys($this->targetTypeLabels()))
            ->mapWithKeys(fn (string $objectType): array => [
                $objectType => $this->options(
                    collect($this->actionLabels())
                        ->only($this->allowedActionsForObject($objectType))
                        ->all()
                ),
            ])
            ->all();
    }

    private function allowedActionsForObject(string $objectType): array
    {
        return match ($objectType) {
            'content', 'post' => ['pending_review', 'hide_temporarily', 'notify_admin', 'manual_review'],
            'comment' => ['hide_temporarily', 'notify_admin', 'manual_review'],
            'venue' => ['notify_admin', 'manual_review'],
            'owner', 'user', 'account' => ['notify_admin', 'manual_review', 'mark_warning', 'warning', 'auto_lock'],
            default => ['notify_admin', 'manual_review'],
        };
    }

    private function thresholdMatchesObject(string $thresholdType, string $objectType, ?Model $target): bool
    {
        if ($thresholdType === $objectType || ($thresholdType === 'post' && $objectType === 'content')) {
            return true;
        }

        if (! $target instanceof User || ! in_array($thresholdType, ['owner', 'user', 'account'], true)) {
            return false;
        }

        if ($thresholdType === 'account') {
            return true;
        }

        $isOwner = $target->roles()->whereIn('roles.name', ['owner', 'venue_owner'])->exists();

        return $thresholdType === 'owner' ? $isOwner : ! $isOwner;
    }

    private function thresholdSummary(array $threshold): string
    {
        $target = $this->targetTypeLabels()[$threshold['object_type']] ?? $threshold['object_type'];
        $action = $this->actionLabels()[$threshold['action']] ?? $threshold['action'];
        $notify = ($threshold['notify_admin'] ?? false) ? ' và thông báo admin' : '';

        return "Nếu {$target} nhận từ {$threshold['min_reports']} báo cáo hợp lệ bởi ít nhất {$threshold['min_distinct_reporters']} người khác nhau trong {$threshold['within_days']} ngày, hệ thống {$action}{$notify}.";
    }

    private function normalizeConfig(array $config): array
    {
        $default = $this->defaultConfig();
        $actions = $config['actions'] ?? $default['actions'];
        if (is_string($actions)) {
            $actions = [$actions];
        }

        $actions = array_map(function ($act) {
            return $act === 'require_admin_review' ? 'pending_review' : $act;
        }, (array) $actions);

        return [
            'target_type' => (string) ($config['target_type'] ?? $default['target_type']),
            'minimum_reports' => (int) ($config['minimum_reports'] ?? $default['minimum_reports']),
            'minimum_unique_reporters' => (int) ($config['minimum_unique_reporters'] ?? $default['minimum_unique_reporters']),
            'window_days' => (int) ($config['window_days'] ?? $default['window_days']),
            'actions' => array_values(array_unique(array_filter($actions))),
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

    private function objectTypeAlias(string $type): string
    {
        $normalized = strtolower(str_replace('\\', '/', $type));
        $base = basename($normalized);

        return match (true) {
            in_array($normalized, ['post', 'content', 'community_posts', 'venue_posts'], true),
            in_array($base, ['communitypost', 'venuepost', 'playerpost'], true) => 'post',
            in_array($normalized, ['comment', 'community_post_comments'], true),
            in_array($base, ['communitypostcomment'], true) => 'comment',
            in_array($normalized, ['venue', 'venue_clusters', 'venue_courts'], true),
            in_array($base, ['venuecluster', 'venuecourt'], true) => 'venue',
            in_array($normalized, ['account', 'user', 'users'], true),
            $base === 'user' => 'account',
            default => 'post',
        };
    }

    private function reportStats(string $type, string $id, int $windowDays): array
    {
        if (! Schema::hasTable('reports')) {
            return ['total' => 0, 'unique_reporters' => 0];
        }

        $query = DB::table('reports')
            ->where('reportable_type', $type)
            ->where('reportable_id', $id)
            ->where('status', '!=', 'dismissed')
            ->where('created_at', '>=', now()->subDays($windowDays));

        return [
            'total' => (clone $query)->count(),
            'unique_reporters' => (clone $query)->distinct('reporter_id')->count('reporter_id'),
        ];
    }

    private function alreadyApplied(?PolicyRule $rule, string $type, string $id, array $threshold): bool
    {
        if (! Schema::hasTable('policy_evaluation_logs') || ! $rule) {
            return false;
        }

        // For auto_lock: check if the user is currently locked, not just if we applied it before
        // If the user was unlocked by an admin, allow re-evaluation
        if ($threshold['action'] === 'auto_lock') {
            $target = class_exists($type) ? $type::find($id) : null;
            if ($target && ($target->status ?? null) === 'locked') {
                return true; // Already locked, skip
            }
            return false; // Not locked, allow re-evaluation
        }

        return PolicyEvaluationLog::query()
            ->where('policy_rule_id', $rule->id)
            ->where('entity_type', $type)
            ->where('entity_id', $id)
            ->where('result_data->matched', true)
            ->where('result_data->threshold_key', $threshold['key'])
            ->whereJsonLength('result_data->applied_actions', '>', 0)
            ->exists();
    }

    private function applyThresholdAction(Model $target, array $threshold, ?SystemPolicy $policy, ?PolicyRule $rule, ?User $actor): array
    {
        $oldValues = $target->toArray();
        $applied = [];
        $action = $threshold['action'];

        if ($action === 'pending_review' && $this->supportsStatusValue($target, 'pending_review')) {
            $updates = ['status' => 'pending_review'];
            if (Schema::hasColumn($target->getTable(), 'status_reason')) {
                $updates['status_reason'] = 'Tự động chuyển chờ kiểm duyệt do đạt ngưỡng báo cáo.';
            }
            $target->forceFill($updates)->save();
            $applied[] = 'pending_review';
        }

        if ($action === 'hide_temporarily' && $this->supportsStatusValue($target, 'hidden')) {
            $updates = ['status' => 'hidden'];
            if (Schema::hasColumn($target->getTable(), 'status_reason')) {
                $updates['status_reason'] = 'Tự động ẩn tạm do đạt ngưỡng báo cáo.';
            }
            $target->forceFill($updates)->save();
            $applied[] = 'hide_temporarily';
        }

        if ($action === 'auto_lock') {
            $ruleResult = $rule ? ($rule->result_json ?? []) : [];
            $lockReason = $ruleResult['reason'] ?? 'Tự động khóa tài khoản do đạt ngưỡng báo cáo.';
            $lockUntil = isset($threshold['lock_duration_days']) && $threshold['lock_duration_days'] ? now()->addDays((int) $threshold['lock_duration_days']) : null;
            $updates = [
                'status' => 'locked',
                'lock_type' => 'auto',
                'locked_at' => now(),
                'locked_until' => $lockUntil,
            ];
            if (Schema::hasColumn($target->getTable(), 'status_reason')) {
                $updates['status_reason'] = $lockReason;
            }
            $target->forceFill($updates)->save();
            $applied[] = 'auto_lock';

            if (class_exists(\App\Models\UserLockLog::class)) {
                \App\Models\UserLockLog::create([
                    'user_id' => $target->id,
                    'action' => 'locked',
                    'reason' => $lockReason,
                    'auto_triggered' => true,
                    'lock_until' => $lockUntil,
                    'created_at' => now(),
                ]);
            }
        }

        if ($action === 'warning') {
            if (Schema::hasColumn($target->getTable(), 'status_reason') && $target->status !== 'locked' && $target->status !== 'deactivated') {
                $target->forceFill(['status_reason' => 'Đang trong diện cảnh báo do đạt ngưỡng báo cáo.'])->save();
            }
            $applied[] = 'warning';
        }

        if (in_array($action, ['notify_admin', 'manual_review', 'mark_warning', 'warning', 'auto_lock'], true)) {
            $applied[] = $action;
        }

        if ((bool) ($threshold['notify_admin'] ?? false) || in_array($action, ['notify_admin', 'manual_review', 'mark_warning', 'warning', 'auto_lock'], true)) {
            $this->notifyAdmins($target, $threshold, $policy);
            if (! in_array('notify_admin', $applied, true)) {
                $applied[] = 'notify_admin';
            }
        }

        if ((bool) ($threshold['notify_reported_user'] ?? false)) {
            $this->notifyReportedUser($target, $threshold);
        }

        $this->auditAction($target, $oldValues, $target->fresh()->toArray(), $threshold, $policy, $rule, $actor, $applied);

        return array_values(array_unique($applied));
    }

    private function supportsStatusValue(Model $target, string $status): bool
    {
        if (! Schema::hasColumn($target->getTable(), 'status')) {
            return false;
        }

        return match ($target->getTable()) {
            'community_posts', 'venue_posts' => in_array($status, ['pending_review', 'hidden'], true),
            'community_post_comments' => $status === 'hidden',
            default => false,
        };
    }

    private function notifyAdmins(Model $target, array $threshold, ?SystemPolicy $policy): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin']))
            ->select('id')
            ->chunk(100, function ($admins) use ($target, $threshold, $policy): void {
                foreach ($admins as $admin) {
                    Notification::query()->create([
                        'user_id' => $admin->id,
                        'type' => 'moderation_threshold_matched',
                        'title' => 'Đối tượng đạt ngưỡng báo cáo',
                        'body' => $threshold['summary'],
                        'reference_type' => $target->getTable(),
                        'reference_id' => (string) $target->getKey(),
                        'data' => [
                            'policy_id' => $policy?->id,
                            'threshold_key' => $threshold['key'],
                            'action' => $threshold['action'],
                        ],
                    ]);
                }
            });
    }

    private function notifyReportedUser(Model $target, array $threshold): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $userId = $target->user_id ?? $target->owner_id ?? $target->customer_id ?? null;
        if (! $userId) {
            return;
        }

        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'reported_object_threshold_matched',
            'title' => 'Nội dung của bạn cần được kiểm duyệt',
            'body' => $threshold['summary'],
            'reference_type' => $target->getTable(),
            'reference_id' => (string) $target->getKey(),
            'data' => [
                'threshold_key' => $threshold['key'],
                'action' => $threshold['action'],
            ],
        ]);
    }

    private function auditAction(Model $target, array $oldValues, array $newValues, array $threshold, ?SystemPolicy $policy, ?PolicyRule $rule, ?User $actor, array $applied): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'module' => 'moderation',
            'action' => 'moderation.threshold_applied',
            'entity_type' => $target->getTable(),
            'entity_id' => (string) $target->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'policy_id' => $policy?->id,
                'policy_rule_id' => $rule?->id,
                'threshold' => $threshold,
                'applied_actions' => $applied,
            ],
            'reason' => $threshold['summary'] ?? null,
            'context' => 'policy_evaluator',
            'severity' => in_array($threshold['action'], ['hide_temporarily', 'pending_review'], true) ? 'warning' : 'info',
        ]);
    }

    private function applyActions(Model $target, array $actions): array
    {
        $applied = [];

        if (in_array('pending_review', $actions, true) && $this->supportsStatusValue($target, 'pending_review')) {
            $target->forceFill(['status' => 'pending_review'])->save();
            $applied[] = 'pending_review';
        }

        if (in_array('hide_temporarily', $actions, true) && $this->supportsStatusValue($target, 'hidden')) {
            $target->forceFill(['status' => 'hidden'])->save();
            $applied[] = 'hide_temporarily';
        }

        if (in_array('notify_admin', $actions, true)) {
            $applied[] = 'notify_admin';
        }

        return $applied;
    }

    private function matchedThresholdSummary(array $threshold, array $stats, bool $alreadyApplied): string
    {
        $suffix = $alreadyApplied ? ' Hành động đã được áp dụng trước đó nên không chạy lặp.' : '';

        return "Đã đạt ngưỡng {$threshold['object_type_label']}: {$stats['total']} báo cáo bởi {$stats['unique_reporters']} người trong {$threshold['within_days']} ngày.{$suffix}";
    }

    private function unmatchedThresholdSummary(array $threshold, array $stats): string
    {
        return "Chưa đạt ngưỡng {$threshold['object_type_label']}: {$stats['total']}/{$threshold['min_reports']} báo cáo và {$stats['unique_reporters']}/{$threshold['min_distinct_reporters']} người báo cáo khác nhau.";
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
