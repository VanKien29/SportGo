<?php

namespace App\Services\Admin;

use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use Illuminate\Support\Collection;

class PolicyConflictService
{
    public function validateBeforePublish(SystemPolicy $policy): array
    {
        $policy->loadMissing(['actionBindings', 'rules']);

        $errors = [];
        $activeRules = $policy->rules->where('is_active', true)->values();

        if ($policy->actionBindings->where('is_active', true)->isEmpty()) {
            $errors[] = 'Chính sách cần có ít nhất một thao tác áp dụng trước khi kích hoạt.';
        }

        if ($this->policyNeedsRules($policy) && $activeRules->isEmpty()) {
            $errors[] = 'Chính sách này cần có ít nhất một quy tắc đang bật trước khi kích hoạt.';
        }

        foreach ($this->findInternalConflicts($activeRules) as $message) {
            $errors[] = $message;
        }

        foreach ($activeRules as $rule) {
            $conflict = $this->findConflict($policy, $rule);

            if ($conflict) {
                $errors[] = sprintf(
                    'Quy tắc "%s" xung đột với chính sách đang áp dụng "%s" / quy tắc "%s".',
                    $rule->rule_name,
                    $conflict->policy?->title ?? $conflict->system_policy_id,
                    $conflict->rule_name
                );
            }
        }

        return array_values(array_unique($errors));
    }

    private function policyNeedsRules(SystemPolicy $policy): bool
    {
        return in_array($policy->policy_type ?: $policy->type, [
            'refund',
            'booking',
            'moderation',
            'account',
            'platform_fee',
            'terms',
        ], true);
    }

    /**
     * @param Collection<int, PolicyRule> $rules
     * @return array<int, string>
     */
    private function findInternalConflicts(Collection $rules): array
    {
        $errors = [];

        foreach ($rules as $index => $rule) {
            foreach ($rules->slice($index + 1) as $candidate) {
                if (! $this->sameDecisionScope($rule, $candidate)) {
                    continue;
                }

                if ($this->normalizeJson($candidate->condition_json) === $this->normalizeJson($rule->condition_json)
                    && $this->normalizeJson($candidate->result_json) !== $this->normalizeJson($rule->result_json)) {
                    $errors[] = sprintf(
                        'Hai quy tắc "%s" và "%s" đang cùng điều kiện nhưng trả kết quả khác nhau.',
                        $rule->rule_name,
                        $candidate->rule_name
                    );
                }
            }
        }

        return $errors;
    }

    private function findConflict(SystemPolicy $policy, PolicyRule $rule): ?PolicyRule
    {
        if (! $rule->decision_key && ! $rule->conflict_group) {
            return null;
        }

        /** @var Collection<int, PolicyRule> $candidates */
        $candidates = PolicyRule::query()
            ->with('policy:id,key,title,status,is_active,effective_from,effective_to')
            ->where('id', '!=', $rule->id)
            ->where('system_policy_id', '!=', $policy->id)
            ->where('action_code', $rule->action_code)
            ->where('rule_type', $rule->rule_type)
            ->where('is_active', true)
            ->where(function ($query) use ($rule): void {
                if ($rule->decision_key) {
                    $query->orWhere('decision_key', $rule->decision_key);
                }

                if ($rule->conflict_group) {
                    $query->orWhere('conflict_group', $rule->conflict_group);
                }
            })
            ->whereHas('policy', function ($query) use ($policy): void {
                $query->where('status', 'active')
                    ->where('is_active', true)
                    ->where('key', '!=', $policy->key);
            })
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->sameDecisionScope($rule, $candidate)
                && $this->normalizeJson($candidate->condition_json) === $this->normalizeJson($rule->condition_json)
                && $this->normalizeJson($candidate->result_json) !== $this->normalizeJson($rule->result_json)) {
                return $candidate;
            }
        }

        return null;
    }

    private function sameDecisionScope(PolicyRule $left, PolicyRule $right): bool
    {
        if ($left->action_code !== $right->action_code || $left->rule_type !== $right->rule_type) {
            return false;
        }

        if ($left->decision_key && $right->decision_key && $left->decision_key === $right->decision_key) {
            return true;
        }

        return $left->conflict_group
            && $right->conflict_group
            && $left->conflict_group === $right->conflict_group;
    }

    private function normalizeJson(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        if (is_array($value)) {
            ksort($value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
