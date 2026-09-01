<?php

namespace App\Services\Policies;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PolicyEvaluationLog;
use App\Models\PolicyRule;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\SlotLock;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\VenuePolicyRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RefundCancellationPolicyService
{
    public const CANCELLATION_RULE_TYPE = 'cancel_before_hours';
    public const REFUND_RULE_TYPE = 'refund_percent_by_cancel_time';
    public const RULE_TYPE = self::REFUND_RULE_TYPE;
    private const FULL_REFUND_REASON_TYPES = ['owner_maintenance', 'owner_emergency', 'venue_locked'];

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

    public function defaultCancelRefundTiers(): array
    {
        return [
            [
                'key' => 'from_24',
                'label' => 'Từ 24 giờ trở lên',
                'from_hours' => 24,
                'to_hours' => null,
                'allow_cancel' => true,
                'refund_percent' => 100,
                'require_owner_confirm' => true,
                'require_admin_confirm' => false,
                'customer_message' => 'Bạn có thể hủy booking và được hoàn 100% số tiền đã thanh toán.',
            ],
            [
                'key' => 'from_6_to_24',
                'label' => 'Từ 6 đến dưới 24 giờ',
                'from_hours' => 6,
                'to_hours' => 24,
                'allow_cancel' => true,
                'refund_percent' => 80,
                'require_owner_confirm' => true,
                'require_admin_confirm' => false,
                'customer_message' => 'Bạn có thể hủy booking và được hoàn 80% số tiền đã thanh toán.',
            ],
            [
                'key' => 'from_1_to_6',
                'label' => 'Từ 1 đến dưới 6 giờ',
                'from_hours' => 1,
                'to_hours' => 6,
                'allow_cancel' => true,
                'refund_percent' => 50,
                'require_owner_confirm' => true,
                'require_admin_confirm' => false,
                'customer_message' => 'Bạn có thể hủy booking và được hoàn 50% số tiền đã thanh toán.',
            ],
            [
                'key' => 'under_1',
                'label' => 'Dưới 1 giờ',
                'from_hours' => 0,
                'to_hours' => 1,
                'allow_cancel' => true,
                'refund_percent' => 0,
                'require_owner_confirm' => true,
                'require_admin_confirm' => false,
                'customer_message' => 'Bạn có thể hủy booking nhưng không được hoàn tiền.',
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

    public function cancelRefundTiersFromRule(?PolicyRule $rule): array
    {
        if (! $rule) {
            return $this->normalizeCancelRefundTiers($this->defaultCancelRefundTiers());
        }

        $result = $rule->result_json ?: [];
        if (isset($result['cancel_refund_tiers']) && is_array($result['cancel_refund_tiers'])) {
            return $this->normalizeCancelRefundTiers($result['cancel_refund_tiers']);
        }

        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeCancelRefundTiers($result['tiers']);
        }

        return $this->normalizeCancelRefundTiers($this->defaultCancelRefundTiers());
    }

    public function cancelRefundTiersFromVenueRule(?VenuePolicyRule $venueRule, array $systemTiers): array
    {
        if (! $venueRule) {
            return $this->normalizeCancelRefundTiers($systemTiers);
        }

        $result = $venueRule->result_json ?: [];
        if (isset($result['cancel_refund_tiers']) && is_array($result['cancel_refund_tiers'])) {
            return $this->normalizeCancelRefundTiers($result['cancel_refund_tiers'], $systemTiers);
        }

        if (isset($result['tiers']) && is_array($result['tiers'])) {
            return $this->normalizeCancelRefundTiers($result['tiers'], $systemTiers);
        }

        return $this->normalizeCancelRefundTiers($systemTiers);
    }

    public function normalizeCancelRefundTiers(array $tiers, ?array $fallback = null): array
    {
        $fallbackByKey = collect($fallback ?: $this->defaultCancelRefundTiers())
            ->keyBy(fn (array $tier): string => (string) ($tier['key'] ?? $this->rangeKey($tier)));
        $normalized = collect($tiers)
            ->map(function (array $tier, int $index) use ($fallbackByKey): array {
                $key = (string) ($tier['key'] ?? $this->rangeKey($tier) ?: 'tier_' . $index);
                $fallback = $fallbackByKey->get($key, $tier);
                $input = $tier;
                $from = $this->nullableFloat($input['from_hours'] ?? $fallback['from_hours'] ?? 0);
                $to = $this->nullableFloat($input['to_hours'] ?? $fallback['to_hours'] ?? null);
                $allowCancel = array_key_exists('allow_cancel', $input)
                    ? (bool) $input['allow_cancel']
                    : (bool) ($fallback['allow_cancel'] ?? true);
                $refundPercent = $allowCancel
                    ? round((float) ($input['refund_percent'] ?? $fallback['refund_percent'] ?? 0), 2)
                    : 0.0;

                $shape = [
                    'key' => $key,
                    'label' => trim((string) ($input['label'] ?? $fallback['label'] ?? $this->rangeLabel($from, $to))),
                    'from_hours' => $from,
                    'to_hours' => $to,
                    'allow_cancel' => $allowCancel,
                    'refund_percent' => $refundPercent,
                    'require_owner_confirm' => true,
                    // Admin monitors the outcome; it is not a confirmation step.
                    'require_admin_confirm' => false,
                    'customer_message' => trim((string) ($input['customer_message'] ?? $fallback['customer_message'] ?? '')),
                ];

                return [
                    ...$shape,
                    'condition_label' => $this->rangeConditionLabel($shape),
                    'result_label' => $this->cancelRefundResultLabel($shape),
                    'business_sentence' => $this->cancelRefundSentence($shape),
                ];
            })
            ->sortByDesc(fn (array $tier): float => (float) $tier['from_hours'])
            ->values()
            ->all();

        return $normalized;
    }

    public function validateSystemCancelRefundTiers(array $tiers): array
    {
        $normalized = $this->normalizeCancelRefundTiers($tiers);
        $this->assertBusinessTimeTable($normalized);
        $this->assertCancelRefundTierValues($normalized);

        return $normalized;
    }

    public function validateVenueCancelRefundTiers(array $venueTiers, array $systemTiers): array
    {
        $normalizedSystem = $this->validateSystemCancelRefundTiers($systemTiers);
        $normalizedVenue = $this->normalizeCancelRefundTiers($venueTiers, $normalizedSystem);
        $this->assertBusinessTimeTable($normalizedVenue);
        $this->assertCancelRefundTierValues($normalizedVenue);

        $errors = [];
        foreach ($normalizedVenue as $index => $venueTier) {
            $overlappingSystemTiers = collect($normalizedSystem)
                ->filter(fn (array $systemTier): bool => $this->timeRangesOverlap($venueTier, $systemTier))
                ->values();

            if ($overlappingSystemTiers->isEmpty()) {
                $errors["tiers.{$index}.from_hours"] = "Mốc {$venueTier['label']} không nằm trong khung thời gian của hệ thống.";
                continue;
            }

            $allowCancelValues = $overlappingSystemTiers
                ->map(fn (array $systemTier): bool => (bool) ($systemTier['allow_cancel'] ?? true))
                ->unique();

            if ($allowCancelValues->count() > 1) {
                $errors["tiers.{$index}.to_hours"] = "Mốc {$venueTier['label']} cắt qua các khoảng có quyền hủy khác nhau của hệ thống. Hãy tách mốc tại ranh giới hệ thống.";
            } else {
                $systemAllowsCancel = (bool) $allowCancelValues->first();
                if ($systemAllowsCancel !== (bool) ($venueTier['allow_cancel'] ?? true)) {
                    $errors["tiers.{$index}.allow_cancel"] = $systemAllowsCancel
                        ? "Mốc {$venueTier['label']}: sân không được chặn hủy khi chính sách hệ thống đang cho phép hủy."
                        : "Mốc {$venueTier['label']}: sân không được cho hủy khi chính sách hệ thống không cho phép hủy.";
                }
            }

            $minimumRefundPercent = (float) $overlappingSystemTiers->max(
                fn (array $systemTier): float => (float) ($systemTier['refund_percent'] ?? 0)
            );
            if ((float) $venueTier['refund_percent'] < $minimumRefundPercent) {
                $errors["tiers.{$index}.refund_percent"] = "Mốc {$venueTier['label']}: mức hoàn của sân không được thấp hơn {$this->formatNumber($minimumRefundPercent)}% theo các khoảng hệ thống mà mốc này đi qua.";
            }

            $requiresOwnerConfirm = $overlappingSystemTiers->contains(
                fn (array $systemTier): bool => (bool) ($systemTier['require_owner_confirm'] ?? false)
            );
            if ($requiresOwnerConfirm && ! ($venueTier['require_owner_confirm'] ?? false)) {
                $errors["tiers.{$index}.require_owner_confirm"] = "Mốc {$venueTier['label']}: sân không được bỏ bước chủ sân xác nhận hoàn tiền.";
            }

        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalizedVenue;
    }

    public function cancelRefundPayload(array $systemTiers, ?array $venueTiers = null): array
    {
        $system = $this->normalizeCancelRefundTiers($systemTiers);
        $venue = $venueTiers ? $this->normalizeCancelRefundTiers($venueTiers, $system) : null;

        return [
            'system_tiers' => $system,
            'venue_tiers' => $venue,
            'system_summary' => $this->cancelRefundSummary($system),
            'venue_summary' => $venue ? $this->cancelRefundSummary($venue) : 'Sân đang dùng mặc định hệ thống.',
            'limits' => $this->cancelRefundLimits($system),
            'venue_can_change_time_ranges' => true,
        ];
    }

    public function cancelRefundSummary(array $tiers): string
    {
        return collect($this->normalizeCancelRefundTiers($tiers))
            ->map(fn (array $tier): string => $tier['business_sentence'])
            ->implode(' ');
    }

    public function cancelRefundResultJson(array $tiers, array $extra = []): array
    {
        $normalized = $this->validateSystemCancelRefundTiers($tiers);

        return [
            ...$extra,
            'cancel_refund_tiers' => $normalized,
            'tiers' => $normalized,
            'requires_owner_confirm' => true,
            'requires_admin_confirm' => false,
            'summary_vi' => $this->cancelRefundSummary($normalized),
        ];
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
        $businessTimezone = (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh');
        $cancelAt = ($cancelAt ?: now($businessTimezone))->copy()->setTimezone($businessTimezone);
        $bookingDate = $booking->booking_date instanceof Carbon
            ? $booking->booking_date->format('Y-m-d')
            : Carbon::parse((string) $booking->booking_date, $businessTimezone)->format('Y-m-d');
        $startAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $bookingDate . ' ' . substr((string) $booking->start_time, 0, 5),
            $businessTimezone,
        );
        $hoursBefore = $cancelAt->diffInMinutes($startAt, false) / 60;

        if ($this->requiresFullRefundByCancellationReason($booking)) {
            $paidAmount = $this->paidAmount($booking);
            $result = [
                'hours_before' => round($hoursBefore, 2),
                'allow_cancel' => true,
                'cancellation_tier' => [
                    'key' => $booking->cancellation_reason_type,
                    'label' => 'Full refund by cancellation reason',
                ],
                'refund_tier' => null,
                'refund_percent' => 100.0,
                'paid_amount' => $paidAmount,
                'refund_amount' => $booking->payment_option === 'no_prepay' ? 0.0 : round($paidAmount, 2),
                'requires_owner_confirm' => false,
                'requires_admin_confirm' => false,
                'customer_message' => 'Booking duoc hoan 100% do chu san/he thong huy vi bao tri, su co hoac khoa san.',
                'summary' => 'Hoan 100% do ly do huy thuoc nhom chu san/bao tri/khoa san.',
            ];

            $this->logEvaluation($booking, $actor, $cancelAt, $result, null, null, null);

            return $result;
        }

        $cancellationPolicy = $this->activePolicy('booking_cancellation', self::CANCELLATION_RULE_TYPE);
        $cancellationRule = $cancellationPolicy?->rules->first();
        $systemCombinedTiers = $this->cancelRefundTiersFromRule($cancellationRule);
        $venueCancellationRule = $cancellationRule ? $this->activeVenueRule($booking, $cancellationRule) : null;
        $effectiveCombinedTiers = $this->cancelRefundTiersFromVenueRule($venueCancellationRule, $systemCombinedTiers);
        $matchedCombinedTier = $this->matchTier($effectiveCombinedTiers, $hoursBefore);

        $refundPolicy = $this->activePolicy('refund', self::REFUND_RULE_TYPE);
        $refundRule = $refundPolicy?->rules->first();
        $systemRefundTiers = $this->tiersFromRule($refundRule);
        $venueRefundRule = $refundRule ? $this->activeVenueRule($booking, $refundRule) : null;
        $effectiveRefundTiers = $this->tiersFromVenueRule($venueRefundRule, $systemRefundTiers);
        $matchedRefundTier = $this->matchTier($effectiveRefundTiers, $hoursBefore);

        $allowCancel = (bool) ($matchedCombinedTier['allow_cancel'] ?? false);
        $refundPercent = $allowCancel
            ? (float) ($matchedCombinedTier['refund_percent'] ?? $matchedRefundTier['refund_percent'] ?? 0)
            : 0.0;
        $paidAmount = $this->paidAmount($booking);
        $refundAmount = $booking->payment_option === 'no_prepay'
            ? 0.0
            : round($paidAmount * ($refundPercent / 100), 2);

        $result = [
            'hours_before' => round($hoursBefore, 2),
            'allow_cancel' => $allowCancel,
            'cancellation_tier' => $matchedCombinedTier,
            'refund_tier' => $matchedRefundTier,
            'refund_percent' => $refundPercent,
            'paid_amount' => $paidAmount,
            'refund_amount' => $refundAmount,
            'requires_owner_confirm' => (bool) ($matchedCombinedTier['require_owner_confirm'] ?? $refundRule?->result_json['requires_owner_confirm'] ?? true),
            'requires_admin_confirm' => false,
            'customer_message' => $matchedCombinedTier['customer_message'] ?? null,
            'summary' => $this->evaluationSummary($matchedCombinedTier, $matchedRefundTier, $allowCancel, $refundPercent),
        ];

        $this->logEvaluation($booking, $actor, $cancelAt, $result, $cancellationPolicy, $cancellationRule, $venueCancellationRule);
        $this->logEvaluation($booking, $actor, $cancelAt, $result, $refundPolicy, $refundRule, $venueRefundRule);

        return $result;
    }

    public function cancelBooking(Booking $booking, User $actor, ?Carbon $cancelAt = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($booking, $actor, $cancelAt, $reason): array {
            $booking = Booking::query()
                ->with('payments')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->customer_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'booking' => 'Bạn không có quyền hủy booking này.',
                ]);
            }

            if (in_array($booking->status, ['checked_in', 'completed', 'cancelled', 'expired', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking này không còn ở trạng thái có thể hủy.',
                ]);
            }

            $result = $this->evaluateBookingCancellation($booking, $actor, $cancelAt);

            if (! ($result['allow_cancel'] ?? false)) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking này không được hủy vì đã quá thời hạn hủy theo chính sách.',
                ]);
            }

            $oldBooking = $booking->toArray();
            $booking->forceFill([
                'status' => 'cancelled',
                'status_reason' => $reason ?: ($result['customer_message'] ?: 'Khách hủy booking theo chính sách.'),
                'cancelled_by' => $actor->id,
                'cancellation_initiator' => 'customer',
                'cancellation_reason_type' => 'customer_request',
                'cancelled_at' => now(),
            ])->save();

            SlotLock::query()->where('booking_id', $booking->id)->delete();

            $refunds = $this->createRefundRequests($booking, $result, $actor, $reason);
            $this->auditCancellation($booking->fresh(), $actor, $oldBooking, $result, $refunds, $reason);

            return [
                'booking' => $booking->fresh(['venueCourt.venueCluster', 'venueCourt.courtType', 'payments']),
                'policy_result' => $result,
                'refunds' => $refunds,
            ];
        });
    }

    public function createRefundsForProviderCancellation(Booking $booking, User $actor, ?string $reason = null): array
    {
        return DB::transaction(function () use ($booking, $actor, $reason): array {
            $booking = Booking::query()
                ->with('payments')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->requiresFullRefundByCancellationReason($booking)) {
                return [];
            }

            if (! in_array($booking->status, ['cancelled', 'rejected'], true)) {
                return [];
            }

            if (Refund::query()->where('booking_id', $booking->id)->exists()) {
                return Refund::query()
                    ->where('booking_id', $booking->id)
                    ->latest()
                    ->get()
                    ->map(fn (Refund $refund): array => $refund->toArray())
                    ->all();
            }

            $result = $this->evaluateBookingCancellation($booking, $actor, $booking->cancelled_at ?: now());

            return $this->createRefundRequests($booking, $result, $actor, $reason);
        });
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

    /**
     * Tạo yêu cầu hoàn tiền do khách chủ động gửi từ một booking đã hủy/từ chối.
     * Luồng này dùng cùng evaluator với hủy booking và bước duyệt của chủ sân.
     */
    public function requestCustomerRefund(
        Booking $booking,
        User $actor,
        ?float $requestedAmount,
        string $destination,
        string $reason,
    ): array {
        return DB::transaction(function () use ($booking, $actor, $requestedAmount, $destination, $reason): array {
            $booking = Booking::query()
                ->with(['payments', 'venueCluster'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $booking->customer_id !== (string) $actor->id) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Bạn không có quyền tạo yêu cầu hoàn cho booking này.',
                ]);
            }

            if (! in_array($booking->status, ['cancelled', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Chỉ booking đã hủy hoặc bị từ chối mới có thể tạo yêu cầu hoàn tiền.',
                ]);
            }

            $blockingStatuses = [
                'pending_owner_confirmation',
                'completed',
                'completed_cash',
            ];
            if (Refund::query()
                ->where('booking_id', $booking->id)
                ->whereIn('status', $blockingStatuses)
                ->exists()) {
                throw ValidationException::withMessages([
                    'refund' => 'Booking này đã có một yêu cầu hoàn tiền đang được xử lý hoặc đã hoàn tất.',
                ]);
            }

            $result = $this->evaluateBookingCancellation(
                $booking,
                $actor,
                $booking->cancelled_at ?: now(),
            );
            $maximumAmount = round((float) ($result['refund_amount'] ?? 0), 2);

            if ($maximumAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Booking này không có khoản tiền đã thanh toán đủ điều kiện hoàn theo chính sách hiện tại.',
                ]);
            }

            $amount = $requestedAmount === null ? $maximumAmount : round($requestedAmount, 2);
            if ($amount <= 0 || $amount > $maximumAmount + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Số tiền yêu cầu phải lớn hơn 0 và không vượt quá %sđ theo chính sách.',
                        number_format($maximumAmount, 0, ',', '.'),
                    ),
                ]);
            }

            $payments = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('status', 'paid')
                ->orderBy('paid_at')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Booking chưa có khoản thanh toán thành công để tạo yêu cầu hoàn tiền.',
                ]);
            }

            $status = 'pending_owner_confirmation';
            $remaining = $amount;
            $created = [];
            $refundPercent = (float) ($result['refund_percent'] ?? 0);

            foreach ($payments as $payment) {
                if ($remaining <= 0.01) {
                    break;
                }

                $paymentMaximum = round((float) $payment->amount * ($refundPercent / 100), 2);
                $paymentAmount = min($paymentMaximum, $remaining);
                if ($paymentAmount <= 0) {
                    continue;
                }

                $created[] = $this->createRefundRow(
                    $booking,
                    $payment,
                    round($paymentAmount, 2),
                    $destination,
                    $status,
                    $result,
                    $actor,
                    $reason,
                );
                $remaining = round($remaining - $paymentAmount, 2);
            }

            if ($remaining > 0.01 || empty($created)) {
                throw ValidationException::withMessages([
                    'amount' => 'Không thể phân bổ số tiền yêu cầu vào các khoản thanh toán của booking.',
                ]);
            }

            if (Schema::hasTable('notifications') && $booking->venueCluster?->owner_id) {
                $firstRefund = collect($created)->first();
                Notification::query()->create([
                    'user_id' => $booking->venueCluster->owner_id,
                    'type' => 'refund_customer_requested',
                    'title' => 'Có yêu cầu hoàn tiền mới',
                    'body' => sprintf(
                        'Khách hàng đã gửi yêu cầu hoàn %s cho booking %s. Vui lòng kiểm tra và xác nhận.',
                        number_format($amount, 0, ',', '.').'đ',
                        $booking->booking_code,
                    ),
                    'reference_type' => 'refund',
                    'reference_id' => (string) ($firstRefund?->id),
                    'data' => [
                        'booking_id' => $booking->id,
                        'booking_code' => $booking->booking_code,
                        'amount' => $amount,
                        'status' => $status,
                    ],
                ]);
            }

            return [
                'booking' => $booking->fresh(['venueCluster', 'venueCourt', 'payments']),
                'policy_result' => $result,
                'refunds' => collect($created)->filter()->map(fn (Refund $refund): array => $refund->fresh()->toArray())->values()->all(),
            ];
        });
    }

    public function cancelBookingItems(Booking $booking, User $actor, array $bookingItemIds, string $reason): array
    {
        return DB::transaction(function () use ($booking, $actor, $bookingItemIds, $reason): array {
            $booking = Booking::query()
                ->with(['items', 'payments', 'venueCluster'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $booking->customer_id !== (string) $actor->id) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Bạn không có quyền thay đổi booking này.',
                ]);
            }

            if (! in_array($booking->status, ['confirmed', 'pending_approval', 'pending_payment'], true)) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Booking hiện không còn khung giờ có thể hủy riêng.',
                ]);
            }

            $activeItems = $booking->items
                ->filter(fn (BookingItem $item): bool => in_array($item->status ?: 'active', ['active', 'moved'], true))
                ->values();
            $items = $activeItems->whereIn('id', $bookingItemIds)->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'booking_item_ids' => 'Không tìm thấy khung giờ còn hoạt động để hủy.',
                ]);
            }

            if ($items->count() >= $activeItems->count()) {
                throw ValidationException::withMessages([
                    'booking_item_ids' => 'Muốn hủy toàn bộ booking, hãy dùng luồng hủy booking để áp dụng đúng chính sách.',
                ]);
            }

            $policy = $this->evaluateBookingCancellation($booking, $actor);
            if (! ($policy['allow_cancel'] ?? false)) {
                throw ValidationException::withMessages([
                    'booking_item_ids' => 'Khung giờ này đã quá thời hạn hủy theo chính sách của sân.',
                ]);
            }

            $cancelledSubtotal = (float) $items->sum(fn (BookingItem $item): float => (float) $item->subtotal);
            $activeSubtotal = max((float) $activeItems->sum(fn (BookingItem $item): float => (float) $item->subtotal), 0.01);
            $refundRatio = min(1, max(0, $cancelledSubtotal / $activeSubtotal));
            $paidAmount = (float) $booking->payments->where('status', 'paid')->sum('amount');
            $refundAmount = $booking->payment_option === 'no_prepay'
                ? 0.0
                : round($paidAmount * ((float) ($policy['refund_percent'] ?? 0) / 100) * $refundRatio, 2);

            BookingItem::query()
                ->whereIn('id', $items->pluck('id')->all())
                ->update([
                    'status' => 'cancelled_by_customer',
                    'status_reason' => $reason,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                ]);

            SlotLock::query()->whereIn('booking_item_id', $items->pluck('id')->all())->delete();

            $remainingItems = $activeItems->reject(fn (BookingItem $item): bool => $items->contains('id', $item->id));
            $remainingSubtotal = max($activeSubtotal - $cancelledSubtotal, 0);
            $remainingDuration = (int) $remainingItems->sum(fn (BookingItem $item): int => (int) $item->duration_minutes);
            $remainingStart = $remainingItems->sortBy('start_time')->first()?->start_time;
            $remainingEnd = $remainingItems->sortByDesc('end_time')->first()?->end_time;

            $booking->forceFill([
                'original_amount' => max((float) ($booking->original_amount ?? $booking->total_price) - $cancelledSubtotal, 0),
                'total_price' => $remainingSubtotal,
                'final_amount' => $remainingSubtotal,
                'required_payment_amount' => min((float) $booking->required_payment_amount, $remainingSubtotal),
                'duration_minutes' => $remainingDuration,
                'start_time' => $remainingStart,
                'end_time' => $remainingEnd,
            ])->save();

            $status = 'pending_owner_confirmation';
            $remainingRefund = $refundAmount;
            $created = [];
            $refundPercent = (float) ($policy['refund_percent'] ?? 0);

            foreach ($booking->payments->where('status', 'paid') as $payment) {
                if ($remainingRefund <= 0.01) {
                    break;
                }

                $paymentMaximum = round((float) $payment->amount * ($refundPercent / 100) * $refundRatio, 2);
                $existingAmount = (float) Refund::query()
                    ->where('payment_id', $payment->id)
                    ->whereNotIn('status', ['owner_rejected'])
                    ->sum('amount');
                $amount = min(max($paymentMaximum - $existingAmount, 0), $remainingRefund);

                if ($amount <= 0.01) {
                    continue;
                }

                $created[] = $this->createRefundRow(
                    $booking,
                    $payment,
                    round($amount, 2),
                    'user_wallet',
                    $status,
                    [...$policy, 'refund_amount' => $refundAmount],
                    $actor,
                    $reason,
                );
                $remainingRefund = round($remainingRefund - $amount, 2);
            }

            return [
                'booking' => $booking->fresh(['venueCluster', 'venueCourt', 'items', 'payments', 'refunds']),
                'policy_result' => [...$policy, 'refund_amount' => $refundAmount, 'refund_ratio' => $refundRatio],
                'refunds' => collect($created)->filter()->map(fn (Refund $refund): array => $refund->fresh()->toArray())->values()->all(),
            ];
        });
    }

    private function requiresFullRefundByCancellationReason(Booking $booking): bool
    {
        return in_array((string) $booking->cancellation_reason_type, self::FULL_REFUND_REASON_TYPES, true);
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
            'requires_admin_confirm' => false,
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

    private function paidAmount(Booking $booking): float
    {
        if ($booking->payment_option === 'no_prepay') {
            return 0.0;
        }

        return (float) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->sum('amount');
    }

    private function createRefundRequests(Booking $booking, array $result, User $actor, ?string $reason): array
    {
        $refundAmount = (float) ($result['refund_amount'] ?? 0);
        if ($refundAmount <= 0 || ! Schema::hasTable('refunds')) {
            return [];
        }

        $created = [];
        $status = 'pending_owner_confirmation';

        $payments = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->orderBy('paid_at')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $paymentRefundAmount = round(((float) $payment->amount) * ((float) ($result['refund_percent'] ?? 0) / 100), 2);
            if ($paymentRefundAmount <= 0) {
                continue;
            }

            $created[] = $this->createRefundRow($booking, $payment, round($paymentRefundAmount, 2), 'user_wallet', $status, $result, $actor, $reason);
        }

        return collect($created)
            ->filter()
            ->map(fn (Refund $refund): array => $refund->fresh()->toArray())
            ->values()
            ->all();
    }

    private function createRefundRow(
        Booking $booking,
        Payment $payment,
        float $amount,
        string $destination,
        string $status,
        array $result,
        User $actor,
        ?string $reason
    ): ?Refund {
        if ($amount <= 0) {
            return null;
        }

        $refund = Refund::query()->create([
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'amount' => $amount,
            'refund_destination' => $destination,
            'user_wallet_id' => $destination === 'user_wallet' ? $payment->user_wallet_id : null,
            'policy_id' => $result['cancellation_tier']['policy_id'] ?? null,
            'reason' => $reason ?: ($result['summary'] ?? 'Khách hủy booking theo chính sách.'),
            'status' => $status,
            'status_reason' => $result['summary'] ?? null,
        ]);

        if (Schema::hasTable('refund_status_histories')) {
            RefundStatusHistory::query()->create([
                'refund_id' => $refund->id,
                'old_status' => null,
                'new_status' => $status,
                'changed_by' => $actor->id,
                'actor_type' => 'user',
                'reason' => $reason ?: 'Tạo yêu cầu hoàn tiền khi khách hủy booking.',
                'metadata' => [
                    'refund_percent' => $result['refund_percent'] ?? null,
                    'paid_amount' => $result['paid_amount'] ?? null,
                    'destination' => $destination,
                ],
                'created_at' => now(),
            ]);
        }

        return $refund;
    }

    private function auditCancellation(Booking $booking, User $actor, array $oldBooking, array $result, array $refunds, ?string $reason): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'module' => 'booking',
            'action' => 'booking.cancelled_by_customer',
            'entity_type' => 'bookings',
            'entity_id' => $booking->id,
            'old_values' => $oldBooking,
            'new_values' => $booking->toArray(),
            'metadata' => [
                'policy_result' => $result,
                'refund_ids' => collect($refunds)->pluck('id')->all(),
            ],
            'reason' => $reason ?: ($result['summary'] ?? null),
            'context' => 'customer',
            'severity' => 'info',
        ]);
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

    private function assertBusinessTimeTable(array $tiers): void
    {
        if (count($tiers) < 2) {
            throw ValidationException::withMessages([
                'tiers' => 'Bảng mốc hủy & hoàn phải có ít nhất 2 mốc thời gian và phủ đủ từ 0 giờ đến vô hạn.',
            ]);
        }

        $ascending = collect($tiers)
            ->sortBy(fn (array $tier): float => (float) ($tier['from_hours'] ?? 0))
            ->values();

        if ((float) ($ascending[0]['from_hours'] ?? -1) !== 0.0) {
            throw ValidationException::withMessages([
                'tiers.0.from_hours' => 'Bảng mốc phải bắt đầu từ 0 giờ để không hở khoảng dưới cùng.',
            ]);
        }

        foreach ($ascending as $index => $tier) {
            $from = $this->nullableFloat($tier['from_hours'] ?? null);
            $to = $this->nullableFloat($tier['to_hours'] ?? null);

            if ($from === null || $from < 0) {
                throw ValidationException::withMessages([
                    "tiers.{$index}.from_hours" => 'Giờ bắt đầu mốc phải lớn hơn hoặc bằng 0.',
                ]);
            }

            if ($to !== null && $to <= $from) {
                throw ValidationException::withMessages([
                    "tiers.{$index}.to_hours" => 'Giờ kết thúc mốc phải lớn hơn giờ bắt đầu.',
                ]);
            }

            $next = $ascending[$index + 1] ?? null;
            if ($next) {
                if ($to === null || (float) $to !== (float) $next['from_hours']) {
                    throw ValidationException::withMessages([
                        "tiers.{$index}.to_hours" => 'Các mốc thời gian phải liền nhau, không được chồng hoặc hở khoảng.',
                    ]);
                }
            } elseif ($to !== null) {
                throw ValidationException::withMessages([
                    "tiers.{$index}.to_hours" => 'Mốc cao nhất phải phủ đến vô hạn giờ trước giờ chơi.',
                ]);
            }
        }
    }

    private function assertCancelRefundTierValues(array $tiers): void
    {
        $errors = [];
        foreach ($tiers as $index => $tier) {
            $percent = $tier['refund_percent'] ?? null;
            if (! is_numeric($percent) || (float) $percent < 0 || (float) $percent > 100) {
                $errors["tiers.{$index}.refund_percent"] = 'Tỷ lệ hoàn phải nằm trong khoảng 0 đến 100%.';
            }

            if (! ($tier['allow_cancel'] ?? true) && (float) ($tier['refund_percent'] ?? 0) !== 0.0) {
                $errors["tiers.{$index}.refund_percent"] = 'Nếu không cho hủy thì tỷ lệ hoàn bắt buộc bằng 0%.';
            }

            if (mb_strlen((string) ($tier['customer_message'] ?? '')) > 500) {
                $errors["tiers.{$index}.customer_message"] = 'Nội dung hiển thị cho khách không được vượt quá 500 ký tự.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
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

    private function cancelRefundLimits(array $systemTiers): array
    {
        return collect($this->normalizeCancelRefundTiers($systemTiers))
            ->map(fn (array $tier): array => [
                'key' => $tier['key'],
                'label' => $tier['label'],
                'from_hours' => $tier['from_hours'],
                'to_hours' => $tier['to_hours'],
                'allow_cancel' => (bool) $tier['allow_cancel'],
                'min_allowed_refund_percent' => (float) $tier['refund_percent'],
                'require_owner_confirm' => (bool) $tier['require_owner_confirm'],
                'require_admin_confirm' => false,
                'summary' => "Trong khoảng {$tier['label']}: chính sách sân không được hoàn thấp hơn {$tier['refund_percent']}%; chủ sân xác nhận, admin chỉ theo dõi.",
            ])
            ->values()
            ->all();
    }

    private function timeRangesOverlap(array $firstTier, array $secondTier): bool
    {
        $firstFrom = $this->nullableFloat($firstTier['from_hours'] ?? null) ?? 0.0;
        $firstTo = $this->nullableFloat($firstTier['to_hours'] ?? null) ?? INF;
        $secondFrom = $this->nullableFloat($secondTier['from_hours'] ?? null) ?? 0.0;
        $secondTo = $this->nullableFloat($secondTier['to_hours'] ?? null) ?? INF;

        return max($firstFrom, $secondFrom) < min($firstTo, $secondTo);
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

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function rangeKey(array $tier): string
    {
        $from = $this->nullableFloat($tier['from_hours'] ?? null) ?? 0.0;
        $to = $this->nullableFloat($tier['to_hours'] ?? null);

        return 'from_' . $this->numberKey($from) . '_to_' . ($to === null ? 'up' : $this->numberKey($to));
    }

    private function rangeLabel(?float $from, ?float $to): string
    {
        $from ??= 0.0;

        if ($from <= 0.0 && $to !== null) {
            return 'Dưới ' . $this->hourText($to);
        }

        if ($to === null) {
            return 'Từ ' . $this->hourText($from) . ' trở lên';
        }

        return 'Từ ' . $this->hourText($from) . ' đến dưới ' . $this->hourText($to);
    }

    private function rangeConditionLabel(array $tier): string
    {
        $from = $this->nullableFloat($tier['from_hours'] ?? null) ?? 0.0;
        $to = $this->nullableFloat($tier['to_hours'] ?? null);

        if ($from <= 0.0 && $to !== null) {
            return 'Khách hủy trước giờ chơi dưới ' . $this->hourText($to);
        }

        if ($to === null) {
            return 'Khách hủy trước giờ chơi từ ' . $this->hourText($from) . ' trở lên';
        }

        return 'Khách hủy trước giờ chơi từ ' . $this->hourText($from) . ' đến dưới ' . $this->hourText($to);
    }

    private function cancelRefundResultLabel(array $tier): string
    {
        if (! (bool) ($tier['allow_cancel'] ?? true)) {
            return 'Không cho hủy';
        }

        $refundPercent = (float) ($tier['refund_percent'] ?? 0);

        return $refundPercent > 0
            ? 'Hoàn ' . $this->formatNumber($refundPercent) . '% trên số tiền đã thanh toán'
            : 'Cho hủy nhưng không hoàn';
    }

    private function cancelRefundSentence(array $tier): string
    {
        return $this->rangeConditionLabel($tier) . ': ' . mb_strtolower($this->cancelRefundResultLabel($tier)) . '.';
    }

    private function hourText(float|int $hours): string
    {
        return $this->formatNumber((float) $hours) . ' giờ';
    }

    private function numberKey(float $value): string
    {
        $normalized = $this->formatNumber($value);

        return str_replace('.', '_', $normalized === '' ? '0' : $normalized);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
