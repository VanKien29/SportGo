<?php

namespace App\Services\Policies;

use App\Models\BookingConfig;
use App\Models\Payment;
use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\Refund;
use App\Models\SystemPolicy;
use App\Models\VenuePolicyRule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class RefundPolicyEvaluator
{
    private const REFUND_RULE_TYPES = ['refund_by_cancel_time', 'refund_time_window'];

    public function evaluate(Refund $refund, bool $log = false, string $actorType = 'system', ?string $actorId = null): array
    {
        $refund->loadMissing(['booking', 'payment']);
        $this->ensureBookingTimingLoaded($refund);

        $input = $this->buildInput($refund);

        if (! $input['has_booking_time']) {
            return $this->maybeLog($refund, [
                'evaluated' => false,
                'compliant' => null,
                'source' => 'missing_booking_time',
                'summary' => 'Chưa đủ dữ liệu giờ chơi để áp chính sách hoàn tiền.',
                'warning' => 'Booking thiếu ngày/giờ bắt đầu nên admin cần kiểm tra thủ công.',
                'input' => $input,
            ], $log, $actorType, $actorId);
        }

        $matched = $this->matchVenueRule($refund, $input)
            ?: $this->matchSystemRule($refund, $input)
            ?: $this->matchBookingConfig($refund, $input);

        if (! $matched) {
            return $this->maybeLog($refund, [
                'evaluated' => true,
                'compliant' => null,
                'source' => 'no_rule',
                'summary' => 'Chưa có quy tắc hoàn tiền phù hợp.',
                'warning' => 'Không tìm thấy rule hoàn tiền đang áp dụng cho khung giờ hủy này.',
                'input' => $input,
            ], $log, $actorType, $actorId);
        }

        $paidAmount = $this->paidAmount($refund);
        $refundPercent = (float) ($matched['result']['refund_percent'] ?? 0);
        $suggestedAmount = round($paidAmount * $refundPercent / 100, 2);
        $requestedAmount = (float) $refund->amount;
        $compliant = $requestedAmount <= $suggestedAmount;

        return $this->maybeLog($refund, [
            'evaluated' => true,
            'compliant' => $compliant,
            'source' => $matched['source'],
            'action_code' => $matched['action_code'],
            'refund_percent' => $refundPercent,
            'suggested_amount' => $suggestedAmount,
            'requested_amount' => $requestedAmount,
            'paid_amount' => $paidAmount,
            'requires_admin_review' => (bool) ($matched['result']['requires_admin_review'] ?? false),
            'hours_before_start' => $input['hours_before_start'],
            'policy' => $matched['policy'],
            'rule' => $matched['rule'],
            'summary' => $this->summary($matched, $refundPercent, $suggestedAmount, $compliant),
            'warning' => $compliant ? null : 'Số tiền hoàn đang vượt mức chính sách hiện tại.',
            'input' => $input,
        ], $log, $actorType, $actorId);
    }

    public function assertCompliant(Refund $refund, ?string $actorId = null, string $actorType = 'admin'): array
    {
        $result = $this->evaluate($refund, true, $actorType, $actorId);

        if (($result['compliant'] ?? null) === false) {
            throw new \RuntimeException(sprintf(
                'Số tiền hoàn vượt quá chính sách hiện tại. Tối đa có thể hoàn là %sđ.',
                number_format((float) $result['suggested_amount'], 0, ',', '.')
            ));
        }

        return $result;
    }

    private function ensureBookingTimingLoaded(Refund $refund): void
    {
        $booking = $refund->booking;

        if (! $booking) {
            return;
        }

        $attributes = $booking->getAttributes();

        foreach (['booking_date', 'start_time', 'cancelled_at', 'venue_cluster_id'] as $attribute) {
            if (! array_key_exists($attribute, $attributes)) {
                $refund->load('booking');

                return;
            }
        }
    }

    private function buildInput(Refund $refund): array
    {
        $booking = $refund->booking;
        $bookingDate = $booking?->booking_date ? Carbon::parse($booking->booking_date)->format('Y-m-d') : null;
        $startTime = $booking?->start_time ? substr((string) $booking->start_time, 0, 8) : null;
        $cancelledAt = $booking?->cancelled_at ?: $refund->created_at ?: now();
        $startAt = $bookingDate && $startTime ? Carbon::parse($bookingDate.' '.$startTime) : null;
        $hoursBeforeStart = $startAt ? round(($startAt->getTimestamp() - Carbon::parse($cancelledAt)->getTimestamp()) / 3600, 2) : null;

        return [
            'booking_id' => $booking?->id,
            'booking_code' => $booking?->booking_code,
            'venue_cluster_id' => $booking?->venue_cluster_id,
            'booking_start_at' => $startAt?->toIso8601String(),
            'cancelled_at' => Carbon::parse($cancelledAt)->toIso8601String(),
            'hours_before_start' => $hoursBeforeStart,
            'has_booking_time' => $startAt !== null,
            'payment_amount' => $this->paidAmount($refund),
            'requested_refund_amount' => (float) $refund->amount,
        ];
    }

    private function matchVenueRule(Refund $refund, array $input): ?array
    {
        if (! Schema::hasTable('venue_policy_rules') || ! $refund->booking?->venue_cluster_id) {
            return null;
        }

        $rule = VenuePolicyRule::query()
            ->with('baseRule.policy.actionBindings')
            ->where('venue_cluster_id', $refund->booking->venue_cluster_id)
            ->where('status', 'active')
            ->whereIn('rule_type', self::REFUND_RULE_TYPES)
            ->get()
            ->filter(fn (VenuePolicyRule $rule): bool => $this->ruleBelongsToActiveRefundPolicy($rule->baseRule)
                && $this->conditionsMatch($rule->condition_json ?? [], $input))
            ->sortByDesc(fn (VenuePolicyRule $rule): int => (int) ($rule->baseRule?->priority ?? 0))
            ->first();

        if (! $rule) {
            return null;
        }

        $rule->loadMissing('baseRule.policy');

        return [
            'source' => 'venue_policy_rule',
            'action_code' => $rule->action_code,
            'result' => $rule->result_json ?? [],
            'policy' => $this->policyPayload($rule->baseRule?->policy),
            'rule' => [
                'id' => $rule->id,
                'code' => $rule->rule_code,
                'name' => $rule->rule_name,
                'type' => $rule->rule_type,
                'venue_rule_id' => $rule->id,
                'base_rule_id' => $rule->base_policy_rule_id,
            ],
        ];
    }

    private function matchSystemRule(Refund $refund, array $input): ?array
    {
        if (! Schema::hasTable('policy_rules')) {
            return null;
        }

        $rule = PolicyRule::query()
            ->with('policy.actionBindings')
            ->where('is_active', true)
            ->whereIn('rule_type', self::REFUND_RULE_TYPES)
            ->whereHas('policy', fn ($query) => $this->activeRefundPolicyQuery($query))
            ->orderByDesc('priority')
            ->get()
            ->first(fn (PolicyRule $rule): bool => $this->policyHasActiveAction($rule->policy, $rule->action_code)
                && $this->conditionsMatch($rule->condition_json ?? [], $input));

        if (! $rule) {
            return null;
        }

        return [
            'source' => 'system_policy_rule',
            'action_code' => $rule->action_code,
            'result' => $rule->result_json ?? [],
            'policy' => $this->policyPayload($rule->policy),
            'rule' => [
                'id' => $rule->id,
                'code' => $rule->rule_code,
                'name' => $rule->rule_name,
                'type' => $rule->rule_type,
            ],
        ];
    }

    private function matchBookingConfig(Refund $refund, array $input): ?array
    {
        $config = $refund->booking?->venue_cluster_id
            ? BookingConfig::query()->whereKey($refund->booking->venue_cluster_id)->first()
            : null;

        if (! $config) {
            return null;
        }

        $refundPercent = ((float) $input['hours_before_start'] >= (int) $config->cancel_before_hours)
            ? (int) $config->refund_percent
            : 0;

        return [
            'source' => 'booking_config',
            'action_code' => 'booking_config.refund',
            'result' => [
                'refund_percent' => $refundPercent,
                'requires_admin_review' => $refundPercent === 0,
            ],
            'policy' => null,
            'rule' => [
                'id' => null,
                'code' => 'booking_config_refund',
                'name' => 'Cấu hình hoàn tiền của cụm sân',
                'type' => 'booking_config',
            ],
        ];
    }

    private function activeRefundPolicyQuery($query): void
    {
        $query
            ->where('is_active', true)
            ->where(function ($inner): void {
                $inner->where('policy_type', 'refund')->orWhere('type', 'refund');
            });

        if (Schema::hasColumn('system_policies', 'status')) {
            $query->where('status', 'active');
        }

        if (Schema::hasColumn('system_policies', 'effective_from')) {
            $query->where(function ($inner): void {
                $inner->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            });
        }

        if (Schema::hasColumn('system_policies', 'effective_to')) {
            $query->where(function ($inner): void {
                $inner->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
        }
    }

    private function conditionsMatch(array $conditions, array $input): bool
    {
        foreach ($conditions as $field => $rule) {
            $value = $input[$field] ?? null;

            if (is_array($rule)) {
                foreach ($rule as $operator => $expected) {
                    if (! $this->compare($value, $operator, $expected)) {
                        return false;
                    }
                }
                continue;
            }

            if ($value !== $rule) {
                return false;
            }
        }

        return true;
    }

    private function compare(mixed $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'gt' => $value > $expected,
            'gte' => $value >= $expected,
            'lt' => $value < $expected,
            'lte' => $value <= $expected,
            'eq' => $value == $expected,
            'neq' => $value != $expected,
            default => false,
        };
    }

    private function paidAmount(Refund $refund): float
    {
        if ($refund->payment && array_key_exists('amount', $refund->payment->getAttributes())) {
            return (float) $refund->payment->amount;
        }

        $amount = Payment::query()->whereKey($refund->payment_id)->value('amount');

        return $amount !== null ? (float) $amount : (float) $refund->amount;
    }

    private function maybeLog(Refund $refund, array $result, bool $log, string $actorType, ?string $actorId): array
    {
        if (! $log || ! Schema::hasTable('policy_evaluation_logs')) {
            return $result;
        }

        $policy = $result['policy'] ?? null;
        $rule = $result['rule'] ?? null;

        PolicyEvaluationLog::query()->create([
            'system_policy_id' => $policy['id'] ?? null,
            'policy_rule_id' => ($result['source'] ?? null) === 'system_policy_rule' ? ($rule['id'] ?? null) : ($rule['base_rule_id'] ?? null),
            'venue_policy_rule_id' => $rule['venue_rule_id'] ?? null,
            'action_code' => $result['action_code'] ?? 'refund.evaluate',
            'entity_type' => 'refund',
            'entity_id' => $refund->id,
            'input_data' => $result['input'] ?? [],
            'result_data' => $result,
            'policy_version_snapshot' => $policy,
            'rule_snapshot' => $rule,
            'evaluated_by_type' => $actorType,
            'evaluated_by_id' => $actorId,
            'created_at' => now(),
        ]);

        return $result;
    }

    private function policyPayload(?Model $policy): ?array
    {
        if (! $policy) {
            return null;
        }

        return [
            'id' => $policy->id,
            'key' => $policy->key,
            'version' => $policy->version,
            'title' => $policy->title,
        ];
    }

    private function ruleBelongsToActiveRefundPolicy(?PolicyRule $rule): bool
    {
        if (! $rule) {
            return false;
        }

        return $rule->is_active
            && in_array($rule->rule_type, self::REFUND_RULE_TYPES, true)
            && $rule->policy
            && $this->policyIsActiveRefundPolicy($rule->policy)
            && $this->policyHasActiveAction($rule->policy, $rule->action_code);
    }

    private function policyIsActiveRefundPolicy(Model $policy): bool
    {
        if (! $policy->is_active) {
            return false;
        }

        if (($policy->policy_type ?: $policy->type) !== 'refund') {
            return false;
        }

        if (Schema::hasColumn('system_policies', 'status') && $policy->status !== 'active') {
            return false;
        }

        if (Schema::hasColumn('system_policies', 'effective_from') && $policy->effective_from && $policy->effective_from->isFuture()) {
            return false;
        }

        if (Schema::hasColumn('system_policies', 'effective_to') && $policy->effective_to && $policy->effective_to->isPast()) {
            return false;
        }

        return true;
    }

    private function policyHasActiveAction(?Model $policy, ?string $actionCode): bool
    {
        if (! $policy || ! $actionCode) {
            return false;
        }

        $bindings = $policy->relationLoaded('actionBindings')
            ? $policy->actionBindings
            : $policy->actionBindings()->get();

        return $bindings->contains(fn ($binding): bool => $binding->is_active && $binding->action_code === $actionCode);
    }

    private function summary(array $matched, float $refundPercent, float $suggestedAmount, bool $compliant): string
    {
        $ruleName = $matched['rule']['name'] ?? 'quy tắc hoàn tiền';
        $amount = number_format($suggestedAmount, 0, ',', '.').'đ';
        $status = $compliant ? 'đúng chính sách' : 'vượt chính sách';

        return "{$ruleName}: hoàn {$refundPercent}%, tối đa {$amount} ({$status}).";
    }
}
