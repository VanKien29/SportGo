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

        $systemPolicies = SystemPolicy::query()
            ->with(['rules' => fn ($query) => $query->where('is_active', true)->orderByDesc('priority')])
            ->where('status', 'active')
            ->where('is_overridable', true)
            ->orderBy('priority')
            ->get()
            ->map(fn (SystemPolicy $policy): array => [
                'id' => $policy->id,
                'title' => $policy->title,
                'policy_type' => $policy->policy_type,
                'policy_type_label' => PolicyUiText::policyTypeLabel($policy->policy_type),
                'business_summary' => PolicyUiText::policyBusinessSummary($policy),
                'rules' => $policy->rules->map(fn (PolicyRule $rule): array => [
                    'id' => $rule->id,
                    'rule_code' => $rule->rule_code,
                    'rule_type' => $rule->rule_type,
                    'rule_label' => PolicyUiText::ruleTypeLabel($rule->rule_type),
                    'action_code' => $rule->action_code,
                    'action_label' => PolicyUiText::actionLabel($rule->action_code),
                    'system_value' => $rule->result_json,
                    'business_summary' => PolicyUiText::ruleBusinessSummary($rule),
                    'constraints' => $this->constraintsForRule($policy->id, $rule->id, $rule->rule_code),
                ])->values(),
            ]);

        $venueRules = VenuePolicyRule::query()
            ->with(['baseRule:id,rule_code,rule_name,rule_type,action_code'])
            ->where('venue_cluster_id', $cluster->id)
            ->latest()
            ->get()
            ->map(fn (VenuePolicyRule $rule): array => $this->venueRulePayload($rule));

        return response()->json([
            'data' => [
                'venue_cluster' => $cluster,
                'system_policies' => $systemPolicies,
                'venue_rules' => $venueRules->where('rule_type', '!=', 'customer_notice')->values(),
                'customer_notices' => $venueRules->where('rule_type', 'customer_notice')->values(),
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
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $baseRule = PolicyRule::query()->with('policy')->findOrFail($data['base_policy_rule_id']);
        $policy = $baseRule->policy;

        if (! $policy?->is_overridable || $policy->status !== 'active') {
            throw ValidationException::withMessages([
                'base_policy_rule_id' => 'Quy tắc này không cho phép sân cấu hình riêng.',
            ]);
        }

        $condition = $baseRule->condition_json ?: [];
        $result = $baseRule->result_json ?: [];
        if ($baseRule->rule_type === 'refund_percent_by_cancel_time') {
            $result['refund_percent'] = (float) ($data['refund_percent'] ?? $result['refund_percent'] ?? 0);
            $condition['hours_before_start'] = ['gte' => (int) ($data['hours_before_start'] ?? 24)];
        }

        $constraintResult = $this->checkConstraints($policy->id, $baseRule, $result);
        if (! $constraintResult['passed']) {
            throw ValidationException::withMessages([
                'refund_percent' => $constraintResult['message'],
            ]);
        }

        $rule = VenuePolicyRule::query()->updateOrCreate([
            'venue_cluster_id' => $cluster->id,
            'base_policy_rule_id' => $baseRule->id,
            'rule_code' => $baseRule->rule_code,
        ], [
            'action_code' => $baseRule->action_code,
            'rule_name' => $baseRule->rule_name,
            'rule_type' => $baseRule->rule_type,
            'condition_json' => $condition,
            'result_json' => $result,
            'status' => $data['status'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'effective_from' => $data['status'] === 'active' ? now() : null,
            'constraint_check_result' => $constraintResult,
        ]);

        $this->audit($request, 'owner.venue_policy.rule_saved', 'venue_policy_rules', $rule->id, [], $rule->toArray());

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

        $this->audit($request, 'owner.venue_policy.notice_created', 'venue_policy_rules', $rule->id, [], $rule->toArray());

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

        $this->audit($request, 'owner.venue_policy.notice_updated', 'venue_policy_rules', $rule->id, $old, $rule->fresh()->toArray());

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
        return [
            'id' => $rule->id,
            'venue_cluster_id' => $rule->venue_cluster_id,
            'base_policy_rule_id' => $rule->base_policy_rule_id,
            'title' => $rule->rule_name,
            'content' => $rule->result_json['content'] ?? null,
            'rule_code' => $rule->rule_code,
            'rule_type' => $rule->rule_type,
            'rule_label' => $rule->rule_type === 'customer_notice'
                ? 'Quy định hiển thị cho khách'
                : PolicyUiText::ruleTypeLabel($rule->rule_type),
            'action_code' => $rule->action_code,
            'action_label' => $rule->action_code === 'venue.customer_rule'
                ? 'Hiển thị quy định cho khách'
                : PolicyUiText::actionLabel($rule->action_code),
            'status' => $rule->status,
            'status_label' => PolicyUiText::statusLabel($rule->status),
            'condition_json' => $rule->condition_json,
            'result_json' => $rule->result_json,
            'constraint_check_result' => $rule->constraint_check_result,
            'created_at' => $rule->created_at,
            'updated_at' => $rule->updated_at,
        ];
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'actor_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'owner',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
