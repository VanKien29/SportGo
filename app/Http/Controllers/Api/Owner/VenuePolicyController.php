<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use App\Services\Policies\RefundCancellationPolicyService;
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
    public function __construct(private readonly RefundCancellationPolicyService $refundPolicies)
    {
    }

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
                    'cancellation_configuration' => $this->policyCancellationConfiguration($policy, $venueRules),
                    'refund_configuration' => $this->policyRefundConfiguration($policy, $venueRules),
                    'rules' => $policy->rules->map(function (PolicyRule $rule) use ($policy, $venueRules): array {
                        $venueRule = $venueRules
                            ->where('base_policy_rule_id', $rule->id)
                            ->where('rule_type', '!=', 'customer_notice')
                            ->where('status', '!=', 'inactive')
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
            'tiers' => ['nullable', 'array'],
            'tiers.*.key' => ['nullable', 'string'],
            'tiers.*.refund_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tiers.*.allow_cancel' => ['nullable', 'boolean'],
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

        if (! in_array($baseRule->rule_type, [
            RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
            RefundCancellationPolicyService::REFUND_RULE_TYPE,
        ], true)) {
            throw ValidationException::withMessages([
                'base_policy_rule_id' => 'Hiện chỉ hỗ trợ chủ sân cấu hình bảng mốc hủy booking hoặc hoàn tiền theo khung hệ thống.',
            ]);
        }

        if ($baseRule->rule_type === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE) {
            $systemTiers = $this->refundPolicies->cancellationTiersFromRule($baseRule);
            $venueTiers = $this->refundPolicies->validateVenueCancellationTiers($data['tiers'] ?? $systemTiers, $systemTiers);
            $condition = ['uses_tier_table' => true];
            $result = [
                'tiers' => $venueTiers,
                'summary_vi' => $this->refundPolicies->cancellationSummary($venueTiers),
            ];
            $constraintResult = [
                'passed' => true,
                'message' => 'Cấu hình hủy booking nằm trong khung hệ thống.',
                'system_summary' => $this->refundPolicies->cancellationSummary($systemTiers),
                'venue_summary' => $this->refundPolicies->cancellationSummary($venueTiers),
            ];
        } else {
            $systemTiers = $this->refundPolicies->tiersFromRule($baseRule);
            $venueTiers = $data['tiers'] ?? null;

            if (! $venueTiers) {
                $venueTiers = $systemTiers;
                $legacyHours = (int) ($data['hours_before_start'] ?? 24);
                $legacyPercent = (float) ($data['refund_percent'] ?? ($venueTiers[0]['refund_percent'] ?? 0));
                foreach ($venueTiers as &$tier) {
                    if ((int) $tier['from_hours'] === $legacyHours) {
                        $tier['refund_percent'] = $legacyPercent;
                        break;
                    }
                }
            }

            $venueTiers = $this->refundPolicies->validateVenueTiers($venueTiers, $systemTiers);
            $condition = ['uses_tier_table' => true];
            $result = [
                'tiers' => $venueTiers,
                'refund_percent' => $venueTiers[0]['refund_percent'] ?? null,
                'requires_owner_confirm' => true,
                'requires_admin_confirm' => true,
                'summary_vi' => $this->refundPolicies->summary($venueTiers),
            ];

            $constraintResult = [
                'passed' => true,
                'message' => 'Cấu hình hoàn tiền nằm trong khung hệ thống.',
                'system_summary' => $this->refundPolicies->summary($systemTiers),
                'venue_summary' => $this->refundPolicies->summary($venueTiers),
            ];
        }
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

    public function destroyRule(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
        ]);

        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $rule = VenuePolicyRule::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('rule_type', '!=', 'customer_notice')
            ->findOrFail($id);
        $old = $rule->toArray();

        $rule->update([
            'status' => 'inactive',
            'updated_by' => $request->user()->id,
            'effective_to' => now(),
        ]);

        $this->audit(
            $request,
            'owner.venue_policy.rule_reset_to_system_default',
            'venue_policy_rules',
            $rule->id,
            $old,
            $rule->fresh()->toArray(),
            'Chủ sân dùng lại mặc định hệ thống.'
        );

        return response()->json([
            'message' => 'Đã chuyển chính sách sân về mặc định hệ thống.',
            'data' => $this->venueRulePayload($rule->fresh('baseRule')),
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
        $systemCancellationTiers = $rule->rule_type === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE
            ? $this->refundPolicies->cancellationTiersFromRule($rule)
            : null;
        $venueCancellationTiers = $systemCancellationTiers
            ? $this->refundPolicies->cancellationTiersFromVenueRule($venueRule, $systemCancellationTiers)
            : null;
        $systemTiers = $rule->rule_type === RefundCancellationPolicyService::REFUND_RULE_TYPE
            ? $this->refundPolicies->tiersFromRule($rule)
            : null;
        $venueTiers = $systemTiers
            ? $this->refundPolicies->tiersFromVenueRule($venueRule, $systemTiers)
            : null;
        $configurationType = match (true) {
            (bool) $systemCancellationTiers => 'cancellation_tier_table',
            (bool) $systemTiers => 'refund_tier_table',
            default => 'rule',
        };
        $previewSummary = match ($configurationType) {
            'cancellation_tier_table' => $this->refundPolicies->cancellationSummary($venueRule ? $venueCancellationTiers : $systemCancellationTiers),
            'refund_tier_table' => $this->refundPolicies->summary($venueRule ? $venueTiers : $systemTiers),
            default => $this->venuePreviewSummary($rule, $venueRule),
        };

        return [
            'id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'rule_type' => $rule->rule_type,
            'rule_label' => PolicyUiText::ruleTypeLabel($rule->rule_type),
            'action_code' => $rule->action_code,
            'action_label' => PolicyUiText::actionLabel($rule->action_code),
            'business_summary' => PolicyUiText::ruleBusinessSummary($rule),
            'configuration_type' => $configurationType,
            'cancellation_tiers' => $systemCancellationTiers ? $this->refundPolicies->cancellationPayload($systemCancellationTiers, $venueRule ? $venueCancellationTiers : null) : null,
            'cancellation_tier_summary' => $systemCancellationTiers ? $this->refundPolicies->cancellationSummary($venueRule ? $venueCancellationTiers : $systemCancellationTiers) : null,
            'refund_tiers' => $systemTiers ? $this->refundPolicies->payload($systemTiers, $venueRule ? $venueTiers : null) : null,
            'refund_tier_summary' => $systemTiers ? $this->refundPolicies->summary($venueRule ? $venueTiers : $systemTiers) : null,
            'system_value' => [
                'refund_percent' => $systemRefundPercent,
                'tiers' => $systemTiers ?: $systemCancellationTiers,
            ],
            'venue_value' => [
                'refund_percent' => $venueRefundPercent,
                'tiers' => $venueRule ? ($venueTiers ?: $venueCancellationTiers) : null,
            ],
            'limit_summary' => $this->constraintSummary($constraints),
            'constraints' => $constraints,
            'venue_rule' => $venueRule ? $this->venueRulePayload($venueRule) : null,
            'preview_summary' => $previewSummary,
            'can_override' => in_array($rule->rule_type, [
                RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
                RefundCancellationPolicyService::REFUND_RULE_TYPE,
            ], true),
        ];
    }

    private function policyRefundConfiguration(SystemPolicy $policy, $venueRules): ?array
    {
        $rule = $policy->rules->firstWhere('rule_type', RefundCancellationPolicyService::RULE_TYPE);
        if (! $rule) {
            return null;
        }

        $venueRule = $venueRules
            ->where('base_policy_rule_id', $rule->id)
            ->where('rule_type', '!=', 'customer_notice')
            ->where('status', '!=', 'inactive')
            ->first();
        $systemTiers = $this->refundPolicies->tiersFromRule($rule);
        $venueTiers = $venueRule ? $this->refundPolicies->tiersFromVenueRule($venueRule, $systemTiers) : null;

        return [
            'base_rule_id' => $rule->id,
            'venue_rule_id' => $venueRule?->id,
            'status' => $venueRule ? 'custom' : 'system_default',
            'status_label' => $venueRule ? 'Đã cấu hình riêng' : 'Đang dùng mặc định hệ thống',
            ...$this->refundPolicies->payload($systemTiers, $venueTiers),
        ];
    }

    private function policyCancellationConfiguration(SystemPolicy $policy, $venueRules): ?array
    {
        $rule = $policy->rules->firstWhere('rule_type', RefundCancellationPolicyService::CANCELLATION_RULE_TYPE);
        if (! $rule) {
            return null;
        }

        $venueRule = $venueRules
            ->where('base_policy_rule_id', $rule->id)
            ->where('rule_type', '!=', 'customer_notice')
            ->where('status', '!=', 'inactive')
            ->first();
        $systemTiers = $this->refundPolicies->cancellationTiersFromRule($rule);
        $venueTiers = $venueRule ? $this->refundPolicies->cancellationTiersFromVenueRule($venueRule, $systemTiers) : null;

        return [
            'base_rule_id' => $rule->id,
            'venue_rule_id' => $venueRule?->id,
            'status' => $venueRule ? 'custom' : 'system_default',
            'status_label' => $venueRule ? 'Đã cấu hình riêng' : 'Đang dùng mặc định hệ thống',
            ...$this->refundPolicies->cancellationPayload($systemTiers, $venueTiers),
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
        $baseRule = $rule->relationLoaded('baseRule') ? $rule->baseRule : null;
        $systemCancellationTiers = $ruleType === RefundCancellationPolicyService::CANCELLATION_RULE_TYPE
            ? $this->refundPolicies->cancellationTiersFromRule($baseRule)
            : null;
        $venueCancellationTiers = $systemCancellationTiers
            ? $this->refundPolicies->cancellationTiersFromVenueRule($rule, $systemCancellationTiers)
            : null;
        $systemTiers = $ruleType === RefundCancellationPolicyService::REFUND_RULE_TYPE
            ? $this->refundPolicies->tiersFromRule($baseRule)
            : null;
        $venueTiers = $systemTiers
            ? $this->refundPolicies->tiersFromVenueRule($rule, $systemTiers)
            : null;
        $summary = match (true) {
            $ruleType === 'customer_notice' => 'Quy định này chỉ hiển thị cho khách đọc, không tác động đến booking/refund/payment.',
            (bool) $venueCancellationTiers => $this->refundPolicies->cancellationSummary($venueCancellationTiers),
            (bool) $venueTiers => $this->refundPolicies->summary($venueTiers),
            default => PolicyUiText::ruleSummary($ruleType, $rule->condition_json ?: [], $rule->result_json ?: []),
        };

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
            'cancellation_tiers' => $venueCancellationTiers,
            'cancellation_tier_summary' => $venueCancellationTiers ? $this->refundPolicies->cancellationSummary($venueCancellationTiers) : null,
            'refund_tiers' => $venueTiers,
            'refund_tier_summary' => $venueTiers ? $this->refundPolicies->summary($venueTiers) : null,
            'constraint_check_result' => $rule->constraint_check_result,
            'business_summary' => $summary,
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
