<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use App\Support\PolicyUiText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VenuePolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));

        $venueRules = VenuePolicyRule::query()
            ->with(['baseRule:id,system_policy_id,rule_code,rule_name,rule_type,action_code'])
            ->where('venue_cluster_id', $cluster->id)
            ->latest()
            ->get();

        $systemPolicies = SystemPolicy::query()
            ->with(['rules' => fn ($query) => $query->where('is_active', true)->orderByDesc('priority')])
            ->where('status', 'active')
            ->where('is_overridable', true)
            ->orderBy('priority')
            ->get()
            ->map(function (SystemPolicy $policy) use ($venueRules): array {
                return [
                    'id' => $policy->id,
                    'title' => $policy->title,
                    'policy_type' => $policy->policy_type,
                    'policy_type_label' => PolicyUiText::policyTypeLabel($policy->policy_type),
                    'business_summary' => PolicyUiText::policyBusinessSummary($policy),
                    'rules' => $policy->rules->map(function (PolicyRule $rule) use ($policy, $venueRules): array {
                        $venueRule = $venueRules
                            ->where('base_policy_rule_id', $rule->id)
                            ->where('rule_type', '!=', 'customer_notice')
                            ->first();

                        return $this->systemRulePayload($policy, $rule, $venueRule);
                    })->values(),
                ];
            });

        return response()->json([
            'data' => [
                'venue_cluster' => [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'status' => $cluster->status,
                    'status_reason' => $cluster->status_reason,
                ],
                'system_policies' => $systemPolicies,
                'venue_rules' => $venueRules
                    ->where('rule_type', '!=', 'customer_notice')
                    ->map(fn (VenuePolicyRule $rule): array => $this->venueRulePayload($rule))
                    ->values(),
                'customer_notices' => $venueRules
                    ->where('rule_type', 'customer_notice')
                    ->map(fn (VenuePolicyRule $rule): array => $this->venueRulePayload($rule))
                    ->values(),
            ],
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'base_policy_rule_id' => ['required', 'string', 'exists:policy_rules,id'],
            'refund_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hours_before_start' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['draft', 'pending_review', 'active'])],
        ], [
            'refund_percent.min' => 'Phần trăm hoàn tiền không được nhỏ hơn 0.',
            'refund_percent.max' => 'Phần trăm hoàn tiền không được lớn hơn 100.',
            'hours_before_start.min' => 'Thời gian hủy trước giờ chơi phải lớn hơn 0.',
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $baseRule = PolicyRule::query()->with('policy')->findOrFail($data['base_policy_rule_id']);
        $policy = $baseRule->policy;

        if (! $policy?->is_overridable || $policy->status !== 'active') {
            throw ValidationException::withMessages([
                'base_policy_rule_id' => 'Quy tắc này không cho phép sân cấu hình riêng.',
            ]);
        }

        if ($baseRule->rule_type !== 'refund_percent_by_cancel_time') {
            throw ValidationException::withMessages([
                'base_policy_rule_id' => 'Hiện chỉ hỗ trợ chủ sân cấu hình quy tắc hoàn tiền theo khung hệ thống.',
            ]);
        }

        $condition = $baseRule->condition_json ?: [];
        $result = $baseRule->result_json ?: [];
        $result['refund_percent'] = (float) ($data['refund_percent'] ?? $result['refund_percent'] ?? 0);
        $condition['hours_before_start'] = ['gte' => (int) ($data['hours_before_start'] ?? $this->conditionValue($condition, 'hours_before_start', 24))];

        $constraintResult = $this->checkConstraints($policy->id, $baseRule, $result);
        if (! $constraintResult['passed']) {
            throw ValidationException::withMessages([
                'refund_percent' => $constraintResult['message'],
            ]);
        }

        $old = VenuePolicyRule::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('base_policy_rule_id', $baseRule->id)
            ->where('rule_code', $baseRule->rule_code)
            ->first();

        $rule = VenuePolicyRule::query()->updateOrCreate([
            'venue_cluster_id' => $cluster->id,
            'base_policy_rule_id' => $baseRule->id,
            'rule_code' => $baseRule->rule_code,
        ], [
            'action_code' => $baseRule->action_code,
            'rule_name' => $baseRule->rule_name ?: PolicyUiText::ruleTypeLabel($baseRule->rule_type),
            'rule_type' => $baseRule->rule_type,
            'condition_json' => $condition,
            'result_json' => $result,
            'status' => $data['status'],
            'created_by' => $old?->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'effective_from' => $data['status'] === 'active' ? now() : null,
            'constraint_check_result' => $constraintResult,
        ]);

        $this->audit(
            $request,
            'owner.venue_policy.rule_saved',
            'venue_policy_rules',
            $rule->id,
            $old?->toArray() ?: [],
            $rule->fresh()->toArray(),
            'Chủ sân cập nhật chính sách sân.'
        );

        return response()->json([
            'message' => 'Đã lưu chính sách sân.',
            'data' => $this->venueRulePayload($rule->fresh('baseRule')),
        ]);
    }

    public function storeNotice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'draft'])],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);

        $rule = VenuePolicyRule::query()->create([
            'venue_cluster_id' => $cluster->id,
            'base_policy_rule_id' => null,
            'action_code' => 'venue.customer_rule',
            'rule_code' => 'customer_notice_' . Str::lower(Str::random(8)),
            'rule_name' => $data['title'],
            'rule_type' => 'customer_notice',
            'condition_json' => [],
            'result_json' => ['content' => $data['content']],
            'status' => $data['status'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'effective_from' => $data['status'] === 'active' ? now() : null,
        ]);

        $this->audit($request, 'owner.venue_policy.notice_created', 'venue_policy_rules', $rule->id, [], $rule->toArray(), 'Chủ sân tạo quy định hiển thị cho khách.');

        return response()->json([
            'message' => 'Đã lưu quy định hiển thị cho khách.',
            'data' => $this->venueRulePayload($rule),
        ], 201);
    }

    public function updateNotice(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'draft'])],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $rule = VenuePolicyRule::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('rule_type', 'customer_notice')
            ->findOrFail($id);
        $old = $rule->toArray();

        $rule->update([
            'rule_name' => $data['title'],
            'result_json' => ['content' => $data['content']],
            'status' => $data['status'],
            'updated_by' => $request->user()->id,
            'effective_from' => $data['status'] === 'active' ? ($rule->effective_from ?: now()) : null,
        ]);

        $this->audit($request, 'owner.venue_policy.notice_updated', 'venue_policy_rules', $rule->id, $old, $rule->fresh()->toArray(), 'Chủ sân cập nhật quy định hiển thị cho khách.');

        return response()->json([
            'message' => 'Đã cập nhật quy định hiển thị cho khách.',
            'data' => $this->venueRulePayload($rule->fresh()),
        ]);
    }

    private function ownedCluster(Request $request, ?string $clusterId): VenueCluster
    {
        if (! $clusterId) {
            throw ValidationException::withMessages(['venue_cluster_id' => 'Vui lòng chọn cụm sân.']);
        }

        return VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($clusterId);
    }

    private function systemRulePayload(SystemPolicy $policy, PolicyRule $rule, ?VenuePolicyRule $venueRule): array
    {
        $constraints = $this->constraintsForRule($policy->id, $rule->id, $rule->rule_code);
        $systemRefundPercent = $rule->result_json['refund_percent'] ?? null;
        $venueRefundPercent = $venueRule?->result_json['refund_percent'] ?? null;

        return [
            'id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'rule_type' => $rule->rule_type,
            'rule_label' => PolicyUiText::ruleTypeLabel($rule->rule_type),
            'action_code' => $rule->action_code,
            'action_label' => PolicyUiText::actionLabel($rule->action_code),
            'business_summary' => PolicyUiText::ruleBusinessSummary($rule),
            'system_value' => [
                'refund_percent' => $systemRefundPercent,
                'hours_before_start' => $this->conditionValue($rule->condition_json ?: [], 'hours_before_start', null),
            ],
            'venue_value' => [
                'refund_percent' => $venueRefundPercent,
                'hours_before_start' => $venueRule ? $this->conditionValue($venueRule->condition_json ?: [], 'hours_before_start', null) : null,
            ],
            'limit_summary' => $this->constraintSummary($constraints),
            'constraints' => $constraints,
            'venue_rule' => $venueRule ? $this->venueRulePayload($venueRule) : null,
            'preview_summary' => $this->venuePreviewSummary($rule, $venueRule),
            'can_override' => (bool) $rule->is_venue_overridable || $rule->rule_type === 'refund_percent_by_cancel_time',
        ];
    }

    private function constraintsForRule(string $policyId, string $ruleId, string $ruleCode)
    {
        if (! Schema::hasTable('policy_override_constraints')) {
            return collect();
        }

        return DB::table('policy_override_constraints')
            ->where('system_policy_id', $policyId)
            ->where(function ($query) use ($ruleId, $ruleCode): void {
                $query->where('policy_rule_id', $ruleId)->orWhere('rule_code', $ruleCode);
            })
            ->where('is_active', true)
            ->get();
    }

    private function checkConstraints(string $policyId, PolicyRule $rule, array $result): array
    {
        $constraints = $this->constraintsForRule($policyId, $rule->id, $rule->rule_code);

        foreach ($constraints as $constraint) {
            if ($constraint->constraint_key === 'refund_percent_minimum') {
                $min = (float) $constraint->min_value;
                $value = (float) ($result['refund_percent'] ?? 0);

                if ($value < $min) {
                    return [
                        'passed' => false,
                        'constraint_key' => $constraint->constraint_key,
                        'message' => $constraint->message_vi ?: "Chính sách sân không được hoàn thấp hơn mức tối thiểu {$min}% của hệ thống.",
                    ];
                }
            }
        }

        return ['passed' => true, 'message' => 'Cấu hình nằm trong khung hệ thống.'];
    }

    private function venueRulePayload(VenuePolicyRule $rule): array
    {
        $ruleType = $rule->rule_type;

        return [
            'id' => $rule->id,
            'venue_cluster_id' => $rule->venue_cluster_id,
            'base_policy_rule_id' => $rule->base_policy_rule_id,
            'title' => $rule->rule_name,
            'content' => $rule->result_json['content'] ?? null,
            'rule_code' => $rule->rule_code,
            'rule_type' => $ruleType,
            'rule_label' => $ruleType === 'customer_notice'
                ? 'Quy định hiển thị cho khách'
                : PolicyUiText::ruleTypeLabel($ruleType),
            'action_code' => $rule->action_code,
            'action_label' => $rule->action_code === 'venue.customer_rule'
                ? 'Hiển thị quy định cho khách'
                : PolicyUiText::actionLabel($rule->action_code),
            'status' => $rule->status,
            'status_label' => PolicyUiText::statusLabel($rule->status),
            'condition_json' => $rule->condition_json,
            'result_json' => $rule->result_json,
            'constraint_check_result' => $rule->constraint_check_result,
            'business_summary' => $ruleType === 'customer_notice'
                ? 'Quy định này chỉ hiển thị cho khách đọc, không tác động đến booking/refund/payment.'
                : PolicyUiText::ruleSummary($ruleType, $rule->condition_json ?: [], $rule->result_json ?: []),
            'created_at' => $rule->created_at,
            'updated_at' => $rule->updated_at,
        ];
    }

    private function constraintSummary($constraints): string
    {
        if ($constraints->isEmpty()) {
            return 'Không có giới hạn riêng ngoài khung chính sách hệ thống.';
        }

        return $constraints
            ->map(fn ($constraint) => $constraint->message_vi ?: $constraint->constraint_name)
            ->implode(' ');
    }

    private function venuePreviewSummary(PolicyRule $rule, ?VenuePolicyRule $venueRule): string
    {
        $condition = $venueRule?->condition_json ?: $rule->condition_json ?: [];
        $result = $venueRule?->result_json ?: $rule->result_json ?: [];

        return PolicyUiText::ruleSummary($rule->rule_type, $condition, $result);
    }

    private function conditionValue(array $condition, string $field, mixed $default): mixed
    {
        $value = $condition[$field] ?? $default;

        if (is_array($value)) {
            return $value['gte'] ?? $value['lte'] ?? $value['eq'] ?? $value['value'] ?? $default;
        }

        return $value;
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues, ?string $reason = null): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'user',
            'module' => 'venue_policy',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'owner',
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
