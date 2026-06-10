<?php

namespace App\Services\Policies;

use App\Models\Booking;
use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenuePolicyRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RefundCancellationPolicyService
{
    public const CANCELLATION_RULE_TYPE = 'cancel_before_hours';
    public const REFUND_RULE_TYPE = 'refund_percent_by_cancel_time';
    public const RULE_TYPE = self::REFUND_RULE_TYPE;

    public function defaultCancellationTiers(): array
    {
        return [
            [
                'key' => 'from_24',
                'label' => 'Từ 24 giờ trở lên',
                'from_hours' => 24,
                'to_hours' => null,
                'allow_cancel' => true,
            ],
            [
                'key' => 'from_6_to_24',
                'label' => 'Từ 6 đến dưới 24 giờ',
                'from_hours' => 6,
                'to_hours' => 24,
                'allow_cancel' => true,
            ],
            [
                'key' => 'from_1_to_6',
                'label' => 'Từ 1 đến dưới 6 giờ',
                'from_hours' => 1,
                'to_hours' => 6,
                'allow_cancel' => true,
            ],
            [
                'key' => 'under_1',
                'label' => 'Dưới 1 giờ',
                'from_hours' => null,
                'to_hours' => 1,
                'allow_cancel' => true,
            ],
        ];
    }

    public function defaultTiers(): array
    {
        return [
            [
                'key' => 'from_24',
                'label' => 'Từ 24 giờ trở lên',
                'from_hours' => 24,
                'to_hours' => null,
                'refund_percent' => 100,
                'allow_cancel' => true,
            ],
            [
                'key' => 'from_6_to_24',
                'label' => 'Từ 6 đến dưới 24 giờ',
                'from_hours' => 6,
                'to_hours' => 24,
                'refund_percent' => 80,
                'allow_cancel' => true,
            ],
            [
                'key' => 'from_1_to_6',
                'label' => 'Từ 1 đến dưới 6 giờ',
                'from_hours' => 1,
                'to_hours' => 6,
                'refund_percent' => 50,
                'allow_cancel' => true,
            ],
            [
                'key' => 'under_1',
                'label' => 'Dưới 1 giờ',
                'from_hours' => null,
                'to_hours' => 1,
                'refund_percent' => 0,
                'allow_cancel' => true,
            ],
        ];
    }

    public function cancellationTiersFromRule(?PolicyRule $rule): array
    {
        if (! $rule) {
            return $this->defaultCancellationTiers();
        }

        $result = $rule->result_json ?: [];
        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeCancellationTiers($result['tiers']);
        }

        $tiers = $this->defaultCancellationTiers();
        $hours = $this->conditionHours($rule->condition_json ?: []);
        $allowCancel = (bool) ($result['allow_cancel'] ?? true);

        if ($hours !== null) {
            foreach ($tiers as &$tier) {
                if ((int) $tier['from_hours'] === (int) $hours) {
                    $tier['allow_cancel'] = $allowCancel;
                    break;
                }
            }
        }

        return $this->normalizeCancellationTiers($tiers);
    }

    public function cancellationTiersFromVenueRule(?VenuePolicyRule $venueRule, array $systemTiers): array
    {
        if (! $venueRule) {
            return $this->normalizeCancellationTiers($systemTiers);
        }

        $result = $venueRule->result_json ?: [];
        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeCancellationTiers($result['tiers'], $systemTiers);
        }

        return $this->normalizeCancellationTiers($systemTiers);
    }

    public function tiersFromRule(?PolicyRule $rule): array
    {
        if (! $rule) {
            return $this->defaultTiers();
        }

        $result = $rule->result_json ?: [];
        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeTiers($result['tiers']);
        }

        $tiers = $this->defaultTiers();
        $hours = $this->conditionHours($rule->condition_json ?: []);
        $percent = isset($result['refund_percent']) ? (float) $result['refund_percent'] : null;

        if ($hours !== null && $percent !== null) {
            foreach ($tiers as &$tier) {
                if ((int) $tier['from_hours'] === (int) $hours) {
                    $tier['refund_percent'] = $percent;
                    break;
                }
            }
        }

        return $this->normalizeTiers($tiers);
    }

    public function tiersFromVenueRule(?VenuePolicyRule $venueRule, array $systemTiers): array
    {
        if (! $venueRule) {
            return $this->normalizeTiers($systemTiers);
        }

        $result = $venueRule->result_json ?: [];
        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeTiers($result['tiers'], $systemTiers);
        }

        $tiers = $this->normalizeTiers($systemTiers);
        $hours = $this->conditionHours($venueRule->condition_json ?: []);
        $percent = isset($result['refund_percent']) ? (float) $result['refund_percent'] : null;

        if ($hours !== null && $percent !== null) {
            foreach ($tiers as &$tier) {
                if ((int) $tier['from_hours'] === (int) $hours) {
                    $tier['refund_percent'] = $percent;
                    break;
                }
            }
        }

        return $this->normalizeTiers($tiers, $systemTiers);
    }

    public function normalizeCancellationTiers(array $tiers, ?array $fallback = null): array
    {
        $fallbackByKey = collect($fallback ?: $this->defaultCancellationTiers())->keyBy('key');
        $inputByKey = collect($tiers)->keyBy(fn (array $tier): string => (string) ($tier['key'] ?? $this->resolveKey($tier)));

        return collect($this->defaultCancellationTiers())
            ->map(function (array $shape) use ($fallbackByKey, $inputByKey): array {
                $fallback = $fallbackByKey->get($shape['key'], $shape);
                $input = $inputByKey->get($shape['key'], []);
                $allowCancel = array_key_exists('allow_cancel', $input)
                    ? (bool) $input['allow_cancel']
                    : (bool) ($fallback['allow_cancel'] ?? $shape['allow_cancel']);

                return [
                    'key' => $shape['key'],
                    'label' => $shape['label'],
                    'from_hours' => $shape['from_hours'],
                    'to_hours' => $shape['to_hours'],
                    'allow_cancel' => $allowCancel,
                    'condition_label' => $this->tierConditionLabel($shape),
                    'result_label' => $allowCancel ? 'Cho hủy' : 'Không cho hủy',
                    'business_sentence' => $this->cancellationSentence($shape, $allowCancel),
                ];
            })
            ->values()
            ->all();
    }

    public function normalizeTiers(array $tiers, ?array $fallback = null): array
    {
        $fallbackByKey = collect($fallback ?: $this->defaultTiers())->keyBy('key');
        $inputByKey = collect($tiers)->keyBy(fn (array $tier): string => (string) ($tier['key'] ?? $this->resolveKey($tier)));

        return collect($this->defaultTiers())
            ->map(function (array $shape) use ($fallbackByKey, $inputByKey): array {
                $fallback = $fallbackByKey->get($shape['key'], $shape);
                $input = $inputByKey->get($shape['key'], []);
                $refundPercent = round((float) ($input['refund_percent'] ?? $fallback['refund_percent'] ?? $shape['refund_percent']), 2);
                $allowCancel = array_key_exists('allow_cancel', $input)
                    ? (bool) $input['allow_cancel']
                    : (bool) ($fallback['allow_cancel'] ?? $shape['allow_cancel']);

                return [
                    'key' => $shape['key'],
                    'label' => $shape['label'],
                    'from_hours' => $shape['from_hours'],
                    'to_hours' => $shape['to_hours'],
                    'refund_percent' => $refundPercent,
                    'allow_cancel' => $allowCancel,
                    'condition_label' => $this->tierConditionLabel($shape),
                    'result_label' => $this->tierResultLabel(true, $refundPercent),
                    'business_sentence' => $this->refundSentence($shape, $refundPercent),
                ];
            })
            ->values()
            ->all();
    }

    public function validateSystemCancellationTiers(array $tiers): array
    {
        $normalized = $this->normalizeCancellationTiers($tiers);
        $this->assertCompleteTimeTable($normalized);

        return $normalized;
    }

    public function validateVenueCancellationTiers(array $venueTiers, array $systemTiers): array
    {
        $normalizedSystem = $this->normalizeCancellationTiers($systemTiers);
        $normalizedVenue = $this->normalizeCancellationTiers($venueTiers, $normalizedSystem);

        $this->assertCompleteTimeTable($normalizedVenue);
        $systemByKey = collect($normalizedSystem)->keyBy('key');
        $errors = [];

        foreach ($normalizedVenue as $index => $venueTier) {
            $systemTier = $systemByKey->get($venueTier['key']);
            if (! $systemTier) {
                continue;
            }

            if (($systemTier['allow_cancel'] ?? true) && ! ($venueTier['allow_cancel'] ?? true)) {
                $errors["tiers.{$index}.allow_cancel"] = "Mốc {$venueTier['label']} không được chặn hủy khi chính sách hệ thống đang cho phép hủy.";
            }

            if (! ($systemTier['allow_cancel'] ?? true) && ($venueTier['allow_cancel'] ?? true)) {
                $errors["tiers.{$index}.allow_cancel"] = "Mốc {$venueTier['label']} không được cho hủy khi chính sách hệ thống không cho hủy.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedVenue;
    }

    public function validateSystemTiers(array $tiers): array
    {
        $normalized = $this->normalizeTiers($tiers);
        $this->assertCompleteTimeTable($normalized);
        $this->assertPercentRanges($normalized);

        return $normalized;
    }

    public function validateVenueTiers(array $venueTiers, array $systemTiers): array
    {
        $normalizedSystem = $this->normalizeTiers($systemTiers);
        $normalizedVenue = $this->normalizeTiers($venueTiers, $normalizedSystem);

        $this->assertCompleteTimeTable($normalizedVenue);
        $this->assertPercentRanges($normalizedVenue);

        $systemByKey = collect($normalizedSystem)->keyBy('key');
        $errors = [];

        foreach ($normalizedVenue as $index => $venueTier) {
            $systemTier = $systemByKey->get($venueTier['key']);
            if (! $systemTier) {
                continue;
            }

            if ((float) $venueTier['refund_percent'] < (float) $systemTier['refund_percent']) {
                $errors["tiers.{$index}.refund_percent"] = "Mức hoàn không được thấp hơn {$systemTier['refund_percent']}% theo chính sách hệ thống ở mốc {$venueTier['label']}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedVenue;
    }

    public function cancellationSummary(array $tiers): string
    {
        return collect($this->normalizeCancellationTiers($tiers))
            ->map(fn (array $tier): string => $tier['business_sentence'])
            ->implode(' ');
    }

    public function summary(array $tiers): string
    {
        return collect($this->normalizeTiers($tiers))
            ->map(fn (array $tier): string => $tier['business_sentence'])
            ->implode(' ');
    }

    public function cancellationPayload(array $systemTiers, ?array $venueTiers = null): array
    {
        $system = $this->normalizeCancellationTiers($systemTiers);
        $venue = $venueTiers ? $this->normalizeCancellationTiers($venueTiers, $system) : null;

        return [
            'system_tiers' => $system,
            'venue_tiers' => $venue,
            'system_summary' => $this->cancellationSummary($system),
            'venue_summary' => $venue ? $this->cancellationSummary($venue) : 'Sân đang dùng mặc định hệ thống.',
            'limits' => $this->cancellationLimits($system),
        ];
    }

    public function payload(array $systemTiers, ?array $venueTiers = null): array
    {
        $system = $this->normalizeTiers($systemTiers);
        $venue = $venueTiers ? $this->normalizeTiers($venueTiers, $system) : null;

        return [
            'system_tiers' => $system,
            'venue_tiers' => $venue,
            'system_summary' => $this->summary($system),
            'venue_summary' => $venue ? $this->summary($venue) : 'Sân đang dùng mặc định hệ thống.',
            'limits' => $this->refundLimits($system),
        ];
    }

    public function evaluateBookingCancellation(Booking $booking, ?User $actor = null, ?Carbon $cancelAt = null): array
    {
        $cancelAt ??= now();
        $startAt = Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . substr((string) $booking->start_time, 0, 5));
        $hoursBefore = $cancelAt->diffInMinutes($startAt, false) / 60;

        $cancellationPolicy = $this->activePolicy('booking_cancellation', self::CANCELLATION_RULE_TYPE);
        $cancellationRule = $cancellationPolicy?->rules->first();
        $systemCancellationTiers = $this->cancellationTiersFromRule($cancellationRule);
        $venueCancellationRule = $cancellationRule ? $this->activeVenueRule($booking, $cancellationRule) : null;
        $effectiveCancellationTiers = $this->cancellationTiersFromVenueRule($venueCancellationRule, $systemCancellationTiers);
        $matchedCancellationTier = $this->matchTier($effectiveCancellationTiers, $hoursBefore);
        $allowCancel = (bool) ($matchedCancellationTier['allow_cancel'] ?? false);

        $refundPolicy = $this->activePolicy('refund', self::REFUND_RULE_TYPE);
        $refundRule = $refundPolicy?->rules->first();
        $systemRefundTiers = $this->tiersFromRule($refundRule);
        $venueRefundRule = $refundRule ? $this->activeVenueRule($booking, $refundRule) : null;
        $effectiveRefundTiers = $this->tiersFromVenueRule($venueRefundRule, $systemRefundTiers);
        $matchedRefundTier = $this->matchTier($effectiveRefundTiers, $hoursBefore);
        $refundPercent = $allowCancel ? (float) ($matchedRefundTier['refund_percent'] ?? 0) : 0.0;

        $result = [
            'hours_before' => round($hoursBefore, 2),
            'allow_cancel' => $allowCancel,
            'cancellation_tier' => $matchedCancellationTier,
            'refund_tier' => $matchedRefundTier,
            'refund_percent' => $refundPercent,
            'refund_amount' => round(((float) $booking->total_price) * ($refundPercent / 100), 2),
            'requires_owner_confirm' => (bool) ($refundRule?->result_json['requires_owner_confirm'] ?? true),
            'requires_admin_confirm' => (bool) ($refundRule?->result_json['requires_admin_confirm'] ?? true),
            'summary' => $this->evaluationSummary($matchedCancellationTier, $matchedRefundTier, $allowCancel, $refundPercent),
        ];

        $this->logEvaluation($booking, $actor, $cancelAt, $result, $cancellationPolicy, $cancellationRule, $venueCancellationRule);
        $this->logEvaluation($booking, $actor, $cancelAt, $result, $refundPolicy, $refundRule, $venueRefundRule);

        return $result;
    }

    public function matchTier(array $tiers, float $hoursBefore): ?array
    {
        foreach ($tiers as $tier) {
            $from = $tier['from_hours'] === null ? null : (float) $tier['from_hours'];
            $to = $tier['to_hours'] === null ? null : (float) $tier['to_hours'];

            if (($from === null || $hoursBefore >= $from) && ($to === null || $hoursBefore < $to)) {
                return $tier;
            }
        }

        return null;
    }

    public function cancellationResultJson(array $tiers, array $extra = []): array
    {
        $normalized = $this->validateSystemCancellationTiers($tiers);

        return [
            ...$extra,
            'tiers' => $normalized,
            'summary_vi' => $this->cancellationSummary($normalized),
        ];
    }

    public function resultJson(array $tiers, array $extra = []): array
    {
        $normalized = $this->validateSystemTiers($tiers);
        $firstTier = $normalized[0] ?? null;

        return [
            ...$extra,
            'tiers' => $normalized,
            'refund_percent' => $firstTier['refund_percent'] ?? null,
            'requires_owner_confirm' => $extra['requires_owner_confirm'] ?? true,
            'requires_admin_confirm' => $extra['requires_admin_confirm'] ?? true,
            'summary_vi' => $this->summary($normalized),
        ];
    }

    private function activePolicy(string $key, string $ruleType): ?SystemPolicy
    {
        return SystemPolicy::query()
            ->where('key', $key)
            ->where('status', 'active')
            ->where('is_active', true)
            ->with(['rules' => fn ($query) => $query->where('rule_type', $ruleType)->where('is_active', true)->orderByDesc('priority')])
            ->orderByDesc('version')
            ->first();
    }

    private function activeVenueRule(Booking $booking, PolicyRule $rule): ?VenuePolicyRule
    {
        return VenuePolicyRule::query()
            ->where('venue_cluster_id', $booking->venue_cluster_id)
            ->where('base_policy_rule_id', $rule->id)
            ->where('status', 'active')
            ->latest('effective_from')
            ->first();
    }

    private function logEvaluation(
        Booking $booking,
        ?User $actor,
        Carbon $cancelAt,
        array $result,
        ?SystemPolicy $policy,
        ?PolicyRule $rule,
        ?VenuePolicyRule $venueRule
    ): void {
        if (! Schema::hasTable('policy_evaluation_logs') || ! $policy) {
            return;
        }

        PolicyEvaluationLog::query()->create([
            'system_policy_id' => $policy->id,
            'policy_rule_id' => $rule?->id,
            'venue_policy_rule_id' => $venueRule?->id,
            'action_code' => $rule?->action_code ?: 'booking.cancel_by_customer',
            'entity_type' => 'bookings',
            'entity_id' => $booking->id,
            'input_data' => [
                'booking_date' => $booking->booking_date,
                'start_time' => $booking->start_time,
                'cancel_at' => $cancelAt->toIso8601String(),
                'hours_before' => $result['hours_before'],
            ],
            'result_data' => $result,
            'policy_version_snapshot' => $policy->only(['id', 'key', 'version', 'title', 'policy_type']),
            'rule_snapshot' => $rule?->only(['id', 'rule_code', 'rule_name', 'rule_type', 'result_json']),
            'evaluated_by_type' => $actor ? 'user' : 'system',
            'evaluated_by_id' => $actor?->id,
        ]);
    }

    private function assertCompleteTimeTable(array $tiers): void
    {
        $expected = collect($this->defaultCancellationTiers())->map(fn (array $tier): array => [
            'key' => $tier['key'],
            'from_hours' => $tier['from_hours'],
            'to_hours' => $tier['to_hours'],
        ])->values()->all();

        $actual = collect($tiers)->map(fn (array $tier): array => [
            'key' => $tier['key'] ?? null,
            'from_hours' => $tier['from_hours'] ?? null,
            'to_hours' => $tier['to_hours'] ?? null,
        ])->values()->all();

        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                'tiers' => 'Bảng mốc phải đủ 4 khoảng: từ 24 giờ trở lên, 6 đến dưới 24 giờ, 1 đến dưới 6 giờ, và dưới 1 giờ; không được chồng hoặc hở khoảng.',
            ]);
        }
    }

    private function assertPercentRanges(array $tiers): void
    {
        $errors = [];
        foreach ($tiers as $index => $tier) {
            $percent = $tier['refund_percent'] ?? null;
            if (! is_numeric($percent) || (float) $percent < 0 || (float) $percent > 100) {
                $errors["tiers.{$index}.refund_percent"] = 'Phần trăm hoàn tiền phải nằm trong khoảng 0 đến 100.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function cancellationLimits(array $systemTiers): array
    {
        return collect($this->normalizeCancellationTiers($systemTiers))
            ->map(fn (array $tier): array => [
                'key' => $tier['key'],
                'label' => $tier['label'],
                'allow_cancel' => (bool) $tier['allow_cancel'],
                'summary' => (bool) $tier['allow_cancel']
                    ? "Mốc {$tier['label']}: sân không được chặn hủy vì hệ thống đang cho phép hủy."
                    : "Mốc {$tier['label']}: sân không được mở hủy vì hệ thống đang không cho hủy.",
            ])
            ->values()
            ->all();
    }

    private function refundLimits(array $systemTiers): array
    {
        return collect($this->normalizeTiers($systemTiers))
            ->map(fn (array $tier): array => [
                'key' => $tier['key'],
                'label' => $tier['label'],
                'min_allowed_refund_percent' => (float) $tier['refund_percent'],
                'summary' => "Mốc {$tier['label']}: mức hoàn không được thấp hơn {$tier['refund_percent']}%.",
            ])
            ->values()
            ->all();
    }

    private function tierConditionLabel(array $tier): string
    {
        return match ($tier['key'] ?? $this->resolveKey($tier)) {
            'from_24' => 'Khách hủy trước giờ chơi từ 24 giờ trở lên',
            'from_6_to_24' => 'Khách hủy từ 6 đến dưới 24 giờ trước giờ chơi',
            'from_1_to_6' => 'Khách hủy từ 1 đến dưới 6 giờ trước giờ chơi',
            'under_1' => 'Khách hủy dưới 1 giờ trước giờ chơi',
            default => 'Mốc thời gian hủy booking',
        };
    }

    private function cancellationSentence(array $tier, bool $allowCancel): string
    {
        return $this->tierConditionLabel($tier) . ': ' . ($allowCancel ? 'cho hủy.' : 'không cho hủy.');
    }

    private function refundSentence(array $tier, float $refundPercent): string
    {
        $result = $refundPercent > 0 ? "hoàn {$refundPercent}%." : 'cho hủy nhưng không hoàn.';

        return $this->tierConditionLabel($tier) . ': ' . $result;
    }

    private function tierResultLabel(bool $allowCancel, float $refundPercent): string
    {
        if (! $allowCancel) {
            return 'Không cho hủy';
        }

        return $refundPercent > 0 ? "Hoàn {$refundPercent}%" : 'Cho hủy nhưng không hoàn';
    }

    private function evaluationSummary(?array $cancellationTier, ?array $refundTier, bool $allowCancel, float $refundPercent): string
    {
        if (! $cancellationTier) {
            return 'Không tìm thấy mốc hủy booking phù hợp.';
        }

        if (! $allowCancel) {
            return "{$cancellationTier['condition_label']}: không cho hủy.";
        }

        $refundText = $refundPercent > 0 ? "hoàn {$refundPercent}%" : 'không hoàn';
        $refundLabel = $refundTier['label'] ?? $cancellationTier['label'];

        return "{$cancellationTier['condition_label']}: cho hủy. Mốc hoàn tiền {$refundLabel}: {$refundText}.";
    }

    private function conditionHours(array $condition): ?int
    {
        $value = $condition['hours_before_start'] ?? null;
        if (is_array($value)) {
            $value = $value['gte'] ?? $value['value'] ?? null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function resolveKey(array $tier): string
    {
        $from = $tier['from_hours'] ?? null;
        $to = $tier['to_hours'] ?? null;

        return match (true) {
            (int) $from === 24 && $to === null => 'from_24',
            (int) $from === 6 && (int) $to === 24 => 'from_6_to_24',
            (int) $from === 1 && (int) $to === 6 => 'from_1_to_6',
            ($from === null || $from === '') && (int) $to === 1 => 'under_1',
            default => (string) ($tier['key'] ?? 'unknown'),
        };
    }
}
