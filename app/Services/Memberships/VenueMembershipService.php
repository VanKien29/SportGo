<?php

namespace App\Services\Memberships;

use App\Models\Booking;
use App\Models\User;
use App\Models\UserVenueMembership;
use App\Models\UserVenueMembershipHistory;
use App\Models\VenueMembershipTierSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueMembershipService
{
    public const DEFAULT_TIERS = [
        ['tier_key' => 'regular', 'tier_label' => 'Thường', 'tier_order' => 0, 'discount_percent' => 0, 'min_completed_bookings' => 0, 'min_spend_amount' => 0],
        ['tier_key' => 'silver', 'tier_label' => 'Bạc', 'tier_order' => 1, 'discount_percent' => 3, 'min_completed_bookings' => 5, 'min_spend_amount' => 500000],
        ['tier_key' => 'gold', 'tier_label' => 'Vàng', 'tier_order' => 2, 'discount_percent' => 5, 'min_completed_bookings' => 15, 'min_spend_amount' => 2000000],
        ['tier_key' => 'diamond', 'tier_label' => 'Kim cương', 'tier_order' => 3, 'discount_percent' => 8, 'min_completed_bookings' => 30, 'min_spend_amount' => 5000000],
    ];

    public function tierKeys(): array
    {
        return collect(self::DEFAULT_TIERS)->pluck('tier_key')->all();
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
        $inputByKey = collect($tiers)->keyBy('tier_key');

        foreach (self::DEFAULT_TIERS as $shape) {
            $input = $inputByKey->get($shape['tier_key'], []);

            VenueMembershipTierSetting::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $venueClusterId,
                    'tier_key' => $shape['tier_key'],
                ],
                [
                    'tier_label' => $shape['tier_label'],
                    'tier_order' => $shape['tier_order'],
                    'discount_percent' => round((float) ($input['discount_percent'] ?? $shape['discount_percent']), 2),
                    'min_completed_bookings' => (int) ($input['min_completed_bookings'] ?? $shape['min_completed_bookings']),
                    'min_spend_amount' => round((float) ($input['min_spend_amount'] ?? $shape['min_spend_amount']), 2),
                    'maintain_period_months' => $this->nullableInt($input['maintain_period_months'] ?? null),
                    'maintain_min_bookings' => $this->nullableInt($input['maintain_min_bookings'] ?? null),
                    'maintain_min_spend_amount' => $this->nullableFloat($input['maintain_min_spend_amount'] ?? null),
                ],
            );
        }

        return $this->settingsPayload($venueClusterId);
    }

    public function syncBooking(Booking $booking): ?UserVenueMembership
    {
        if ($booking->status !== 'completed' || ! $booking->customer_id || ! $booking->venue_cluster_id) {
            return null;
        }

        return $this->syncUserVenue($booking->customer_id, $booking->venue_cluster_id, 'booking_completed');
    }

    public function syncUserVenue(string $userId, string $venueClusterId, string $reason = 'recalculated'): UserVenueMembership
    {
        $stats = $this->statsForUserVenue($userId, $venueClusterId);
        $settings = $this->settingsForCluster($venueClusterId);
        $tier = $this->determineTier($settings, $stats['completed_bookings'], $stats['total_spend_amount']);

        return DB::transaction(function () use ($userId, $venueClusterId, $stats, $tier, $reason): UserVenueMembership {
            $membership = UserVenueMembership::query()
                ->where('user_id', $userId)
                ->where('venue_cluster_id', $venueClusterId)
                ->lockForUpdate()
                ->first();

            $oldTier = $membership?->tier_key;

            if (! $membership) {
                $membership = new UserVenueMembership([
                    'user_id' => $userId,
                    'venue_cluster_id' => $venueClusterId,
                ]);
            }

            $membership->fill([
                'tier_key' => $tier['tier_key'],
                'completed_bookings' => $stats['completed_bookings'],
                'total_spend_amount' => $stats['total_spend_amount'],
                'last_booking_completed_at' => $stats['last_booking_completed_at'],
                'evaluated_at' => now(),
            ]);
            $membership->save();

            if ($oldTier !== $tier['tier_key']) {
                UserVenueMembershipHistory::query()->create([
                    'membership_id' => $membership->id,
                    'user_id' => $userId,
                    'venue_cluster_id' => $venueClusterId,
                    'from_tier_key' => $oldTier,
                    'to_tier_key' => $tier['tier_key'],
                    'change_type' => $oldTier === null ? 'created' : ($tier['tier_order'] > $this->tierOrder($oldTier) ? 'upgraded' : 'downgraded'),
                    'reason' => $reason,
                    'completed_bookings' => $stats['completed_bookings'],
                    'total_spend_amount' => $stats['total_spend_amount'],
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
            ->merge(UserVenueMembership::query()->where('user_id', $user->id)->pluck('venue_cluster_id'))
            ->unique()
            ->values();

        return $clusterIds
            ->map(fn (string $clusterId): UserVenueMembership => $this->syncUserVenue($user->id, $clusterId))
            ->map(fn (UserVenueMembership $membership): array => $this->membershipPayload($membership))
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
        $stats = $this->statsForUserVenue($userId, $venueClusterId);
        $tier = $this->determineTier($this->settingsForCluster($venueClusterId), $stats['completed_bookings'], $stats['total_spend_amount']);
        $discountPercent = (float) ($tier['discount_percent'] ?? 0);
        $discountAmount = round(min(max($amount * ($discountPercent / 100), 0), $amount), 2);

        return [
            'tier_key' => $tier['tier_key'],
            'tier_label' => $tier['tier_label'],
            'tier_order' => $tier['tier_order'],
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
        ];
    }

    public function userTierKey(string $userId, string $venueClusterId): string
    {
        $stats = $this->statsForUserVenue($userId, $venueClusterId);
        $tier = $this->determineTier($this->settingsForCluster($venueClusterId), $stats['completed_bookings'], $stats['total_spend_amount']);

        return $tier['tier_key'];
    }

    private function settingsForCluster(string $venueClusterId): Collection
    {
        $stored = VenueMembershipTierSetting::query()
            ->where('venue_cluster_id', $venueClusterId)
            ->get()
            ->keyBy('tier_key');

        return collect(self::DEFAULT_TIERS)
            ->map(function (array $shape) use ($stored): array {
                $tier = $stored->get($shape['tier_key']);
                if (! $tier) {
                    return $shape + [
                        'maintain_period_months' => null,
                        'maintain_min_bookings' => null,
                        'maintain_min_spend_amount' => null,
                    ];
                }

                return [
                    'tier_key' => $shape['tier_key'],
                    'tier_label' => $shape['tier_label'],
                    'tier_order' => $shape['tier_order'],
                    'discount_percent' => (float) $tier->discount_percent,
                    'min_completed_bookings' => (int) $tier->min_completed_bookings,
                    'min_spend_amount' => (float) $tier->min_spend_amount,
                    'maintain_period_months' => $tier->maintain_period_months,
                    'maintain_min_bookings' => $tier->maintain_min_bookings,
                    'maintain_min_spend_amount' => $tier->maintain_min_spend_amount !== null ? (float) $tier->maintain_min_spend_amount : null,
                ];
            })
            ->sortBy('tier_order')
            ->values();
    }

    private function determineTier(Collection $settings, int $completedBookings, float $totalSpend): array
    {
        return $settings
            ->filter(fn (array $tier): bool => $completedBookings >= (int) $tier['min_completed_bookings'] && $totalSpend >= (float) $tier['min_spend_amount'])
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
            'completed_bookings' => (int) ($stats?->completed_bookings ?? 0),
            'total_spend_amount' => round((float) ($stats?->total_spend_amount ?? 0), 2),
            'last_booking_completed_at' => $stats?->last_booking_completed_at,
        ];
    }

    private function membershipPayload(UserVenueMembership $membership): array
    {
        $settings = $this->settingsForCluster($membership->venue_cluster_id);
        $tier = $settings->firstWhere('tier_key', $membership->tier_key) ?: $settings->first();
        $nextTier = $settings->first(fn (array $item): bool => (int) $item['tier_order'] === ((int) $tier['tier_order'] + 1));

        $progressPercent = 100;
        if ($nextTier) {
            $bookingRange = max(1, (int) $nextTier['min_completed_bookings'] - (int) $tier['min_completed_bookings']);
            $spendRange = max(1, (float) $nextTier['min_spend_amount'] - (float) $tier['min_spend_amount']);
            $bookingProgress = (((int) $membership->completed_bookings - (int) $tier['min_completed_bookings']) / $bookingRange) * 100;
            $spendProgress = (((float) $membership->total_spend_amount - (float) $tier['min_spend_amount']) / $spendRange) * 100;
            $progressPercent = (int) round(min(100, max(0, min($bookingProgress, $spendProgress))));
        }

        return [
            'venue_cluster_id' => $membership->venue_cluster_id,
            'venue_name' => $membership->venueCluster?->name,
            'tier' => $this->tierPayload($tier),
            'completed_bookings' => (int) $membership->completed_bookings,
            'total_spend_amount' => (float) $membership->total_spend_amount,
            'next_tier' => $nextTier ? $this->tierPayload($nextTier) : null,
            'remaining_bookings' => $nextTier ? max(0, (int) $nextTier['min_completed_bookings'] - (int) $membership->completed_bookings) : 0,
            'remaining_spend_amount' => $nextTier ? max(0, (float) $nextTier['min_spend_amount'] - (float) $membership->total_spend_amount) : 0,
            'progress_percent' => $progressPercent,
        ];
    }

    private function tierPayload(array $tier): array
    {
        return [
            'tier_key' => $tier['tier_key'],
            'key' => $tier['tier_key'],
            'tier_label' => $tier['tier_label'],
            'label' => $tier['tier_label'],
            'tier_order' => (int) $tier['tier_order'],
            'discount_percent' => (float) $tier['discount_percent'],
            'min_completed_bookings' => (int) $tier['min_completed_bookings'],
            'min_spend_amount' => (float) $tier['min_spend_amount'],
            'maintain_period_months' => $tier['maintain_period_months'] ?? null,
            'maintain_min_bookings' => $tier['maintain_min_bookings'] ?? null,
            'maintain_min_spend_amount' => $tier['maintain_min_spend_amount'] ?? null,
        ];
    }

    private function tierOrder(?string $tierKey): int
    {
        return collect(self::DEFAULT_TIERS)->firstWhere('tier_key', $tierKey)['tier_order'] ?? -1;
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
