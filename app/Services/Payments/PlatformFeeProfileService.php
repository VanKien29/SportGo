<?php

namespace App\Services\Payments;

use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use App\Models\VenuePlatformFeeProfile;
use Carbon\CarbonImmutable;

class PlatformFeeProfileService
{
    public function ensureProfile(VenueCluster $cluster, ?CarbonImmutable $now = null): VenuePlatformFeeProfile
    {
        $now ??= CarbonImmutable::now(config('platform_fee.timezone'));
        $existing = VenuePlatformFeeProfile::query()->where('venue_cluster_id', $cluster->id)->first();
        if ($existing) {
            return $this->refreshTrialStatus($existing, $now);
        }

        $firstLedger = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->orderBy('period_start')
            ->first();
        $plan = app(PlatformFeePricingService::class)->planFor($now->startOfDay());
        $trialDays = (int) ($plan?->trial_days ?? 30);
        $activationCandidate = CarbonImmutable::instance($cluster->updated_at ?: $cluster->created_at ?: $now);
        $isLegacyCluster = $firstLedger !== null || $activationCandidate->lt($now->subDays($trialDays));

        if ($isLegacyCluster) {
            return VenuePlatformFeeProfile::query()->create([
                'venue_cluster_id' => $cluster->id,
                'trial_plan_version_id' => $plan?->id,
                'trial_status' => 'legacy_not_applicable',
                'trial_days' => $trialDays,
                'fee_started_at' => $firstLedger?->period_start ?: $now->startOfMonth(),
                'billing_anchor_day' => 1,
                'auto_pay_from_balance' => false,
                'metadata' => ['source' => 'automatic_legacy_profile'],
            ]);
        }

        $trialStart = $activationCandidate;
        $trialEnd = $trialStart->addDays($trialDays)->subSecond();

        return VenuePlatformFeeProfile::query()->create([
            'venue_cluster_id' => $cluster->id,
            'trial_plan_version_id' => $plan?->id,
            'trial_status' => $trialEnd->isFuture() ? 'active' : 'expired',
            'trial_days' => $trialDays,
            'trial_started_at' => $trialStart,
            'trial_ends_at' => $trialEnd,
            'fee_started_at' => $trialEnd->addSecond(),
            'billing_anchor_day' => 1,
            'auto_pay_from_balance' => false,
            'metadata' => ['source' => 'automatic_cluster_activation'],
        ]);
    }

    public function extendTrial(VenuePlatformFeeProfile $profile, int $totalTrialDays, ?string $reason = null): VenuePlatformFeeProfile
    {
        if (! in_array($profile->trial_status, ['eligible', 'active'], true) || ! $profile->trial_started_at) {
            throw new \RuntimeException('Chỉ được gia hạn khi cụm sân vẫn đang trong thời gian dùng thử.');
        }
        if ($totalTrialDays < (int) $profile->trial_days) {
            throw new \RuntimeException('Không được rút ngắn thời gian dùng thử đã cấp.');
        }

        $metadata = $profile->metadata ?: [];
        $metadata['trial_extensions'][] = [
            'old_days' => (int) $profile->trial_days,
            'new_days' => $totalTrialDays,
            'old_end' => $profile->trial_ends_at?->toIso8601String(),
            'reason' => $reason,
            'changed_at' => now()->toIso8601String(),
        ];
        $trialEnd = $profile->trial_started_at->copy()->addDays($totalTrialDays)->subSecond();
        $profile->forceFill([
            'trial_days' => $totalTrialDays,
            'trial_ends_at' => $trialEnd,
            'fee_started_at' => $trialEnd->copy()->addSecond(),
            'trial_status' => $trialEnd->isFuture() ? 'active' : 'expired',
            'metadata' => $metadata,
        ])->save();

        return $profile->fresh();
    }

    private function refreshTrialStatus(VenuePlatformFeeProfile $profile, CarbonImmutable $now): VenuePlatformFeeProfile
    {
        if ($profile->trial_status === 'active' && $profile->trial_ends_at && $profile->trial_ends_at->lt($now)) {
            $profile->forceFill(['trial_status' => 'expired'])->save();
        }

        return $profile->fresh();
    }
}
