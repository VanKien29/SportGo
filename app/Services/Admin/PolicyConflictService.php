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

        if ($policy->actionBindings->where('is_active', true)->isEmpty()) {
            $errors[] = 'Chính sách cần có ít nhất một thao tác áp dụng trước khi kích hoạt.';
        }

        if ($this->policyNeedsRules($policy) && $policy->rules->where('is_active', true)->isEmpty()) {
            $errors[] = 'Chính sách này cần có ít nhất một quy tắc đang bật trước khi kích hoạt.';
        }

        foreach ($policy->rules->where('is_active', true) as $rule) {
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

        return $errors;
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
            ->whereHas('policy', function ($query): void {
                $query->where('status', 'active')->where('is_active', true);
            })
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->normalizeJson($candidate->condition_json) === $this->normalizeJson($rule->condition_json)
                && $this->normalizeJson($candidate->result_json) !== $this->normalizeJson($rule->result_json)) {
                return $candidate;
            }
        }

        return null;
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