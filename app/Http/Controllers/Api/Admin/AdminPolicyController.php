<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ModerationThreshold;
use App\Models\Notification;
use App\Models\PenaltyEscalationRule;
use App\Models\PolicyActionBinding;
use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\PolicyStatusHistory;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenuePolicyRule;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\PolicyConflictService;
use App\Services\Policies\ModerationReportPolicyService;
use App\Services\Policies\RefundCancellationPolicyService;
use App\Services\Policies\PolicyConfigurationService;
use App\Services\Policy\PolicyRuleSyncService;
use App\Support\PolicyUiText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPolicyController extends Controller
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PolicyConflictService $conflicts,
        private readonly RefundCancellationPolicyService $refundPolicies,
        private readonly ModerationReportPolicyService $reportPolicies,
        private readonly PolicyConfigurationService $configurationPolicies
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $policies = SystemPolicy::query()
            ->withCount(['actionBindings', 'rules'])
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . $request->query('keyword') . '%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('key', 'like', $keyword)
                        ->orWhere('title', 'like', $keyword)
                        ->orWhere('content', 'like', $keyword);
                });
            })
            ->when($request->filled('policy_type'), fn ($query) => $query->where('policy_type', $request->query('policy_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_overridable'), fn ($query) => $query->where('is_overridable', $request->boolean('is_overridable')))
            ->when($request->has('require_reaccept'), fn ($query) => $query->where('require_reaccept', $request->boolean('require_reaccept')))
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => $policies->map(fn (SystemPolicy $policy): array => $this->policyPayload($policy))->values(),
            'summary' => [
                'total' => $policies->count(),
                'active' => $policies->where('status', 'active')->count(),
                'draft' => $policies->where('status', 'draft')->count(),
                'archived' => $policies->where('status', 'archived')->count(),
                'overridable' => $policies->where('is_overridable', true)->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizePermission(request(), 'policy.view');

        $policy = SystemPolicy::query()
            ->with([
                'createdBy:id,username,full_name',
                'updatedBy:id,username,full_name',
                'publishedBy:id,username,full_name',
                'actionBindings' => fn ($query) => $query->orderBy('module')->orderBy('action_code'),
                'rules' => fn ($query) => $query->orderByDesc('priority')->orderBy('rule_code'),
                'statusHistories' => fn ($query) => $query->with('changedBy:id,username,full_name,email')->latest('created_at')->limit(30),
            ])
            ->findOrFail($id);

        $ruleIds = $policy->rules->pluck('id')->all();
        $venueRules = Schema::hasTable('venue_policy_rules')
            ? VenuePolicyRule::query()
                ->with(['venueCluster:id,name', 'baseRule:id,rule_code,rule_name'])
                ->whereIn('base_policy_rule_id', $ruleIds)
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        $bindingIds = $policy->actionBindings->pluck('id')->all();

        $evaluationLogs = Schema::hasTable('policy_evaluation_logs')
            ? PolicyEvaluationLog::query()
                ->with(['rule:id,rule_code,rule_name,rule_type,decision_key', 'venueRule:id,rule_code,rule_name,rule_type'])
                ->where('system_policy_id', $policy->id)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $auditLogs = Schema::hasTable('audit_logs')
            ? AuditLog::query()
                ->where(function ($query) use ($policy, $ruleIds, $bindingIds): void {
                    $query->where(function ($inner) use ($policy): void {
                        $inner->where('entity_type', 'system_policies')->where('entity_id', $policy->id);
                    });

                    if ($ruleIds) {
                        $query->orWhere(function ($inner) use ($ruleIds): void {
                            $inner->where('entity_type', 'policy_rules')->whereIn('entity_id', $ruleIds);
                        });
                    }

                    if ($bindingIds) {
                        $query->orWhere(function ($inner) use ($bindingIds): void {
                            $inner->where('entity_type', 'policy_action_bindings')->whereIn('entity_id', $bindingIds);
                        });
                    }
                })
                ->with('actor:id,username,full_name,email')
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $policyPayload = $this->policyPayload($policy);
        $rulesPayload = $policy->rules
            ->map(fn (PolicyRule $rule): array => $this->rulePayload($rule))
            ->values();
        $statusHistories = $policy->statusHistories
            ->map(fn (PolicyStatusHistory $history): array => $this->statusHistoryPayload($history))
            ->values();
        $cancelRefundConfiguration = $this->cancelRefundConfigurationPayload($policy);
        $reportConfiguration = $this->reportConfigurationPayload($policy);
        $permissionRevokeConfiguration = $this->configurationPolicies->permissionRevokePayload($policy);
        $accountPolicyConfiguration = $this->configurationPolicies->accountPolicyPayload($policy);
        $partnerContractConfiguration = $this->configurationPolicies->partnerContractPayload($policy);
        
        $businessSummary = $cancelRefundConfiguration['summary'] ?? $reportConfiguration['summary'] ?? $permissionRevokeConfiguration['summary'] ?? $accountPolicyConfiguration['summary'] ?? $partnerContractConfiguration['summary'] ?? $this->policyBusinessSummary($policy);

        return response()->json([
            'data' => [
                'policy_info' => $policyPayload,
                'content' => [
                    'title' => $policy->title,
                    'content' => $policy->content,
                    'version' => (int) $policy->version,
                    'require_reaccept' => (bool) $policy->require_reaccept,
                    'change_summary' => $policy->change_summary,
                ],
                'configuration_type' => $this->configurationPolicies->getConfigurationType($policy),
                'cancel_refund_tiers' => $cancelRefundConfiguration,
                'moderation_thresholds' => $reportConfiguration,
                'permission_revoke_configuration' => $permissionRevokeConfiguration,
                'account_policy_configuration' => $accountPolicyConfiguration,
                'partner_contract_configuration' => $partnerContractConfiguration,
                'venue_overrides' => $venueRules
                    ->map(fn (VenuePolicyRule $venueRule): array => $this->venueOverridePayload($venueRule))
                    ->values(),
                'status_histories' => $statusHistories,
                'business_summary' => $businessSummary,
                'preview_text' => $businessSummary,
                'policy' => $policyPayload,
                'action_bindings' => $policy->actionBindings
                    ->map(fn (PolicyActionBinding $binding): array => $this->bindingPayload($binding))
                    ->values(),
                'rules' => $rulesPayload,
                'cancellation_configuration' => $this->cancellationConfigurationPayload($policy),
                'refund_configuration' => $this->refundConfigurationPayload($policy),
                'report_configuration' => $reportConfiguration,
                'venue_rules' => $venueRules,
                'evaluation_logs' => $evaluationLogs
                    ->map(fn (PolicyEvaluationLog $log): array => $this->evaluationPayload($log, $policy))
                    ->values(),
                'audit_logs' => $auditLogs
                    ->map(fn (AuditLog $log): array => $this->auditPayload($log))
                    ->values(),
                'status_history_logs' => $statusHistories,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.create');

        $data = $this->policyData($request);

        $policy = SystemPolicy::query()->create([
            ...$data,
            'type' => $this->legacyType($data['policy_type']),
            'status' => 'draft',
            'is_active' => false,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.created', 'system_policies', $policy->id, [], $policy->toArray(), [
            'policy_id' => $policy->id,
        ]);

        return response()->json([
            'message' => 'Đã tạo bản nháp chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.update');

        $policy = SystemPolicy::query()->findOrFail($id);
        $data = $this->policyData($request, $policy);
        $oldValues = $policy->toArray();

        if ($policy->status === 'active' && $this->changesProtectedFields($policy, $data)) {
            throw ValidationException::withMessages([
                'policy' => 'Chính sách đang áp dụng không được sửa trực tiếp nội dung quan trọng. Hãy tạo phiên bản mới.',
            ]);
        }

        $policy->update([
            ...$data,
            'type' => $this->legacyType($data['policy_type']),
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.updated', 'system_policies', $policy->id, $oldValues, $policy->fresh()->toArray(), [
            'policy_id' => $policy->id,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật thông tin chung chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
        ]);
    }

    public function updateConfiguration(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.update');

        $policy = SystemPolicy::query()->with(['rules', 'actionBindings'])->findOrFail($id);

        if ($policy->status === 'active') {
            throw ValidationException::withMessages([
                'policy' => 'Chính sách đang áp dụng không được sửa cấu hình. Hãy tạo phiên bản nháp mới.',
            ]);
        }

        $configService = app(\App\Services\Policies\PolicyConfigurationService::class);
        if ($request->has('score_thresholds') || $request->has('auto_hide_score')) {
            return $this->updateModerationThresholds($request, $policy->id);
        }

        $data = $request->validate([
            'configuration_data' => ['required', 'array'],
        ]);

        try {
            DB::transaction(function() use ($policy, $data, $configService) {
                $configService->applyConfigurationData($policy, $data['configuration_data']);
            });
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'configuration_data' => $e->getMessage()
            ]);
        }

        $this->audit->log($request, 'policy', 'policy.configured', 'system_policies', $policy->id, [], $policy->fresh()->toArray(), [
            'policy_id' => $policy->id,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật cấu hình nghiệp vụ chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.update');

        $policy = SystemPolicy::query()->with(['rules', 'actionBindings'])->findOrFail($id);

        if ($policy->status !== 'draft') {
            throw ValidationException::withMessages([
                'policy' => 'Chỉ có thể xóa chính sách ở trạng thái bản nháp.',
            ]);
        }

        DB::transaction(function () use ($request, $policy): void {
            $oldValues = $policy->toArray();

            $policy->update([
                'status' => 'archived',
                'is_active' => false,
                'effective_to' => now(),
                'updated_by' => $request->user()->id,
            ]);

            $this->audit->log($request, 'policy', 'policy.deleted', 'system_policies', $policy->id, $oldValues, $policy->fresh()->toArray(), [
                'policy_id' => $policy->id,
                'policy_key' => $policy->key,
                'policy_title' => $policy->title,
            ]);
            $this->recordPolicyStatusHistory($policy->fresh(), 'draft', 'archived', $request, 'Xóa bản nháp chính sách.');

        });

        return response()->json([
            'message' => 'Đã xóa bản nháp chính sách.',
        ]);
    }

    public function cloneVersion(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.create');

        $source = SystemPolicy::query()->with(['actionBindings', 'rules', 'moderationThresholds'])->findOrFail($id);
        $nextVersion = (int) SystemPolicy::query()->where('key', $source->key)->max('version') + 1;

        $newPolicy = DB::transaction(function () use ($request, $source, $nextVersion): SystemPolicy {
            $policy = SystemPolicy::query()->create([
                'key' => $source->key,
                'version' => $nextVersion,
                'title' => $source->title,
                'content' => $source->content,
                'type' => $source->type,
                'policy_type' => $source->policy_type,
                'status' => 'draft',
                'is_active' => false,
                'is_overridable' => $source->is_overridable,
                'priority' => $source->priority,
                'effective_from' => now(),
                'replaced_policy_id' => $source->id,
                'require_reaccept' => $source->require_reaccept,
                'change_summary' => 'Tạo từ phiên bản ' . $source->version,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($source->actionBindings as $binding) {
                $policy->actionBindings()->create($binding->only(['module', 'action_code', 'description', 'is_active']));
            }

            foreach ($source->rules as $rule) {
                $policy->rules()->create([
                    ...$rule->only([
                        'action_code',
                        'rule_code',
                        'rule_name',
                        'rule_type',
                        'decision_key',
                        'conflict_group',
                        'condition_json',
                        'result_json',
                        'constraint_json',
                        'allowed_override_json',
                        'priority',
                        'is_active',
                    ]),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            foreach ($source->moderationThresholds as $threshold) {
                $policy->moderationThresholds()->create($threshold->only([
                    'target_type',
                    'warning_threshold',
                    'action_threshold',
                    'unique_reporters_threshold',
                    'timeframe_days',
                ]));
            }

            return $policy;
        });

        $this->audit->log($request, 'policy', 'policy.cloned', 'system_policies', $newPolicy->id, $source->toArray(), $newPolicy->toArray(), [
            'policy_id' => $newPolicy->id,
        ]);

        return response()->json([
            'message' => 'Đã tạo phiên bản chính sách mới.',
            'data' => $this->policyPayload($newPolicy),
        ], 201);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.publish');

        $policy = SystemPolicy::query()->with(['actionBindings', 'rules'])->findOrFail($id);
        $this->assertPolicyConfigurationCompatible($policy);
        $errors = $this->conflicts->validateBeforePublish($policy);

        if ($errors) {
            throw ValidationException::withMessages(['policy' => $errors]);
        }

        $oldValues = $policy->toArray();

        DB::transaction(function () use ($request, $policy): void {
            $archivedPolicies = SystemPolicy::query()
                ->where('key', $policy->key)
                ->where('id', '!=', $policy->id)
                ->where('status', 'active')
                ->get();

            foreach ($archivedPolicies as $archivedPolicy) {
                $oldStatus = $archivedPolicy->status;
                $archivedPolicy->update([
                    'status' => 'archived',
                    'is_active' => false,
                    'effective_to' => now(),
                    'updated_by' => $request->user()->id,
                ]);
                $this->recordPolicyStatusHistory($archivedPolicy->fresh(), $oldStatus, 'archived', $request, 'Tự động lưu trữ khi áp dụng phiên bản mới.');
            }

            if ($policy->replaced_policy_id) {
                $replacedPolicy = SystemPolicy::query()->find($policy->replaced_policy_id);
                if ($replacedPolicy && $replacedPolicy->status !== 'archived') {
                    $oldStatus = $replacedPolicy->status;
                    $replacedPolicy->update([
                        'status' => 'archived',
                        'is_active' => false,
                        'effective_to' => now(),
                        'updated_by' => $request->user()->id,
                    ]);
                    $this->recordPolicyStatusHistory($replacedPolicy->fresh(), $oldStatus, 'archived', $request, 'Tự động lưu trữ khi phiên bản thay thế được áp dụng.');
                }
            }

            $oldStatus = $policy->status;
            $policy->update([
                'status' => 'active',
                'is_active' => true,
                'effective_from' => $policy->effective_from ?: now(),
                'published_at' => now(),
                'published_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->recordPolicyStatusHistory($policy->fresh(), $oldStatus, 'active', $request, 'Áp dụng chính sách.');
        });

        $policy = $policy->fresh();

        $this->createPolicyNotifications($policy);
        $this->audit->log($request, 'policy', 'policy.published', 'system_policies', $policy->id, $oldValues, $policy->toArray(), [
            'policy_id' => $policy->id,
            'severity' => $policy->require_reaccept ? 'warning' : 'info',
        ]);

        return response()->json([
            'message' => 'Đã kích hoạt chính sách.',
            'data' => $this->policyPayload($policy),
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.publish');

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'inactive', 'archived'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $policy = SystemPolicy::query()->findOrFail($id);
        $oldValues = $policy->toArray();
        $oldStatus = $policy->status;

        $policy->update([
            'status' => $data['status'],
            'is_active' => false,
            'effective_to' => in_array($data['status'], ['inactive', 'archived'], true) ? now() : $policy->effective_to,
            'updated_by' => $request->user()->id,
        ]);
        $this->recordPolicyStatusHistory($policy->fresh(), $oldStatus, $data['status'], $request, $data['reason'] ?? null);

        $this->audit->log($request, 'policy', 'policy.status_changed', 'system_policies', $policy->id, $oldValues, $policy->fresh()->toArray(), [
            'policy_id' => $policy->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật trạng thái chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
        ]);
    }

    public function updateCancelRefundTiers(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->with(['rules', 'actionBindings'])->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'booking_cancellation') {
            throw ValidationException::withMessages([
                'policy' => 'Bảng mốc hủy & hoàn chỉ áp dụng cho chính sách Hủy booking.',
            ]);
        }

        $data = $request->validate([
            'tiers' => ['required', 'array', 'min:2'],
            'tiers.*.label' => ['nullable', 'string', 'max:120'],
            'tiers.*.from_hours' => ['required', 'numeric', 'min:0'],
            'tiers.*.to_hours' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.allow_cancel' => ['required', 'boolean'],
            'tiers.*.refund_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tiers.*.require_owner_confirm' => ['nullable', 'boolean'],
            'tiers.*.require_admin_confirm' => ['nullable', 'boolean'],
            'tiers.*.customer_message' => ['nullable', 'string', 'max:500'],
        ]);

        $tiers = $this->refundPolicies->validateSystemCancelRefundTiers($data['tiers']);

        $rule = DB::transaction(function () use ($request, $policy, $tiers): PolicyRule {
            $policy->actionBindings()->updateOrCreate(
                ['action_code' => 'booking.cancel_by_customer'],
                [
                    'module' => 'booking',
                    'description' => 'Khách hủy booking theo bảng mốc hủy & hoàn.',
                    'is_active' => true,
                ]
            );

            $oldRule = PolicyRule::query()
                ->where('system_policy_id', $policy->id)
                ->where('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE)
                ->orderByDesc('priority')
                ->first();

            $payload = [
                'action_code' => 'booking.cancel_by_customer',
                'rule_code' => $oldRule?->rule_code ?: 'cancel_refund_tiers',
                'rule_name' => 'Bảng mốc hủy & hoàn booking',
                'rule_type' => RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
                'decision_key' => 'cancel_refund_decision',
                'conflict_group' => 'booking_cancel_refund_window',
                'condition_json' => ['uses_cancel_refund_tier_table' => true],
                'result_json' => $this->refundPolicies->cancelRefundResultJson($tiers, [
                    'refund_basis' => 'paid_amount',
                ]),
                'constraint_json' => ['covers_from_hours' => 0, 'covers_to_infinity' => true],
                'allowed_override_json' => [
                    'venue_can_improve_refund_percent' => true,
                    'venue_can_change_time_ranges' => false,
                    'venue_can_block_system_allowed_cancel' => false,
                ],
                'priority' => 100,
                'is_active' => true,
                'updated_by' => $request->user()->id,
            ];

            if ($oldRule) {
                $oldValues = $oldRule->toArray();
                $oldRule->update($payload);
                $this->audit->log($request, 'policy', 'policy.cancel_refund_tiers_saved', 'policy_rules', $oldRule->id, $oldValues, $oldRule->fresh()->toArray(), [
                    'policy_id' => $policy->id,
                    'policy_rule_id' => $oldRule->id,
                ]);

                return $oldRule->fresh();
            }

            $rule = $policy->rules()->create([
                ...$payload,
                'created_by' => $request->user()->id,
            ]);

            $this->audit->log($request, 'policy', 'policy.cancel_refund_tiers_saved', 'policy_rules', $rule->id, [], $rule->toArray(), [
                'policy_id' => $policy->id,
                'policy_rule_id' => $rule->id,
            ]);

            return $rule;
        });

        $policy->load(['rules', 'actionBindings']);

        return response()->json([
            'message' => 'Đã lưu bảng mốc hủy & hoàn booking.',
            'data' => [
                'rule' => $this->rulePayload($rule),
                'cancel_refund_tiers' => $this->cancelRefundConfigurationPayload($policy),
            ],
        ]);
    }

    public function updateModerationThresholds(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->with(['rules', 'actionBindings'])->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'moderation') {
            throw ValidationException::withMessages([
                'policy' => 'Ngưỡng báo cáo chỉ áp dụng cho chính sách Báo cáo & kiểm duyệt.',
            ]);
        }

        $payload = $request->has('score_thresholds')
            ? $request->validate([
                'score_thresholds' => ['required', 'array', 'min:1', 'max:20'],
                'score_thresholds.*.target_type' => ['required', Rule::in(['community_post', 'venue_post', 'comment', 'user', 'venue_cluster'])],
                'score_thresholds.*.warning_threshold' => ['required', 'integer', 'min:1'],
                'score_thresholds.*.action_threshold' => ['required', 'integer', 'min:1'],
                'score_thresholds.*.unique_reporters_threshold' => ['required', 'integer', 'min:1'],
                'score_thresholds.*.timeframe_days' => ['required', 'integer', 'min:1', 'max:365'],
            ])['score_thresholds']
            : [$request->validate([
                'target_type' => ['required', Rule::in(['community_post', 'venue_post', 'comment', 'user', 'venue_cluster'])],
                'warning_threshold' => ['required', 'integer', 'min:1'],
                'action_threshold' => ['required', 'integer', 'min:1'],
                'unique_reporters_threshold' => ['required', 'integer', 'min:1'],
                'timeframe_days' => ['required', 'integer', 'min:1', 'max:365'],
            ])];

        foreach ($payload as $index => $row) {
            if ((int) $row['action_threshold'] <= (int) $row['warning_threshold']) {
                throw ValidationException::withMessages([
                    "score_thresholds.{$index}.action_threshold" => 'Ngưỡng thực hiện thao tác phải lớn hơn ngưỡng cảnh báo.',
                ]);
            }
            if ((int) $row['unique_reporters_threshold'] > (int) $row['warning_threshold']) {
                throw ValidationException::withMessages([
                    "score_thresholds.{$index}.unique_reporters_threshold" => 'Ngưỡng số người báo cáo không được lớn hơn ngưỡng cảnh báo.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $policy, $payload): void {
            foreach ($payload as $row) {
                ModerationThreshold::query()->updateOrCreate(
                    [
                        'system_policy_id' => $policy->id,
                        'target_type' => $row['target_type'],
                    ],
                    [
                        'warning_threshold' => (int) $row['warning_threshold'],
                        'action_threshold' => (int) $row['action_threshold'],
                        'unique_reporters_threshold' => (int) $row['unique_reporters_threshold'],
                        'timeframe_days' => (int) $row['timeframe_days'],
                    ]
                );
            }

            if (class_exists(\App\Services\Policy\PolicyRuleSyncService::class)) {
                app(\App\Services\Policy\PolicyRuleSyncService::class)->syncFromThresholds($policy->fresh());
            }

            $this->audit->log($request, 'policy', 'policy.score_thresholds_saved', 'system_policies', $policy->id, [], [
                'score_thresholds' => $payload,
            ], ['policy_id' => $policy->id]);
        });

        return response()->json([
            'message' => 'Đã lưu cấu hình ngưỡng báo cáo.',
            'data' => [
                'score_thresholds' => $policy->fresh('moderationThresholds')->moderationThresholds->values(),
            ],
        ]);
    }

    public function scoreModerationThresholds(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $policy = SystemPolicy::query()->with('moderationThresholds')->findOrFail($id);

        return response()->json([
            'data' => $policy->moderationThresholds
                ->sortBy('target_type')
                ->values()
                ->map(fn (ModerationThreshold $threshold): array => [
                    'id' => $threshold->id,
                    'target_type' => $threshold->target_type,
                    'target_type_label' => $this->moderationTargetLabel($threshold->target_type),
                    'warning_threshold' => (int) $threshold->warning_threshold,
                    'action_threshold' => (int) $threshold->action_threshold,
                    'unique_reporters_threshold' => (int) $threshold->unique_reporters_threshold,
                    'timeframe_days' => (int) $threshold->timeframe_days,
                    'summary' => sprintf(
                        '%s: cảnh báo khi đạt %d báo cáo, xử lý (ẩn/khóa) khi đạt %d báo cáo (từ ít nhất %d người khác nhau) trong %d ngày.',
                        $this->moderationTargetLabel($threshold->target_type),
                        (int) $threshold->warning_threshold,
                        (int) $threshold->action_threshold,
                        (int) $threshold->unique_reporters_threshold,
                        (int) $threshold->timeframe_days
                    ),
                ]),
        ]);
    }


    public function storeBinding(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $data = $request->validate([
            'module' => ['required', 'string', 'max:50'],
            'action_code' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $this->ensureActionCompatible($policy, $data['action_code']);

        $binding = $policy->actionBindings()->updateOrCreate(
            ['action_code' => $data['action_code']],
            [
                ...$data,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        $this->audit->log($request, 'policy', 'policy.binding_saved', 'policy_action_bindings', $binding->id, [], $binding->toArray(), [
            'policy_id' => $policy->id,
        ]);

        return response()->json([
            'message' => 'Đã lưu thao tác áp dụng chính sách.',
            'data' => $this->bindingPayload($binding),
        ], 201);
    }

    public function destroyBinding(Request $request, string $id, string $bindingId): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $binding = PolicyActionBinding::query()
            ->where('system_policy_id', $policy->id)
            ->findOrFail($bindingId);
        $oldValues = $binding->toArray();

        $binding->update(['is_active' => false]);

        $this->audit->log($request, 'policy', 'policy.binding_disabled', 'policy_action_bindings', $binding->id, $oldValues, $binding->fresh()->toArray(), [
            'policy_id' => $policy->id,
        ]);

        return response()->json(['message' => 'Đã tắt thao tác áp dụng chính sách.']);
    }

    public function showRule(Request $request, string $id, string $ruleId): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $policy = SystemPolicy::query()->findOrFail($id);
        $rule = PolicyRule::query()
            ->with('policy:id,policy_type,type,title')
            ->where('system_policy_id', $policy->id)
            ->findOrFail($ruleId);

        return response()->json([
            'data' => $this->rulePayload($rule),
        ]);
    }

    public function storeRule(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $data = $this->ruleData($request, $policy);

        $rule = $policy->rules()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.rule_created', 'policy_rules', $rule->id, [], $rule->toArray(), [
            'policy_id' => $policy->id,
            'policy_rule_id' => $rule->id,
        ]);

        return response()->json([
            'message' => 'Đã tạo quy tắc xử lý tự động.',
            'data' => $this->rulePayload($rule),
        ], 201);
    }

    public function updateRule(Request $request, string $id, string $ruleId): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->findOrFail($id);
        $rule = PolicyRule::query()->where('system_policy_id', $policy->id)->findOrFail($ruleId);
        $this->ensurePolicyDraftForRuleChange($policy);

        $data = $this->ruleData($request, $policy, $rule);
        $oldValues = $rule->toArray();

        $rule->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.rule_updated', 'policy_rules', $rule->id, $oldValues, $rule->fresh()->toArray(), [
            'policy_id' => $policy->id,
            'policy_rule_id' => $rule->id,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật quy tắc xử lý tự động.',
            'data' => $this->rulePayload($rule->fresh()),
        ]);
    }

    public function toggleRule(Request $request, string $id, string $ruleId): JsonResponse
    {
        $this->authorizePermission($request, 'policy.rule.manage');

        $policy = SystemPolicy::query()->findOrFail($id);
        $this->ensurePolicyDraftForRuleChange($policy);

        $rule = PolicyRule::query()->where('system_policy_id', $policy->id)->findOrFail($ruleId);
        $oldValues = $rule->toArray();

        $rule->update([
            'is_active' => ! $rule->is_active,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.rule_toggled', 'policy_rules', $rule->id, $oldValues, $rule->fresh()->toArray(), [
            'policy_id' => $policy->id,
            'policy_rule_id' => $rule->id,
        ]);

        return response()->json([
            'message' => $rule->is_active ? 'Đã bật quy tắc.' : 'Đã tắt quy tắc.',
            'data' => $this->rulePayload($rule->fresh()),
        ]);
    }

    public function evaluationLogs(string $id): JsonResponse
    {
        $this->authorizePermission(request(), 'policy.view');

        if (! Schema::hasTable('policy_evaluation_logs')) {
            return response()->json(['data' => []]);
        }

        $policy = SystemPolicy::query()->find($id);
        $logs = PolicyEvaluationLog::query()
            ->with(['rule:id,rule_code,rule_name,rule_type,decision_key', 'venueRule:id,rule_code,rule_name,rule_type'])
            ->where('system_policy_id', $id)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $logs->map(fn (PolicyEvaluationLog $log): array => $this->evaluationPayload($log, $policy))->values(),
        ]);
    }

    public function actionCodes(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $policyType = $request->query('policy_type');
        $options = collect($this->actionOptions())
            ->filter(fn (array $item): bool => ! $policyType || in_array($policyType, $item['policy_types'], true))
            ->values()
            ->all();

        return response()->json(['data' => $options]);
    }

    public function ruleTemplates(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'policy.view');

        $policyType = $request->query('policy_type');
        $templates = collect($this->ruleTemplateOptions())
            ->filter(fn (array $template): bool => ! $policyType || in_array($policyType, $template['policy_types'], true))
            ->mapWithKeys(fn (array $template, string $key): array => [$key => $template])
            ->all();

        return response()->json(['data' => $templates]);
    }

    private function policyData(Request $request, ?SystemPolicy $policy = null): array
    {
        $key = $request->input('key', $policy?->key);

        return $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'version' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('system_policies', 'version')
                    ->where(fn ($query) => $query->where('key', $key))
                    ->ignore($policy?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'policy_type' => ['required', Rule::in(array_keys(PolicyUiText::policyTypeLabels()))],
            'is_overridable' => ['boolean'],
            'require_reaccept' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:9999'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_summary' => ['nullable', 'string', 'max:5000'],
        ], [
            'key.required' => 'Vui lòng nhập mã chính sách.',
            'title.required' => 'Vui lòng nhập tiêu đề chính sách.',
            'content.required' => 'Vui lòng nhập nội dung chính sách.',
            'version.unique' => 'Phiên bản này đã tồn tại với cùng mã chính sách.',
        ]);
    }

    private function ruleData(Request $request, SystemPolicy $policy, ?PolicyRule $rule = null): array
    {
        $data = $request->validate([
            'action_code' => ['required', 'string', 'max:100'],
            'rule_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('policy_rules', 'rule_code')
                    ->where(fn ($query) => $query->where('system_policy_id', $policy->id))
                    ->ignore($rule?->id),
            ],
            'rule_name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', 'string', 'max:50'],
            'decision_key' => ['nullable', 'string', 'max:100'],
            'conflict_group' => ['nullable', 'string', 'max:100'],
            'condition_json' => ['nullable', 'array'],
            'result_json' => ['nullable', 'array'],
            'constraint_json' => ['nullable', 'array'],
            'allowed_override_json' => ['nullable', 'array'],
            'priority' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], [
            'rule_code.unique' => 'Mã quy tắc đã tồn tại trong chính sách này.',
        ]);

        $this->ensureRuleCompatible($policy, $data['rule_type']);
        $this->ensureActionCompatible($policy, $data['action_code']);
        $this->ensureRuleActionPairCompatible($data['rule_type'], $data['action_code']);
        $this->validateRulePayload($data);

        if ($data['rule_type'] === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE && isset($data['result_json']['tiers'])) {
            $tiers = $this->refundPolicies->validateSystemCancellationTiers($data['result_json']['tiers']);
            $data['condition_json'] = ['uses_tier_table' => true];
            $data['result_json'] = [
                ...($data['result_json'] ?? []),
                'tiers' => $tiers,
                'summary_vi' => $this->refundPolicies->cancellationSummary($tiers),
            ];
        }

        if ($data['rule_type'] === RefundCancellationPolicyService::REFUND_RULE_TYPE && isset($data['result_json']['tiers'])) {
            $tiers = $this->refundPolicies->validateSystemTiers($data['result_json']['tiers']);
            $data['condition_json'] = ['uses_tier_table' => true];
            $data['result_json'] = [
                ...($data['result_json'] ?? []),
                'tiers' => $tiers,
                'refund_percent' => $tiers[0]['refund_percent'] ?? null,
                'requires_owner_confirm' => $data['result_json']['requires_owner_confirm'] ?? true,
                'requires_admin_confirm' => $data['result_json']['requires_admin_confirm'] ?? true,
                'summary_vi' => $this->refundPolicies->summary($tiers),
            ];
        }

        if ($data['rule_type'] === ModerationReportPolicyService::RULE_TYPE) {
            $config = $this->reportPolicies->validateConfig([
                'target_type' => $data['condition_json']['target_type'] ?? 'content',
                'minimum_reports' => $data['condition_json']['report_count']['gte'] ?? $data['condition_json']['report_count'] ?? 5,
                'minimum_unique_reporters' => $data['condition_json']['unique_reporters']['gte'] ?? $data['condition_json']['unique_reporters'] ?? 2,
                'window_days' => $data['condition_json']['window_days'] ?? 14,
                'actions' => $data['result_json']['actions'] ?? [$data['result_json']['action'] ?? 'pending_review'],
            ]);
            $data['condition_json'] = $this->reportPolicies->conditionJson($config);
            $data['result_json'] = $this->reportPolicies->resultJson($config);
        }

        $this->validateRulePayload($data);

        return $data;
    }

    private function policySummaryPayload(SystemPolicy $policy): array
    {
        $payload = $this->policyPayload($policy);
        unset($payload['content']);

        if ($payload['configuration_type'] === 'permission_revoke') {
            $configService = app(\App\Services\Policies\PolicyConfigurationService::class);
            $payload['supported_targets'] = $configService->getSupportedTargets();
            $payload['supported_reasons'] = $configService->getSupportedReasons();
            $payload['supported_permissions'] = $configService->getSupportedPermissions();
        }

        return $payload;
    }

    private function policyPayload(SystemPolicy $policy): array
    {
        $policy->loadMissing(['rules', 'actionBindings']);
        
        $configService = app(\App\Services\Policies\PolicyConfigurationService::class);

        $payload = [
            'id' => $policy->id,
            'key' => $policy->key,
            'version' => (int) $policy->version,
            'title' => $policy->title,
            'content' => $policy->content,
            'type' => $policy->type,
            'policy_type' => $policy->policy_type,
            'policy_type_label' => $this->policyTypeLabel($policy->policy_type ?: $policy->type),
            'policy_type_label_vi' => $this->policyTypeLabel($policy->policy_type ?: $policy->type),
            'configuration_type' => $configService->getConfigurationType($policy),
            'configuration_data' => $configService->extractConfigurationData($policy),
            'supported_actions' => $configService->getSupportedActions($policy),
            'status' => $policy->status,
            'status_label' => $this->statusLabel($policy->status),
            'status_label_vi' => $this->statusLabel($policy->status),
            'is_active' => (bool) $policy->is_active,
            'is_overridable' => (bool) $policy->is_overridable,
            'priority' => (int) $policy->priority,
            'require_reaccept' => (bool) $policy->require_reaccept,
            'effective_from' => $policy->effective_from,
            'effective_to' => $policy->effective_to,
            'published_at' => $policy->published_at,
            'business_summary' => $this->policyBusinessSummary($policy),
            'business_summary_vi' => $this->policyBusinessSummary($policy),
            'can_edit_content' => $policy->status !== 'active',
            'can_edit' => $policy->status !== 'active',
            'can_publish' => $policy->status === 'draft',
            'action_bindings_count' => (int) ($policy->action_bindings_count ?? ($policy->relationLoaded('actionBindings') ? $policy->actionBindings->count() : 0)),
            'rules_count' => (int) ($policy->rules_count ?? ($policy->relationLoaded('rules') ? $policy->rules->count() : 0)),
            'created_by' => $policy->created_by,
            'updated_by' => $policy->updated_by,
            'published_by' => $policy->published_by,
            'created_by_name' => $policy->relationLoaded('createdBy') ? ($policy->createdBy?->full_name ?: $policy->createdBy?->username) : null,
            'updated_by_name' => $policy->relationLoaded('updatedBy') ? ($policy->updatedBy?->full_name ?: $policy->updatedBy?->username) : null,
            'published_by_name' => $policy->relationLoaded('publishedBy') ? ($policy->publishedBy?->full_name ?: $policy->publishedBy?->username) : null,
            'created_at' => $policy->created_at,
            'updated_at' => $policy->updated_at,
            'status_histories' => $policy->relationLoaded('statusHistories') ? $policy->statusHistories->map(fn($h) => [
                'id' => $h->id,
                'old_status' => $h->old_status,
                'new_status' => $h->new_status,
                'reason' => $h->reason,
                'created_at' => $h->created_at,
                'actor_name' => $h->changedBy?->full_name ?: $h->changedBy?->username,
            ])->values() : [],
        ];

        if ($payload['configuration_type'] === 'permission_revoke') {
            $payload['supported_targets'] = $configService->getSupportedTargets();
            $payload['supported_reasons'] = $configService->getSupportedReasons();
            $payload['supported_permissions'] = $configService->getSupportedPermissions();
        }

        return $payload;
    }

    private function bindingPayload(PolicyActionBinding $binding): array
    {
        return [
            'id' => $binding->id,
            'system_policy_id' => $binding->system_policy_id,
            'module' => $binding->module,
            'module_label' => $this->moduleLabel($binding->module),
            'action_code' => $binding->action_code,
            'action_label' => $this->actionLabel($binding->action_code),
            'action_label_vi' => $this->actionLabel($binding->action_code),
            'description' => $binding->description,
            'is_active' => (bool) $binding->is_active,
            'created_at' => $binding->created_at,
            'updated_at' => $binding->updated_at,
        ];
    }

    private function configurationType(SystemPolicy $policy): string
    {
        return $this->configurationPolicies->getConfigurationType($policy);
    }

    private function cancelRefundConfigurationPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'booking_cancellation') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE);
        $tiers = $this->refundPolicies->cancelRefundTiersFromRule($rule);

        return [
            'is_supported' => true,
            'has_rule' => (bool) $rule,
            'rule_id' => $rule?->id,
            'rule_name' => $rule?->rule_name ?: 'Bảng mốc hủy & hoàn booking',
            'summary' => $this->refundPolicies->cancelRefundSummary($tiers),
            'preview_text' => $this->refundPolicies->cancelRefundSummary($tiers),
            'tiers' => $tiers,
            'system_tiers' => $tiers,
            'limits' => $this->refundPolicies->cancelRefundPayload($tiers)['limits'],
            'can_edit' => $policy->status !== 'active',
            'status_label' => $rule ? ($rule->is_active ? 'Đang bật' : 'Đang tắt') : 'Chưa cấu hình',
        ];
    }

    private function venueOverridePayload(VenuePolicyRule $venueRule): array
    {
        return [
            'id' => $venueRule->id,
            'venue_cluster_name' => $venueRule->venueCluster?->name,
            'rule_name' => $venueRule->rule_name,
            'base_rule_name' => $venueRule->baseRule?->rule_name,
            'status' => $venueRule->status,
            'status_label' => $this->statusLabel($venueRule->status),
            'summary' => $venueRule->result_json['summary_vi'] ?? $venueRule->rule_name,
            'created_at' => $venueRule->created_at,
            'updated_at' => $venueRule->updated_at,
        ];
    }

    private function statusHistoryPayload(PolicyStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'old_status' => $history->old_status,
            'old_status_label' => $this->statusLabel($history->old_status),
            'new_status' => $history->new_status,
            'new_status_label' => $this->statusLabel($history->new_status),
            'changed_by_name' => $history->changedBy?->full_name ?: $history->changedBy?->username ?: $history->changedBy?->email,
            'actor_type' => $history->actor_type,
            'reason' => $history->reason,
            'created_at' => $history->created_at,
        ];
    }

    private function cancellationConfigurationPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'booking_cancellation') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE);

        if (! $rule) {
            $tiers = $this->refundPolicies->defaultCancellationTiers();

            return [
                'is_supported' => true,
                'has_rule' => false,
                'summary' => 'Chưa có bảng mốc hủy booking cho chính sách này.',
                'tiers' => $this->refundPolicies->normalizeCancellationTiers($tiers),
                'can_edit' => $policy->status !== 'active',
            ];
        }

        $tiers = $this->refundPolicies->cancellationTiersFromRule($rule);

        return [
            'is_supported' => true,
            'has_rule' => true,
            'rule_id' => $rule->id,
            'rule_name' => $rule->rule_name,
            'status_label' => $rule->is_active ? 'Đang bật' : 'Đang tắt',
            'summary' => $this->refundPolicies->cancellationSummary($tiers),
            'tiers' => $tiers,
            'can_edit' => $policy->status !== 'active',
        ];
    }

    private function refundConfigurationPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'refund') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', RefundCancellationPolicyService::RULE_TYPE);

        if (! $rule) {
            return [
                'is_supported' => true,
                'has_rule' => false,
                'summary' => 'Chưa có bảng mốc hoàn tiền cho chính sách này.',
                'tiers' => $this->refundPolicies->normalizeTiers($this->refundPolicies->defaultTiers()),
                'can_edit' => $policy->status !== 'active',
                'requires_owner_confirm' => true,
                'requires_admin_confirm' => true,
            ];
        }

        $tiers = $this->refundPolicies->tiersFromRule($rule);

        return [
            'is_supported' => true,
            'has_rule' => true,
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->rule_name,
            'status_label' => $rule->is_active ? 'Đang bật' : 'Đang tắt',
            'summary' => $this->refundPolicies->summary($tiers),
            'tiers' => $tiers,
            'can_edit' => $policy->status !== 'active',
            'requires_owner_confirm' => (bool) ($rule->result_json['requires_owner_confirm'] ?? true),
            'requires_admin_confirm' => (bool) ($rule->result_json['requires_admin_confirm'] ?? true),
        ];
    }

    private function reportConfigurationPayload(SystemPolicy $policy): ?array
    {
        $policyType = $policy->policy_type ?: $policy->type;
        if ($policyType !== 'moderation') {
            return null;
        }

        $rules = $policy->relationLoaded('rules') ? $policy->rules : $policy->rules()->get();
        $rule = $rules->firstWhere('rule_type', ModerationReportPolicyService::RULE_TYPE);

        return $this->reportPolicies->payload($rule, $policy->status !== 'active');
    }

    private function rulePayload(PolicyRule $rule): array
    {
        $isCancelRefundRule = $rule->rule_type === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE
            && (isset($rule->result_json['cancel_refund_tiers']) || ($rule->result_json['refund_basis'] ?? null) === 'paid_amount');
        $cancellationTiers = $rule->rule_type === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE
            ? ($isCancelRefundRule ? $this->refundPolicies->cancelRefundTiersFromRule($rule) : $this->refundPolicies->cancellationTiersFromRule($rule))
            : null;
        $refundTiers = $rule->rule_type === RefundCancellationPolicyService::RULE_TYPE
            ? $this->refundPolicies->tiersFromRule($rule)
            : null;
        $reportConfig = $rule->rule_type === ModerationReportPolicyService::RULE_TYPE
            ? $this->reportPolicies->payload($rule, true)
            : null;
        $businessSummary = match (true) {
            (bool) $cancellationTiers => $isCancelRefundRule ? $this->refundPolicies->cancelRefundSummary($cancellationTiers) : $this->refundPolicies->cancellationSummary($cancellationTiers),
            (bool) $refundTiers => $this->refundPolicies->summary($refundTiers),
            (bool) $reportConfig => $reportConfig['summary'],
            default => $this->ruleBusinessSummary($rule),
        };

        return [
            'id' => $rule->id,
            'system_policy_id' => $rule->system_policy_id,
            'action_code' => $rule->action_code,
            'action_label' => $this->actionLabel($rule->action_code),
            'action_label_vi' => $this->actionLabel($rule->action_code),
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->rule_name,
            'rule_label_vi' => $this->ruleTypeLabel($rule->rule_type),
            'rule_type' => $rule->rule_type,
            'rule_type_label' => $this->ruleTypeLabel($rule->rule_type),
            'policy_type' => $rule->relationLoaded('policy') ? ($rule->policy?->policy_type ?: $rule->policy?->type) : null,
            'policy_type_label' => $rule->relationLoaded('policy') ? $this->policyTypeLabel($rule->policy?->policy_type ?: $rule->policy?->type) : null,
            'decision_key' => $rule->decision_key,
            'conflict_group' => $rule->conflict_group,
            'condition_json' => $rule->condition_json,
            'result_json' => $rule->result_json,
            'condition_summary_vi' => $reportConfig['summary'] ?? PolicyUiText::conditionSummary($rule->rule_type, $rule->condition_json ?: []),
            'result_summary_vi' => $businessSummary,
            'constraint_json' => $rule->constraint_json,
            'allowed_override_json' => $rule->allowed_override_json,
            'business_summary' => $businessSummary,
            'business_summary_vi' => $businessSummary,
            'configuration_type' => match (true) {
                (bool) $cancellationTiers => $isCancelRefundRule ? 'cancel_refund_tier_table' : 'cancellation_tier_table',
                (bool) $refundTiers => 'refund_tier_table',
                (bool) $reportConfig => 'report_threshold',
                default => 'rule',
            },
            'cancellation_tiers' => $cancellationTiers,
            'cancel_refund_tiers' => $isCancelRefundRule ? $cancellationTiers : null,
            'cancel_refund_tier_summary' => ($isCancelRefundRule && $cancellationTiers) ? $this->refundPolicies->cancelRefundSummary($cancellationTiers) : null,
            'cancellation_tier_summary' => $cancellationTiers ? ($isCancelRefundRule ? $this->refundPolicies->cancelRefundSummary($cancellationTiers) : $this->refundPolicies->cancellationSummary($cancellationTiers)) : null,
            'refund_tiers' => $refundTiers,
            'refund_tier_summary' => $refundTiers ? $this->refundPolicies->summary($refundTiers) : null,
            'report_configuration' => $reportConfig,
            'technical_detail' => [
                'action_code' => $rule->action_code,
                'rule_type' => $rule->rule_type,
                'decision_key' => $rule->decision_key,
                'conflict_group' => $rule->conflict_group,
                'condition_json' => $rule->condition_json,
                'result_json' => $rule->result_json,
                'constraint_json' => $rule->constraint_json,
                'allowed_override_json' => $rule->allowed_override_json,
            ],
            'priority' => (int) $rule->priority,
            'is_active' => (bool) $rule->is_active,
            'created_by' => $rule->created_by,
            'updated_by' => $rule->updated_by,
            'created_at' => $rule->created_at,
            'updated_at' => $rule->updated_at,
        ];
    }

    private function evaluationPayload(PolicyEvaluationLog $log, ?SystemPolicy $policy = null): array
    {
        $rule = $log->relationLoaded('rule') ? $log->rule : null;
        $venueRule = $log->relationLoaded('venueRule') ? $log->venueRule : null;

        return [
            ...$log->toArray(),
            'action_label' => $this->actionLabel($log->action_code),
            'policy_title' => $policy?->title,
            'rule_label' => $rule?->rule_name ?: $venueRule?->rule_name,
            'rule_type_label' => $this->ruleTypeLabel($rule?->rule_type ?: $venueRule?->rule_type),
            'human_result' => $this->evaluationHumanResult($log),
            'human_message' => $this->actionLabel($log->action_code) . ' đã được kiểm tra theo chính sách.',
        ];
    }

    private function auditPayload(AuditLog $log): array
    {
        $actor = $log->relationLoaded('actor') ? $log->actor : null;

        return [
            ...$log->toArray(),
            'actor_name' => $actor?->full_name ?: $actor?->username ?: $actor?->email,
            'human_message' => $this->auditHumanMessage($log),
            'changes_summary' => $this->changesSummarySafe($log->old_values ?? [], $log->new_values ?? []),
            'technical_old_values' => $log->old_values ?? null,
            'technical_new_values' => $log->new_values ?? null,
        ];
    }

    private function legacyType(string $policyType): string
    {
        return match ($policyType) {
            'refund' => 'refund',
            'booking', 'booking_cancellation' => 'booking',
            'moderation', 'account' => 'moderation',
            default => 'general',
        };
    }

    private function changesProtectedFields(SystemPolicy $policy, array $data): bool
    {
        foreach (['key', 'version', 'title', 'content', 'policy_type', 'is_overridable', 'require_reaccept', 'effective_from', 'effective_to'] as $field) {
            if (array_key_exists($field, $data) && $policy->{$field} != $data[$field]) {
                return true;
            }
        }

        return false;
    }

    private function ensureActionCompatible(SystemPolicy $policy, string $actionCode): void
    {
        $policyType = $policy->policy_type ?: $policy->type ?: 'general';
        $allowed = $this->allowedActionCodes($policyType);

        if (! in_array($actionCode, $allowed, true)) {
            throw ValidationException::withMessages([
                'action_code' => 'Thao tác này không phù hợp với loại chính sách ' . $this->policyTypeLabel($policyType) . '.',
            ]);
        }
    }

    private function ensureRuleCompatible(SystemPolicy $policy, string $ruleType): void
    {
        $policyType = $policy->policy_type ?: $policy->type ?: 'general';
        $allowed = $this->allowedRuleTypes($policyType);

        if (! in_array($ruleType, $allowed, true)) {
            throw ValidationException::withMessages([
                'rule_type' => 'Loại quy tắc này không phù hợp với chính sách ' . $this->policyTypeLabel($policyType) . '.',
            ]);
        }
    }

    private function ensureRuleActionPairCompatible(string $ruleType, string $actionCode): void
    {
        $template = $this->ruleTemplateOptions()[$ruleType] ?? null;

        if (! $template) {
            throw ValidationException::withMessages([
                'rule_type' => 'Loại quy tắc này chưa được hệ thống hỗ trợ.',
            ]);
        }

        if (! in_array($actionCode, $template['action_codes'] ?? [], true)) {
            throw ValidationException::withMessages([
                'action_code' => 'Thao tác áp dụng không phù hợp với loại quy tắc đã chọn.',
            ]);
        }
    }

    private function validateRulePayload(array $data): void
    {
        $condition = $data['condition_json'] ?? [];
        $result = $data['result_json'] ?? [];
        $errors = [];

        $positive = function (mixed $value): bool {
            if (is_array($value)) {
                $value = $value['gte'] ?? $value['gt'] ?? $value['value'] ?? null;
            }

            return is_numeric($value) && (float) $value > 0;
        };

        $percent = function (mixed $value): bool {
            return is_numeric($value) && (float) $value >= 0 && (float) $value <= 100;
        };

        switch ($data['rule_type']) {
            case 'cancel_before_hours':
                if (isset($result['tiers']) && is_array($result['tiers'])) {
                    $this->refundPolicies->validateSystemCancellationTiers($result['tiers']);
                    break;
                }
                if (! $positive($condition['hours_before_start'] ?? null)) {
                    $errors['condition_json.hours_before_start'] = 'Số giờ trước giờ chơi phải lớn hơn 0.';
                }
                break;

            case 'refund_percent_by_cancel_time':
                if (isset($result['tiers']) && is_array($result['tiers'])) {
                    $this->refundPolicies->validateSystemTiers($result['tiers']);
                    break;
                }
                if (! $positive($condition['hours_before_start'] ?? null)) {
                    $errors['condition_json.hours_before_start'] = 'Số giờ trước giờ chơi phải lớn hơn 0.';
                }
                if (! $percent($result['refund_percent'] ?? null)) {
                    $errors['result_json.refund_percent'] = 'Phần trăm hoàn tiền phải nằm trong khoảng 0 đến 100.';
                }
                break;

            case 'owner_confirm_required_before_admin_transfer':
                if (($result['owner_confirm_required'] ?? null) !== true) {
                    $errors['result_json.owner_confirm_required'] = 'Rule hoàn tiền phải bắt buộc chủ sân xác nhận.';
                }
                if (($result['admin_can_complete_without_owner'] ?? null) !== false) {
                    $errors['result_json.admin_can_complete_without_owner'] = 'Admin không được hoàn tất nếu chủ sân chưa xác nhận.';
                }
                break;

            case 'platform_fee_overdue_warning':
                if (! $positive($condition['days_before_due'] ?? $condition['overdue_days'] ?? null)) {
                    $errors['condition_json.days_before_due'] = 'Số ngày nhắc phí phải lớn hơn 0.';
                }
                break;

            case 'platform_fee_overdue_lock':
                if (! $positive($condition['overdue_days'] ?? null)) {
                    $errors['condition_json.overdue_days'] = 'Số ngày quá hạn phải lớn hơn 0.';
                }
                break;

            case 'report_threshold_requires_review':
                $this->reportPolicies->validateConfig([
                    'target_type' => $condition['target_type'] ?? 'content',
                    'minimum_reports' => $condition['report_count']['gte'] ?? $condition['report_count'] ?? null,
                    'minimum_unique_reporters' => $condition['unique_reporters']['gte'] ?? $condition['unique_reporters'] ?? null,
                    'window_days' => $condition['window_days'] ?? null,
                    'actions' => $result['actions'] ?? [$result['action'] ?? null],
                ]);
                foreach (['report_count', 'unique_reporters'] as $field) {
                    if (! $positive($condition[$field] ?? null)) {
                        $errors['condition_json.' . $field] = 'Ngưỡng báo cáo phải lớn hơn 0.';
                    }
                }
                if (! $positive($condition['window_days'] ?? null)) {
                    $errors['condition_json.window_days'] = 'Số ngày theo dõi báo cáo phải lớn hơn 0.';
                }
                break;

            case 'contract_signing_required':
                if (($condition['owner_signed'] ?? null) !== true || ($condition['sportgo_signed'] ?? null) !== true) {
                    $errors['condition_json.signatures'] = 'Hợp đồng có hiệu lực phải yêu cầu đủ chữ ký chủ sân và SportGo.';
                }
                break;

            case 'partner_termination_transition_30_days':
                if (! $positive($result['transition_days'] ?? null)) {
                    $errors['result_json.transition_days'] = 'Số ngày chuyển tiếp phải lớn hơn 0.';
                }
                break;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensurePolicyDraftForRuleChange(SystemPolicy $policy): void
    {
        if ($policy->status !== 'active') {
            return;
        }

        throw ValidationException::withMessages([
            'policy' => 'Không được sửa quy tắc hoặc thao tác của chính sách đang áp dụng. Hãy tạo phiên bản mới.',
        ]);
    }

    private function allowedActionCodes(string $policyType): array
    {
        if ($policyType === 'general') {
            return collect($this->actionOptions())->pluck('action_code')->all();
        }

        return collect($this->actionOptions())
            ->filter(fn (array $item): bool => in_array($policyType, $item['policy_types'], true))
            ->pluck('action_code')
            ->all();
    }

    private function allowedRuleTypes(string $policyType): array
    {
        if ($policyType === 'general') {
            return array_keys($this->ruleTemplateOptions());
        }

        return collect($this->ruleTemplateOptions())
            ->filter(fn (array $item): bool => in_array($policyType, $item['policy_types'], true))
            ->keys()
            ->all();
    }

    private function validatePenaltyTargetType(string $targetType): void
    {
        if (! in_array($targetType, ['user', 'venue_cluster'], true)) {
            throw ValidationException::withMessages([
                'target_type' => 'Đối tượng leo thang xử lý vi phạm không hợp lệ.',
            ]);
        }
    }

    private function moderationTargetLabel(?string $targetType): string
    {
        return [
            'community_post' => 'Bài viết cộng đồng',
            'venue_post' => 'Bài viết sân',
            'comment' => 'Bình luận',
            'user' => 'Người dùng',
            'venue_cluster' => 'Sân / cụm sân',
        ][$targetType] ?? 'Đối tượng kiểm duyệt';
    }

    private function actionOptions(): array
    {
        return PolicyUiText::actionOptions();
    }

    private function ruleTemplateOptions(): array
    {
        return PolicyUiText::ruleTemplateOptions();
    }

    private function policyTypeLabel(?string $type): string
    {
        return PolicyUiText::policyTypeLabel($type);
    }

    private function statusLabel(?string $status): string
    {
        return PolicyUiText::statusLabel($status);
    }

    private function moduleLabel(?string $module): string
    {
        return PolicyUiText::moduleLabel($module);
    }

    private function actionLabel(?string $code): string
    {
        return PolicyUiText::actionLabel($code);
    }

    private function ruleTypeLabel(?string $type): string
    {
        if (! $type) {
            return 'Không xác định';
        }

        $template = $this->ruleTemplateOptions()[$type] ?? null;
        return $template['rule_type_label'] ?? $template['label'] ?? $type;
    }

    private function policyBusinessSummary(SystemPolicy $policy): string
    {
        return PolicyUiText::policyBusinessSummary($policy);
    }

    private function ruleBusinessSummary(PolicyRule $rule): string
    {
        return PolicyUiText::ruleBusinessSummary($rule);
    }

    private function ruleConditionValue(array $condition, string $field): string
    {
        $value = $condition[$field] ?? '?';
        if (is_array($value)) {
            $value = $value['gte'] ?? $value['lte'] ?? $value['eq'] ?? $value['value'] ?? reset($value);
        }
        return $this->safeScalar($value);
    }

    private function safeScalar(mixed $value): string
    {
        if ($value === null || $value === '') return '?';
        if (is_bool($value)) return $value ? 'Có' : 'Không';
        if (is_scalar($value)) return (string) $value;
        return 'Dữ liệu kỹ thuật';
    }

    private function formatSimpleAuditValue(mixed $value): string
    {
        if (is_null($value)) return 'trống';
        if (is_bool($value)) return $value ? 'Có' : 'Không';
        if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
        return (string) $value;
    }

    private function resultActionLabel(mixed $action): string
    {
        return PolicyUiText::resultActionLabel($action);
    }

    private function evaluationHumanResult(PolicyEvaluationLog $log): string
    {
        $result = $log->result_data ?: [];
        $rule = $log->relationLoaded('rule') ? $log->rule : null;
        $decisionKey = $rule?->decision_key
            ?: ($log->rule_snapshot['decision_key'] ?? null)
            ?: ($result['decision_key'] ?? null);

        if ($decisionKey === 'refund_percent') {
            return 'Kết quả xử lý: hoàn ' . $this->safeScalar($result['refund_percent'] ?? null) . '% tiền.';
        }
        if ($decisionKey === 'require_reaccept') {
            return ($result['require_reaccept'] ?? false)
                ? 'Kết quả xử lý: yêu cầu người dùng đồng ý chính sách.'
                : 'Kết quả xử lý: người dùng không cần đồng ý lại.';
        }
        if (isset($result['action'])) {
            return 'Kết quả xử lý: ' . $this->resultActionLabel($result['action']) . '.';
        }
        return 'Đã ghi nhận kết quả đánh giá chính sách.';
    }

    private function auditHumanMessage(AuditLog $log): string
    {
        return match ($log->action) {
            'policy.created' => 'Admin đã tạo bản nháp chính sách.',
            'policy.updated' => 'Admin đã cập nhật thông tin chính sách.',
            'policy.cloned' => 'Admin đã tạo phiên bản mới của chính sách.',
            'policy.published' => 'Admin đã kích hoạt chính sách.',
            'policy.status_changed' => 'Admin đã cập nhật trạng thái chính sách.',
            'policy.binding_saved' => 'Admin đã cấu hình thao tác áp dụng chính sách.',
            'policy.binding_disabled' => 'Admin đã tắt một thao tác áp dụng chính sách.',
            'policy.rule_created' => 'Admin đã thêm quy tắc xử lý tự động.',
            'policy.rule_updated' => 'Admin đã cập nhật quy tắc xử lý tự động.',
            'policy.rule_toggled' => 'Admin đã bật hoặc tắt quy tắc xử lý tự động.',
            default => 'Admin đã thực hiện thao tác trên chính sách.',
        };
    }

    private function changesSummarySafe(?array $oldValues, ?array $newValues): array
    {
        $oldValues ??= [];
        $newValues ??= [];
        $labels = [
            'title' => 'Tiêu đề',
            'content' => 'Nội dung',
            'policy_type' => 'Loại chính sách',
            'status' => 'Trạng thái',
            'version' => 'Phiên bản',
            'priority' => 'Thứ tự ưu tiên',
            'is_overridable' => 'Cho sân chỉnh riêng',
            'require_reaccept' => 'Bắt buộc đồng ý lại',
            'change_summary' => 'Tóm tắt thay đổi',
            'rules' => 'Quy tắc xử lý',
            'action_bindings' => 'Thao tác áp dụng',
            'condition_json' => 'Điều kiện quy tắc',
            'result_json' => 'Kết quả quy tắc',
            'metadata' => 'Dữ liệu kỹ thuật',
        ];
        $changes = [];
        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field) {
            if (! array_key_exists($field, $labels)) continue;
            $old = $oldValues[$field] ?? null;
            $new = $newValues[$field] ?? null;
            if (json_encode($old) === json_encode($new)) continue;
            $changes[] = [
                'field' => $field,
                'field_label' => $labels[$field],
                'old' => $this->formatAuditValue($field, $old),
                'new' => $this->formatAuditValue($field, $new),
                'summary' => $this->changeSummaryTextSafe($field, $labels[$field], $old, $new),
            ];
        }
        return $changes;
    }

    private function formatAuditValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '(trống)';
        if (is_bool($value)) return $value ? 'Có' : 'Không';
        if ($field === 'status' && is_scalar($value)) return $this->statusLabel((string) $value);
        if ($field === 'policy_type' && is_scalar($value)) return $this->policyTypeLabel((string) $value);
        if (! is_scalar($value) && $value !== null) {
            return match ($field) {
                'rules', 'condition_json', 'result_json' => 'Quy tắc xử lý đã thay đổi',
                'action_bindings' => 'Thao tác áp dụng đã thay đổi',
                default => 'Dữ liệu kỹ thuật đã thay đổi',
            };
        }
        $string = (string) $value;
        return mb_strlen($string) > 120 ? mb_substr($string, 0, 120) . '...' : $string;
    }

    private function changeSummaryTextSafe(string $field, string $label, mixed $old, mixed $new): string
    {
        if ($field === 'content') return 'Nội dung chính sách đã thay đổi';
        if (in_array($field, ['rules', 'condition_json', 'result_json'], true)) return 'Quy tắc xử lý đã thay đổi';
        if ($field === 'action_bindings') return 'Thao tác áp dụng đã thay đổi';
        if ((! is_scalar($old) && $old !== null) || (! is_scalar($new) && $new !== null)) return $label . ' đã thay đổi';
        return $label . ': ' . $this->formatAuditValue($field, $old) . ' → ' . $this->formatAuditValue($field, $new);
    }

    private function changesSummary(?array $oldValues, ?array $newValues): array
    {
        return $this->changesSummarySafe($oldValues, $newValues);
    }

    private function displayValue(string $field, mixed $value): string
    {
        return $this->formatAuditValue($field, $value);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizePermission(Request $request, string|array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        $roles = $user->roles()->pluck('roles.name')->all();

        if (array_intersect($roles, ['super_admin', 'admin'])) {
            return;
        }

        $permissionList = (array) $permissions;
        $hasPermission = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('permissions.code', $permissionList)
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }

    private function assertPolicyConfigurationCompatible(SystemPolicy $policy): void
    {
        $policyType = $policy->policy_type ?: $policy->type;
        $activeActionCodes = $policy->actionBindings
            ->where('is_active', true)
            ->pluck('action_code')
            ->all();

        foreach ($policy->actionBindings->where('is_active', true) as $binding) {
            $this->ensureActionCompatible($policy, $binding->action_code);
        }

        // Rule types that are auto-synced from system tables (ModerationThreshold, PenaltyEscalationRule)
        // and don't require manual action binding activation
        $autoSyncedRuleTypes = ['moderation_score_threshold', 'penalty_escalation'];

        foreach ($policy->rules->where('is_active', true) as $rule) {
            $this->ensureRuleCompatible($policy, $rule->rule_type);

            $isAutoSynced = in_array($rule->rule_type, $autoSyncedRuleTypes, true);

            if (! $isAutoSynced) {
                $this->ensureActionCompatible($policy, $rule->action_code);
                $this->ensureRuleActionPairCompatible($rule->rule_type, $rule->action_code);

                if (! in_array($rule->action_code, $activeActionCodes, true)) {
                    throw ValidationException::withMessages([
                        'action_bindings' => 'Quy tắc "' . $rule->rule_name . '" đang dùng thao tác chưa được bật trong chính sách.',
                    ]);
                }
            }

            if (! in_array($rule->rule_type, $this->allowedRuleTypes($policyType), true)) {
                throw ValidationException::withMessages([
                    'rule_type' => 'Loại quy tắc này không phù hợp với chính sách ' . $this->policyTypeLabel($policyType) . '.',
                ]);
            }
        }
    }

    private function recordPolicyStatusHistory(SystemPolicy $policy, ?string $oldStatus, string $newStatus, Request $request, ?string $reason = null): void
    {
        if (! Schema::hasTable('policy_status_histories')) {
            return;
        }

        $policy->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $request->user()?->id,
            'actor_type' => $this->adminActorType($request),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function adminActorType(Request $request): string
    {
        $roles = $request->user()?->roles()->pluck('roles.name')->all() ?? [];

        return in_array('super_admin', $roles, true) ? 'super_admin' : 'admin';
    }

    private function createPolicyNotifications(SystemPolicy $policy): void
    {
        if (! Schema::hasTable('notifications')) return;
        if (! $policy->require_reaccept) return;
        User::query()
            ->where('status', 'active')
            ->select('id')
            ->chunk(200, function ($users) use ($policy): void {
                foreach ($users as $user) {
                    Notification::query()->create([
                        'user_id' => $user->id,
                        'type' => 'policy_updated',
                        'title' => 'Chính sách SportGo đã được cập nhật',
                        'body' => 'Vui lòng đọc và xác nhận lại chính sách: ' . $policy->title,
                        'reference_type' => 'system_policies',
                        'reference_id' => $policy->id,
                        'data' => [
                            'policy_key' => $policy->key,
                            'version' => $policy->version,
                            'require_reaccept' => true,
                        ],
                    ]);
                }
            });
    }
}
