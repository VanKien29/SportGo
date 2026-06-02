<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PolicyActionBinding;
use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenuePolicyRule;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\PolicyConflictService;
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
        private readonly PolicyConflictService $conflicts
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

        return response()->json([
            'data' => [
                'policy' => $this->policyPayload($policy),
                'action_bindings' => $policy->actionBindings
                    ->map(fn (PolicyActionBinding $binding): array => $this->bindingPayload($binding))
                    ->values(),
                'rules' => $policy->rules
                    ->map(fn (PolicyRule $rule): array => $this->rulePayload($rule))
                    ->values(),
                'venue_rules' => $venueRules,
                'evaluation_logs' => $evaluationLogs
                    ->map(fn (PolicyEvaluationLog $log): array => $this->evaluationPayload($log, $policy))
                    ->values(),
                'audit_logs' => $auditLogs
                    ->map(fn (AuditLog $log): array => $this->auditPayload($log))
                    ->values(),
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
            'message' => 'Đã cập nhật chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
        ]);
    }

    public function cloneVersion(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, 'policy.create');

        $source = SystemPolicy::query()->with(['actionBindings', 'rules'])->findOrFail($id);
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
            SystemPolicy::query()
                ->where('key', $policy->key)
                ->where('id', '!=', $policy->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'archived',
                    'is_active' => false,
                    'effective_to' => now(),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);

            if ($policy->replaced_policy_id) {
                SystemPolicy::query()->where('id', $policy->replaced_policy_id)->update([
                    'status' => 'archived',
                    'is_active' => false,
                    'effective_to' => now(),
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);
            }

            $policy->update([
                'status' => 'active',
                'is_active' => true,
                'effective_from' => $policy->effective_from ?: now(),
                'published_at' => now(),
                'published_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
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

        $policy->update([
            'status' => $data['status'],
            'is_active' => false,
            'effective_to' => in_array($data['status'], ['inactive', 'archived'], true) ? now() : $policy->effective_to,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'policy', 'policy.status_changed', 'system_policies', $policy->id, $oldValues, $policy->fresh()->toArray(), [
            'policy_id' => $policy->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật trạng thái chính sách.',
            'data' => $this->policyPayload($policy->fresh()),
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
            'policy_type' => ['required', Rule::in(['general', 'refund', 'booking', 'moderation', 'account', 'platform_fee', 'terms'])],
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

        return $data;
    }

    private function policySummaryPayload(SystemPolicy $policy): array
    {
        $payload = $this->policyPayload($policy);
        unset($payload['content']);
        return $payload;
    }

    private function policyPayload(SystemPolicy $policy): array
    {
        $policyType = $policy->policy_type ?: $policy->type;

        return [
            'id' => $policy->id,
            'key' => $policy->key,
            'version' => (int) $policy->version,
            'title' => $policy->title,
            'content' => $policy->content,
            'type' => $policy->type,
            'policy_type' => $policyType,
            'policy_type_label' => $this->policyTypeLabel($policyType),
            'status' => $policy->status,
            'status_label' => $this->statusLabel($policy->status),
            'is_active' => (bool) $policy->is_active,
            'is_overridable' => (bool) $policy->is_overridable,
            'priority' => (int) $policy->priority,
            'effective_from' => $policy->effective_from,
            'effective_to' => $policy->effective_to,
            'published_at' => $policy->published_at,
            'require_reaccept' => (bool) $policy->require_reaccept,
            'change_summary' => $policy->change_summary,
            'business_summary' => $this->policyBusinessSummary($policy),
            'can_edit_content' => $policy->status !== 'active',
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
        ];
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
            'description' => $binding->description,
            'is_active' => (bool) $binding->is_active,
            'created_at' => $binding->created_at,
            'updated_at' => $binding->updated_at,
        ];
    }

    private function rulePayload(PolicyRule $rule): array
    {
        return [
            'id' => $rule->id,
            'system_policy_id' => $rule->system_policy_id,
            'action_code' => $rule->action_code,
            'action_label' => $this->actionLabel($rule->action_code),
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->rule_name,
            'rule_type' => $rule->rule_type,
            'rule_type_label' => $this->ruleTypeLabel($rule->rule_type),
            'decision_key' => $rule->decision_key,
            'conflict_group' => $rule->conflict_group,
            'condition_json' => $rule->condition_json,
            'result_json' => $rule->result_json,
            'constraint_json' => $rule->constraint_json,
            'allowed_override_json' => $rule->allowed_override_json,
            'business_summary' => $this->ruleBusinessSummary($rule),
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
            'booking' => 'booking',
            'moderation', 'account' => 'moderation',
            default => 'general',
        };
    }

    private function changesProtectedFields(SystemPolicy $policy, array $data): bool
    {
        foreach (['key', 'version', 'content', 'policy_type', 'is_overridable', 'require_reaccept'] as $field) {
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
        return collect($this->actionOptions())
            ->filter(fn (array $item): bool => in_array($policyType, $item['policy_types'], true))
            ->pluck('action_code')
            ->all();
    }

    private function allowedRuleTypes(string $policyType): array
    {
        return collect($this->ruleTemplateOptions())
            ->filter(fn (array $item): bool => in_array($policyType, $item['policy_types'], true))
            ->keys()
            ->all();
    }

    private function actionOptions(): array
    {
        return [
            ['module' => 'booking', 'module_label' => 'Đặt sân', 'action_code' => 'booking.cancel', 'action_label' => 'Hủy lịch đặt sân', 'description' => 'Áp dụng khi khách hoặc sân hủy booking.', 'policy_types' => ['general', 'booking', 'refund']],
            ['module' => 'booking', 'module_label' => 'Đặt sân', 'action_code' => 'booking.create', 'action_label' => 'Tạo lịch đặt sân', 'description' => 'Áp dụng khi tạo booking mới.', 'policy_types' => ['general', 'booking']],
            ['module' => 'booking', 'module_label' => 'Đặt sân', 'action_code' => 'booking.confirm', 'action_label' => 'Xác nhận lịch đặt sân', 'description' => 'Áp dụng khi xác nhận booking.', 'policy_types' => ['general', 'booking']],
            ['module' => 'refund', 'module_label' => 'Hoàn tiền', 'action_code' => 'refund.request', 'action_label' => 'Khách yêu cầu hoàn tiền', 'description' => 'Áp dụng khi khách gửi yêu cầu hoàn tiền.', 'policy_types' => ['general', 'refund']],
            ['module' => 'refund', 'module_label' => 'Hoàn tiền', 'action_code' => 'refund.owner_confirm', 'action_label' => 'Chủ sân xác nhận hoàn tiền', 'description' => 'Áp dụng khi chủ sân xác nhận yêu cầu hoàn.', 'policy_types' => ['general', 'refund']],
            ['module' => 'refund', 'module_label' => 'Hoàn tiền', 'action_code' => 'refund.admin_confirm', 'action_label' => 'Admin xác nhận hoàn tiền', 'description' => 'Áp dụng khi admin xác nhận hoàn tiền hoàn tất.', 'policy_types' => ['general', 'refund']],
            ['module' => 'report', 'module_label' => 'Báo cáo vi phạm', 'action_code' => 'report.create', 'action_label' => 'Người dùng báo cáo vi phạm', 'description' => 'Áp dụng khi tạo báo cáo vi phạm.', 'policy_types' => ['general', 'moderation']],
            ['module' => 'report', 'module_label' => 'Báo cáo vi phạm', 'action_code' => 'report.resolve', 'action_label' => 'Xử lý báo cáo vi phạm', 'description' => 'Áp dụng khi admin xử lý báo cáo vi phạm.', 'policy_types' => ['general', 'moderation']],
            ['module' => 'complaint', 'module_label' => 'Khiếu nại', 'action_code' => 'complaint.create', 'action_label' => 'Tạo khiếu nại', 'description' => 'Áp dụng khi người dùng tạo khiếu nại.', 'policy_types' => ['general', 'moderation']],
            ['module' => 'complaint', 'module_label' => 'Khiếu nại', 'action_code' => 'complaint.resolve', 'action_label' => 'Xử lý khiếu nại', 'description' => 'Áp dụng khi admin xử lý khiếu nại.', 'policy_types' => ['general', 'moderation']],
            ['module' => 'account', 'module_label' => 'Tài khoản', 'action_code' => 'account.lock', 'action_label' => 'Khóa tài khoản', 'description' => 'Áp dụng khi khóa tài khoản.', 'policy_types' => ['general', 'account', 'moderation']],
            ['module' => 'account', 'module_label' => 'Tài khoản', 'action_code' => 'account.unlock', 'action_label' => 'Mở khóa tài khoản', 'description' => 'Áp dụng khi mở khóa tài khoản.', 'policy_types' => ['general', 'account']],
            ['module' => 'venue', 'module_label' => 'Cụm sân', 'action_code' => 'venue.lock', 'action_label' => 'Khóa cụm sân', 'description' => 'Áp dụng khi khóa cụm sân.', 'policy_types' => ['general', 'platform_fee']],
            ['module' => 'venue', 'module_label' => 'Cụm sân', 'action_code' => 'venue.lock_due_fee', 'action_label' => 'Khóa cụm sân do quá hạn phí', 'description' => 'Áp dụng khi cụm sân quá hạn phí duy trì.', 'policy_types' => ['general', 'platform_fee']],
            ['module' => 'auth', 'module_label' => 'Xác thực', 'action_code' => 'first_login.accept_policy', 'action_label' => 'Bắt buộc đồng ý điều khoản', 'description' => 'Áp dụng khi user cần đọc và đồng ý chính sách.', 'policy_types' => ['general', 'terms']],
        ];
    }

    private function ruleTemplateOptions(): array
    {
        return [
            'refund_by_cancel_time' => [
                'rule_type' => 'refund_by_cancel_time',
                'label' => 'Hoàn tiền theo thời điểm hủy',
                'rule_type_label' => 'Hoàn tiền theo thời điểm hủy',
                'description' => 'Xác định phần trăm hoàn tiền theo số giờ khách hủy trước giờ bắt đầu.',
                'decision_key' => 'refund_percent',
                'conflict_group' => 'refund_percent',
                'condition_json' => ['hours_before_start' => ['gte' => 24]],
                'result_json' => ['refund_percent' => 80, 'requires_owner_confirm' => true, 'requires_admin_confirm' => false],
                'policy_types' => ['general', 'refund'],
                'action_codes' => ['booking.cancel', 'refund.request'],
            ],
            'refund_time_window' => [
                'rule_type' => 'refund_time_window',
                'label' => 'Hoàn tiền theo khung thời gian',
                'rule_type_label' => 'Hoàn tiền theo khung thời gian',
                'description' => 'Dùng cho các mốc hoàn tiền khác nhau trong cùng chính sách hoàn hủy.',
                'decision_key' => 'refund_percent',
                'conflict_group' => 'refund_percent',
                'condition_json' => ['hours_before_start' => ['gte' => 12]],
                'result_json' => ['refund_percent' => 50, 'requires_owner_confirm' => true, 'requires_admin_confirm' => true],
                'policy_types' => ['general', 'refund'],
                'action_codes' => ['booking.cancel', 'refund.request'],
            ],
            'report_auto_lock' => [
                'rule_type' => 'report_auto_lock',
                'label' => 'Gợi ý khóa tài khoản theo số báo cáo',
                'rule_type_label' => 'Gợi ý khóa tài khoản theo số báo cáo',
                'description' => 'Tự đề xuất cảnh báo hoặc khóa tài khoản khi có nhiều báo cáo hợp lệ.',
                'decision_key' => 'moderation_action',
                'conflict_group' => 'report_auto_lock',
                'condition_json' => ['report_count' => ['gte' => 10], 'unique_reporters' => ['gte' => 3], 'window_days' => 30],
                'result_json' => ['action' => 'temporary_lock', 'lock_days' => 7],
                'policy_types' => ['general', 'moderation'],
                'action_codes' => ['report.create', 'report.resolve', 'account.lock'],
            ],
            'report_threshold' => [
                'rule_type' => 'report_threshold',
                'label' => 'Ngưỡng xử lý báo cáo vi phạm',
                'rule_type_label' => 'Ngưỡng xử lý báo cáo vi phạm',
                'description' => 'Xác định ngưỡng report để hệ thống nhắc admin xử lý.',
                'decision_key' => 'report_review_required',
                'conflict_group' => 'report_threshold',
                'condition_json' => ['report_count' => ['gte' => 5], 'unique_reporters' => ['gte' => 2], 'window_days' => 14],
                'result_json' => ['action' => 'require_admin_review'],
                'policy_types' => ['general', 'moderation'],
                'action_codes' => ['report.create', 'report.resolve', 'complaint.resolve'],
            ],
            'platform_fee_overdue' => [
                'rule_type' => 'platform_fee_overdue',
                'label' => 'Xử lý cụm sân quá hạn phí duy trì',
                'rule_type_label' => 'Xử lý cụm sân quá hạn phí duy trì',
                'description' => 'Khóa hoặc nhắc phí khi cụm sân quá hạn thanh toán phí duy trì.',
                'decision_key' => 'venue_fee_action',
                'conflict_group' => 'platform_fee_overdue',
                'condition_json' => ['overdue_days' => ['gte' => 7]],
                'result_json' => ['action' => 'lock_venue', 'reason' => 'Quá hạn phí duy trì nền tảng'],
                'policy_types' => ['general', 'platform_fee'],
                'action_codes' => ['venue.lock', 'venue.lock_due_fee'],
            ],
            'account_lock_manual' => [
                'rule_type' => 'account_lock_manual',
                'label' => 'Khóa tài khoản thủ công',
                'rule_type_label' => 'Khóa tài khoản thủ công',
                'description' => 'Bắt buộc admin nhập lý do khi khóa tài khoản.',
                'decision_key' => 'account_lock_reason_required',
                'conflict_group' => 'account_lock_manual',
                'condition_json' => ['manual_action' => true],
                'result_json' => ['requires_reason' => true, 'requires_audit_log' => true],
                'policy_types' => ['general', 'account', 'moderation'],
                'action_codes' => ['account.lock'],
            ],
            'first_login_accept_required' => [
                'rule_type' => 'first_login_accept_required',
                'label' => 'Bắt buộc đồng ý chính sách',
                'rule_type_label' => 'Bắt buộc đồng ý chính sách',
                'description' => 'Yêu cầu người dùng đồng ý phiên bản chính sách mới nhất khi đăng nhập.',
                'decision_key' => 'require_reaccept',
                'conflict_group' => 'terms_acceptance',
                'condition_json' => ['first_login_after_publish' => true],
                'result_json' => ['require_reaccept' => true],
                'policy_types' => ['general', 'terms'],
                'action_codes' => ['first_login.accept_policy'],
            ],
            'booking_auto_cancel_unpaid' => [
                'rule_type' => 'booking_auto_cancel_unpaid',
                'label' => 'Tự hủy booking chưa thanh toán',
                'rule_type_label' => 'Tự hủy booking chưa thanh toán',
                'description' => 'Tự hủy booking nếu quá thời gian giữ chỗ nhưng chưa thanh toán.',
                'decision_key' => 'booking_auto_cancel_minutes',
                'conflict_group' => 'booking_auto_cancel',
                'condition_json' => ['unpaid_minutes' => ['gte' => 15]],
                'result_json' => ['action' => 'cancel_booking'],
                'policy_types' => ['general', 'booking'],
                'action_codes' => ['booking.create', 'booking.confirm'],
            ],
        ];
    }

    private function policyTypeLabel(?string $type): string
    {
        return [
            'general' => 'Chung',
            'refund' => 'Hủy lịch và hoàn tiền',
            'booking' => 'Đặt sân',
            'moderation' => 'Kiểm duyệt và báo cáo',
            'account' => 'Tài khoản',
            'platform_fee' => 'Phí duy trì cụm sân',
            'terms' => 'Điều khoản sử dụng',
        ][$type] ?? ($type ?: 'Không xác định');
    }

    private function statusLabel(?string $status): string
    {
        return [
            'draft' => 'Bản nháp',
            'active' => 'Đang áp dụng',
            'inactive' => 'Tạm ngưng',
            'archived' => 'Đã lưu trữ',
        ][$status] ?? ($status ?: 'Không xác định');
    }

    private function moduleLabel(?string $module): string
    {
        return [
            'auth' => 'Xác thực',
            'booking' => 'Đặt sân',
            'refund' => 'Hoàn tiền',
            'complaint' => 'Khiếu nại',
            'report' => 'Báo cáo vi phạm',
            'account' => 'Tài khoản',
            'venue' => 'Cụm sân',
        ][$module] ?? ($module ?: 'Khác');
    }

    private function actionLabel(?string $code): string
    {
        if (! $code) {
            return 'Không xác định';
        }
        foreach ($this->actionOptions() as $option) {
            if ($option['action_code'] === $code) {
                return $option['action_label'];
            }
        }
        return str_replace(['.', '_'], [' / ', ' '], $code);
    }

    private function ruleTypeLabel(?string $type): string
    {
        if (! $type) {
            return 'Không xác định';
        }
        $template = $this->ruleTemplateOptions()[$type] ?? null;
        return $template['rule_type_label'] ?? str_replace('_', ' ', $type);
    }

    private function policyBusinessSummary(SystemPolicy $policy): string
    {
        $type = $this->policyTypeLabel($policy->policy_type ?: $policy->type);
        $status = $this->statusLabel($policy->status);
        $summary = $type . ', ' . $status . ', phiên bản ' . $policy->version . '.';
        if ($policy->require_reaccept) {
            $summary .= ' Người dùng cần đồng ý lại khi chính sách được áp dụng.';
        }
        if ($policy->is_overridable) {
            $summary .= ' Chủ sân có thể cấu hình ghi đè nếu module hỗ trợ.';
        }
        return $summary;
    }

    private function ruleBusinessSummary(PolicyRule $rule): string
    {
        $condition = $rule->condition_json ?: [];
        $result = $rule->result_json ?: [];
        return match ($rule->rule_type) {
            'refund_by_cancel_time', 'refund_time_window' => sprintf(
                'Nếu khách hủy trước ít nhất %s giờ, hệ thống hoàn %s%% tiền%s%s.',
                $this->ruleConditionValue($condition, 'hours_before_start'),
                $this->safeScalar($result['refund_percent'] ?? '?'),
                ($result['requires_owner_confirm'] ?? false) ? ', cần chủ sân xác nhận' : '',
                ($result['requires_admin_confirm'] ?? false) ? ', cần admin xác nhận' : ''
            ),
            'report_auto_lock', 'report_threshold' => sprintf(
                'Nếu có ít nhất %s báo cáo từ %s người khác nhau trong %s ngày, hệ thống thực hiện: %s.',
                $this->ruleConditionValue($condition, 'report_count'),
                $this->ruleConditionValue($condition, 'unique_reporters'),
                $this->safeScalar($condition['window_days'] ?? '?'),
                $this->resultActionLabel($result['action'] ?? null)
            ),
            'platform_fee_overdue' => sprintf(
                'Nếu cụm sân quá hạn phí %s ngày, hệ thống thực hiện: %s.',
                $this->ruleConditionValue($condition, 'overdue_days'),
                $this->resultActionLabel($result['action'] ?? null)
            ),
            'account_lock_manual' => 'Admin phải nhập lý do khi khóa tài khoản thủ công.',
            'first_login_accept_required' => 'Người dùng phải đồng ý phiên bản chính sách mới nhất trước khi tiếp tục sử dụng.',
            'booking_auto_cancel_unpaid' => sprintf('Booking chưa thanh toán sẽ tự hủy sau %s phút.', $this->ruleConditionValue($condition, 'unpaid_minutes')),
            default => $rule->rule_name ?: $this->ruleTypeLabel($rule->rule_type),
        };
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
        if (! is_scalar($action) && $action !== null) return 'xử lý theo cấu hình';
        return [
            'warning' => 'gửi cảnh báo',
            'temporary_lock' => 'khóa tài khoản tạm thời',
            'permanent_lock' => 'khóa tài khoản vĩnh viễn',
            'require_admin_review' => 'yêu cầu admin kiểm tra',
            'lock_venue' => 'khóa cụm sân',
            'notify' => 'gửi thông báo nhắc nhở',
            'cancel_booking' => 'hủy booking',
        ][$action] ?? ($action ?: 'xử lý theo cấu hình');
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

        foreach ($policy->rules->where('is_active', true) as $rule) {
            $this->ensureRuleCompatible($policy, $rule->rule_type);
            $this->ensureActionCompatible($policy, $rule->action_code);
            $this->ensureRuleActionPairCompatible($rule->rule_type, $rule->action_code);

            if (! in_array($rule->action_code, $activeActionCodes, true)) {
                throw ValidationException::withMessages([
                    'action_bindings' => 'Quy tắc "' . $rule->rule_name . '" đang dùng thao tác chưa được bật trong chính sách.',
                ]);
            }

            if (! in_array($rule->rule_type, $this->allowedRuleTypes($policyType), true)) {
                throw ValidationException::withMessages([
                    'rule_type' => 'Loại quy tắc này không phù hợp với chính sách ' . $this->policyTypeLabel($policyType) . '.',
                ]);
            }
        }
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
