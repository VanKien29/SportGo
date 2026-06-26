<?php

namespace App\Services\Memberships;

use App\Models\Booking;
use App\Models\CourtMembershipTier;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserCourtMembership;
use App\Models\UserCourtMembershipHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueMembershipService
{
    public const DEFAULT_TIERS = [
        ['tier' => 'standard', 'tier_label' => 'Thường', 'tier_order' => 0, 'discount_percent' => 0, 'min_bookings' => 0, 'min_spent_amount' => 0],
        ['tier' => 'silver', 'tier_label' => 'Bạc', 'tier_order' => 1, 'discount_percent' => 3, 'min_bookings' => 5, 'min_spent_amount' => 500000],
        ['tier' => 'gold', 'tier_label' => 'Vàng', 'tier_order' => 2, 'discount_percent' => 5, 'min_bookings' => 15, 'min_spent_amount' => 2000000],
        ['tier' => 'diamond', 'tier_label' => 'Kim cương', 'tier_order' => 3, 'discount_percent' => 8, 'min_bookings' => 30, 'min_spent_amount' => 5000000],
    ];

    public function tierKeys(): array
    {
        return collect(self::DEFAULT_TIERS)->pluck('tier')->all();
    }

    public function settingsPayload(string $venueClusterId): array
    {
        return $this->settingsForCluster($venueClusterId)
            ->map(fn (array $tier): array => $this->tierPayload($tier))
            ->values()
            ->all();
    }

    public function upsertSettings(string $venueClusterId, array $tiers): array
    {
        $inputByKey = collect($tiers)
            ->keyBy(fn (array $tier): string => $this->normalizeTierKey($tier['tier'] ?? $tier['tier_key'] ?? 'standard'));

        foreach (self::DEFAULT_TIERS as $shape) {
            $input = $inputByKey->get($shape['tier'], []);

            CourtMembershipTier::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $venueClusterId,
                    'tier' => $shape['tier'],
                ],
                [
                    'discount_percent' => round((float) ($input['discount_percent'] ?? $shape['discount_percent']), 2),
                    'min_bookings' => (int) ($input['min_bookings'] ?? $input['min_completed_bookings'] ?? $shape['min_bookings']),
                    'min_spent_amount' => round((float) ($input['min_spent_amount'] ?? $input['min_spend_amount'] ?? $shape['min_spent_amount']), 2),
                    'maintain_period_months' => $this->nullableInt($input['maintain_period_months'] ?? null),
                    'maintain_min_bookings' => $this->nullableInt($input['maintain_min_bookings'] ?? null),
                    'maintain_min_spent' => $this->nullableFloat($input['maintain_min_spent'] ?? $input['maintain_min_spend_amount'] ?? null),
                ],
            );
        }

        return $this->settingsPayload($venueClusterId);
    }

    public function syncBooking(Booking $booking): ?UserCourtMembership
    {
        if ($booking->status !== 'completed' || ! $booking->customer_id || ! $booking->venue_cluster_id) {
            return null;
        }

        return $this->syncUserVenue($booking->customer_id, $booking->venue_cluster_id, 'booking_completed');
    }

    public function syncUserVenue(string $userId, string $venueClusterId, string $reason = 'recalculated'): UserCourtMembership
    {
        $stats = $this->statsForUserVenue($userId, $venueClusterId);
        $settings = $this->settingsForCluster($venueClusterId);
        $eligibleTier = $this->determineTier($settings, $stats['total_bookings'], $stats['total_spent']);

        return DB::transaction(function () use ($userId, $venueClusterId, $stats, $settings, $eligibleTier, $reason): UserCourtMembership {
            $membership = UserCourtMembership::query()
                ->where('user_id', $userId)
                ->where('venue_cluster_id', $venueClusterId)
                ->lockForUpdate()
                ->first();

            $oldTier = $membership?->tier;

            if (! $membership) {
                $membership = new UserCourtMembership([
                    'user_id' => $userId,
                    'venue_cluster_id' => $venueClusterId,
                    'period_start' => now()->toDateString(),
                ]);
            }

            $currentTier = $this->normalizeTierKey($membership->tier ?: 'standard');
            $canUpgrade = $oldTier === null || $reason === 'booking_completed';
            $targetTier = $canUpgrade && $this->tierOrder($eligibleTier['tier']) > $this->tierOrder($currentTier)
                ? $eligibleTier
                : ($settings->firstWhere('tier', $currentTier) ?: $eligibleTier);

            $periodStart = $membership->period_start ? Carbon::parse($membership->period_start)->startOfDay() : now()->startOfDay();
            $periodStats = $this->periodStatsForUserVenue($userId, $venueClusterId, $periodStart);

            $membership->fill([
                'tier' => $targetTier['tier'],
                'total_bookings' => $stats['total_bookings'],
                'total_spent' => $stats['total_spent'],
                'period_bookings' => $periodStats['period_bookings'],
                'period_spent' => $periodStats['period_spent'],
                'period_start' => $periodStart->toDateString(),
            ]);

            if ($oldTier !== null && $this->tierOrder($targetTier['tier']) > $this->tierOrder($oldTier)) {
                $membership->last_upgraded_at = now();
            }

            $membership->save();

            if ($oldTier !== $targetTier['tier']) {
                UserCourtMembershipHistory::query()->create([
                    'membership_id' => $membership->id,
                    'user_id' => $userId,
                    'venue_cluster_id' => $venueClusterId,
                    'from_tier' => $oldTier,
                    'to_tier' => $targetTier['tier'],
                    'change_type' => $oldTier === null ? 'created' : ($targetTier['tier_order'] > $this->tierOrder($oldTier) ? 'upgraded' : 'downgraded'),
                    'reason' => $reason,
                    'total_bookings' => $stats['total_bookings'],
                    'total_spent' => $stats['total_spent'],
                    'period_bookings' => $periodStats['period_bookings'],
                    'period_spent' => $periodStats['period_spent'],
                    'changed_at' => now(),
                ]);
            }

            return $membership->fresh('venueCluster');
        });
    }

    public function membershipsForUser(User $user): array
    {
        $clusterIds = Booking::query()
            ->where('customer_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('venue_cluster_id')
            ->distinct()
            ->pluck('venue_cluster_id')
            ->merge(UserCourtMembership::query()->where('user_id', $user->id)->pluck('venue_cluster_id'))
            ->unique()
            ->values();

        return $clusterIds
            ->map(fn (string $clusterId): UserCourtMembership => $this->syncUserVenue($user->id, $clusterId))
            ->map(fn (UserCourtMembership $membership): array => $this->membershipPayload($membership))
            ->sortByDesc(fn (array $item): int => (int) ($item['tier']['tier_order'] ?? 0))
            ->values()
            ->all();
    }

    public function primaryMembershipForUser(User $user): ?array
    {
        return collect($this->membershipsForUser($user))->first();
    }

    public function discountForBooking(string $userId, string $venueClusterId, float $amount): array
    {
        $tierKey = $this->userTierKey($userId, $venueClusterId);
        $settings = $this->settingsForCluster($venueClusterId);
        $tier = $settings->firstWhere('tier', $tierKey) ?: $settings->first();
        $discountPercent = (float) ($tier['discount_percent'] ?? 0);
        $discountAmount = round(min(max($amount * ($discountPercent / 100), 0), $amount), 2);

        return [
            'tier' => $tier['tier'],
            'tier_key' => $tier['tier'],
            'tier_label' => $tier['tier_label'],
            'tier_order' => $tier['tier_order'],
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
        ];
    }

    public function userTierKey(string $userId, string $venueClusterId): string
    {
        $membership = UserCourtMembership::query()
            ->where('user_id', $userId)
            ->where('venue_cluster_id', $venueClusterId)
            ->first();

        if ($membership) {
            return $this->normalizeTierKey($membership->tier);
        }

        $stats = $this->statsForUserVenue($userId, $venueClusterId);
        $tier = $this->determineTier($this->settingsForCluster($venueClusterId), $stats['total_bookings'], $stats['total_spent']);

        return $tier['tier'];
    }

    public function evaluateMaintenance(): int
    {
        $processed = 0;

        UserCourtMembership::query()
            ->where('tier', '!=', 'standard')
            ->with('venueCluster')
            ->orderBy('id')
            ->chunkById(100, function (Collection $memberships) use (&$processed): void {
                foreach ($memberships as $membership) {
                    if ($this->evaluateMembershipMaintenance($membership)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    private function settingsForCluster(string $venueClusterId): Collection
    {
        $stored = CourtMembershipTier::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->get()
            ->keyBy('tier');

        return collect(self::DEFAULT_TIERS)
            ->map(function (array $shape) use ($stored): array {
                $tier = $stored->get($shape['tier']);
                if (! $tier) {
                    return $shape + [
                        'maintain_period_months' => null,
                        'maintain_min_bookings' => null,
                        'maintain_min_spent' => null,
                    ];
                }

                return [
                    'tier' => $shape['tier'],
                    'tier_label' => $shape['tier_label'],
                    'tier_order' => $shape['tier_order'],
                    'discount_percent' => (float) $tier->discount_percent,
                    'min_bookings' => (int) $tier->min_bookings,
                    'min_spent_amount' => (float) $tier->min_spent_amount,
                    'maintain_period_months' => $tier->maintain_period_months,
                    'maintain_min_bookings' => $tier->maintain_min_bookings,
                    'maintain_min_spent' => $tier->maintain_min_spent !== null ? (float) $tier->maintain_min_spent : null,
                ];
            })
            ->sortBy('tier_order')
            ->values();
    }

    private function determineTier(Collection $settings, int $completedBookings, float $totalSpend): array
    {
        return $settings
            ->filter(fn (array $tier): bool => $completedBookings >= (int) $tier['min_bookings'] && $totalSpend >= (float) $tier['min_spent_amount'])
            ->sortByDesc('tier_order')
            ->first() ?: $settings->first();
    }

    private function statsForUserVenue(string $userId, string $venueClusterId): array
    {
        $stats = Booking::query()
            ->where('customer_id', $userId)
            ->where('venue_cluster_id', $venueClusterId)
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as completed_bookings')
            ->selectRaw('COALESCE(SUM(COALESCE(final_amount, total_price, 0)), 0) as total_spend_amount')
            ->selectRaw('MAX(updated_at) as last_booking_completed_at')
            ->first();

        return [
            'total_bookings' => (int) ($stats?->completed_bookings ?? 0),
            'total_spent' => round((float) ($stats?->total_spend_amount ?? 0), 2),
            'last_booking_completed_at' => $stats?->last_booking_completed_at,
        ];
    }

    private function periodStatsForUserVenue(string $userId, string $venueClusterId, Carbon $periodStart, ?Carbon $periodEnd = null): array
    {
        $stats = Booking::query()
            ->where('customer_id', $userId)
            ->where('venue_cluster_id', $venueClusterId)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $periodStart)
            ->when($periodEnd, fn ($query) => $query->where('updated_at', '<', $periodEnd))
            ->selectRaw('COUNT(*) as period_bookings')
            ->selectRaw('COALESCE(SUM(COALESCE(final_amount, total_price, 0)), 0) as period_spent')
            ->first();

        return [
            'period_bookings' => (int) ($stats?->period_bookings ?? 0),
            'period_spent' => round((float) ($stats?->period_spent ?? 0), 2),
        ];
    }

    private function membershipPayload(UserCourtMembership $membership): array
    {
        $settings = $this->settingsForCluster($membership->venue_cluster_id);
        $tier = $settings->firstWhere('tier', $membership->tier) ?: $settings->first();
        $nextTier = $settings->first(fn (array $item): bool => (int) $item['tier_order'] === ((int) $tier['tier_order'] + 1));

        $progressPercent = 100;
        if ($nextTier) {
            $bookingRange = max(1, (int) $nextTier['min_bookings'] - (int) $tier['min_bookings']);
            $spendRange = max(1, (float) $nextTier['min_spent_amount'] - (float) $tier['min_spent_amount']);
            $bookingProgress = (((int) $membership->total_bookings - (int) $tier['min_bookings']) / $bookingRange) * 100;
            $spendProgress = (((float) $membership->total_spent - (float) $tier['min_spent_amount']) / $spendRange) * 100;
            $progressPercent = (int) round(min(100, max(0, min($bookingProgress, $spendProgress))));
        }

        return [
            'venue_cluster_id' => $membership->venue_cluster_id,
            'venue_name' => $membership->venueCluster?->name,
            'tier' => $this->tierPayload($tier),
            'completed_bookings' => (int) $membership->total_bookings,
            'total_bookings' => (int) $membership->total_bookings,
            'total_spend_amount' => (float) $membership->total_spent,
            'total_spent' => (float) $membership->total_spent,
            'period_bookings' => (int) $membership->period_bookings,
            'period_spent' => (float) $membership->period_spent,
            'next_tier' => $nextTier ? $this->tierPayload($nextTier) : null,
            'tiers' => $settings->map(fn (array $item): array => $this->tierPayload($item))->values()->all(),
            'remaining_bookings' => $nextTier ? max(0, (int) $nextTier['min_bookings'] - (int) $membership->total_bookings) : 0,
            'remaining_spend_amount' => $nextTier ? max(0, (float) $nextTier['min_spent_amount'] - (float) $membership->total_spent) : 0,
            'progress_percent' => $progressPercent,
        ];
    }

    private function tierPayload(array $tier): array
    {
        return [
            'tier' => $tier['tier'],
            'tier_key' => $tier['tier'],
            'key' => $tier['tier'],
            'tier_label' => $tier['tier_label'],
            'label' => $tier['tier_label'],
            'tier_order' => (int) $tier['tier_order'],
            'discount_percent' => (float) $tier['discount_percent'],
            'min_bookings' => (int) $tier['min_bookings'],
            'min_completed_bookings' => (int) $tier['min_bookings'],
            'min_spent_amount' => (float) $tier['min_spent_amount'],
            'min_spend_amount' => (float) $tier['min_spent_amount'],
            'maintain_period_months' => $tier['maintain_period_months'] ?? null,
            'maintain_min_bookings' => $tier['maintain_min_bookings'] ?? null,
            'maintain_min_spent' => $tier['maintain_min_spent'] ?? null,
            'maintain_min_spend_amount' => $tier['maintain_min_spent'] ?? null,
        ];
    }

    private function evaluateMembershipMaintenance(UserCourtMembership $membership): bool
    {
        $settings = $this->settingsForCluster($membership->venue_cluster_id);
        $tier = $settings->firstWhere('tier', $membership->tier);
        if (! $tier || $tier['tier'] === 'standard' || empty($tier['maintain_period_months'])) {
            return false;
        }

        $periodStart = $membership->period_start ? Carbon::parse($membership->period_start)->startOfDay() : now()->startOfDay();
        $periodEnd = $periodStart->copy()->addMonths((int) $tier['maintain_period_months']);
        if (now()->lt($periodEnd)) {
            return false;
        }

        $periodStats = $this->periodStatsForUserVenue($membership->user_id, $membership->venue_cluster_id, $periodStart, $periodEnd);
        $requiredBookings = (int) ($tier['maintain_min_bookings'] ?? 0);
        $requiredSpent = (float) ($tier['maintain_min_spent'] ?? 0);

        if ($periodStats['period_bookings'] >= $requiredBookings && $periodStats['period_spent'] >= $requiredSpent) {
            $membership->fill([
                'period_bookings' => 0,
                'period_spent' => 0,
                'period_start' => $periodEnd->toDateString(),
            ])->save();

            return false;
        }

        $previousTier = $tier['tier'];
        $nextTier = $settings->first(fn (array $item): bool => (int) $item['tier_order'] === ((int) $tier['tier_order'] - 1)) ?: $settings->first();
        $reason = $this->maintenanceFailureReason($periodStats, $requiredBookings, $requiredSpent);

        DB::transaction(function () use ($membership, $previousTier, $nextTier, $periodStats, $reason, $periodEnd): void {
            Notification::query()->create([
                'user_id' => $membership->user_id,
                'type' => 'membership_downgrade',
                'title' => 'Hang thanh vien san bi ha',
                'body' => sprintf('Ban chua dat dieu kien duy tri (%s). Hang moi: %s.', $reason, $nextTier['tier_label']),
                'reference_type' => 'venue_cluster',
                'reference_id' => $membership->venue_cluster_id,
                'data' => [
                    'from_tier' => $previousTier,
                    'to_tier' => $nextTier['tier'],
                    'reason' => $reason,
                ],
            ]);

            $membership->fill([
                'tier' => $nextTier['tier'],
                'period_bookings' => 0,
                'period_spent' => 0,
                'period_start' => now()->toDateString(),
                'last_downgraded_at' => now(),
                'downgrade_notified_at' => now(),
            ])->save();

            UserCourtMembershipHistory::query()->create([
                'membership_id' => $membership->id,
                'user_id' => $membership->user_id,
                'venue_cluster_id' => $membership->venue_cluster_id,
                'from_tier' => $previousTier,
                'to_tier' => $nextTier['tier'],
                'change_type' => 'downgraded',
                'reason' => 'maintenance_failed: '.$reason,
                'total_bookings' => $membership->total_bookings,
                'total_spent' => $membership->total_spent,
                'period_bookings' => $periodStats['period_bookings'],
                'period_spent' => $periodStats['period_spent'],
                'changed_at' => now(),
            ]);
        });

        return true;
    }

    private function maintenanceFailureReason(array $periodStats, int $requiredBookings, float $requiredSpent): string
    {
        $reasons = [];
        if ($periodStats['period_bookings'] < $requiredBookings) {
            $reasons[] = 'khong du booking trong ky';
        }
        if ($periodStats['period_spent'] < $requiredSpent) {
            $reasons[] = 'khong du chi tieu trong ky';
        }

        return implode(' va ', $reasons) ?: 'khong dat dieu kien duy tri';
    }

    private function tierOrder(?string $tierKey): int
    {
        return collect(self::DEFAULT_TIERS)->firstWhere('tier', $this->normalizeTierKey($tierKey))['tier_order'] ?? -1;
    }

    private function normalizeTierKey(?string $tierKey): string
    {
        return $tierKey === 'regular' ? 'standard' : ($tierKey ?: 'standard');
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }
}
