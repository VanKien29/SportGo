<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommunityAuthorBadgeService
{
    public function lookup(iterable $authorIds): array
    {
        $ids = collect($authorIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $lookup = [];
        foreach ($ids as $id) {
            $lookup[(string) $id] = [
                'is_venue_owner' => false,
                'vip' => null,
                'venue_membership' => null,
            ];
        }

        $this->decorateVenueOwners($lookup, $ids);
        $this->decorateVipMembers($lookup, $ids);
        $this->decorateVenueMemberships($lookup, $ids);

        return $lookup;
    }

    private function decorateVenueOwners(array &$lookup, Collection $ids): void
    {
        if (! Schema::hasTable('venue_clusters')) {
            return;
        }

        DB::table('venue_clusters')
            ->whereIn('owner_id', $ids)
            ->whereIn('status', ['active', 'locked', 'termination_locked', 'termination_processing'])
            ->pluck('owner_id')
            ->unique()
            ->each(function ($ownerId) use (&$lookup): void {
                $key = (string) $ownerId;
                if (isset($lookup[$key])) {
                    $lookup[$key]['is_venue_owner'] = true;
                }
            });
    }

    private function decorateVipMembers(array &$lookup, Collection $ids): void
    {
        if (! Schema::hasTable('user_subscriptions') || ! Schema::hasTable('membership_packages')) {
            return;
        }

        DB::table('user_subscriptions')
            ->join('membership_packages', 'membership_packages.id', '=', 'user_subscriptions.package_id')
            ->whereIn('user_subscriptions.user_id', $ids)
            ->where('user_subscriptions.status', 'active')
            ->where('user_subscriptions.expires_at', '>', now())
            ->where('membership_packages.type', '!=', 'free')
            ->orderByDesc('user_subscriptions.expires_at')
            ->get([
                'user_subscriptions.user_id',
                'membership_packages.type',
                'membership_packages.badge_name',
            ])
            ->each(function ($subscription) use (&$lookup): void {
                $key = (string) $subscription->user_id;
                if (! isset($lookup[$key]) || $lookup[$key]['vip'] !== null) {
                    return;
                }

                $lookup[$key]['vip'] = [
                    'type' => (string) $subscription->type,
                    'label' => $subscription->badge_name ?: 'VIP SportGo',
                    'icon' => match ((string) $subscription->type) {
                        'pro' => 'shieldCheck',
                        'saving' => 'sparkles',
                        default => 'star',
                    },
                ];
            });
    }

    private function decorateVenueMemberships(array &$lookup, Collection $ids): void
    {
        if (
            ! Schema::hasTable('user_court_memberships')
            || ! Schema::hasTable('court_membership_tiers')
            || ! Schema::hasTable('venue_clusters')
        ) {
            return;
        }

        DB::table('user_court_memberships as memberships')
            ->join('court_membership_tiers as tiers', function ($join): void {
                $join->on('tiers.venue_cluster_id', '=', 'memberships.venue_cluster_id')
                    ->on('tiers.tier', '=', 'memberships.tier')
                    ->where('tiers.is_active', true);
            })
            ->leftJoin('venue_clusters', 'venue_clusters.id', '=', 'memberships.venue_cluster_id')
            ->whereIn('memberships.user_id', $ids)
            ->orderByRaw("CASE memberships.tier WHEN 'diamond' THEN 3 WHEN 'gold' THEN 2 WHEN 'silver' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('memberships.updated_at')
            ->get([
                'memberships.user_id',
                'memberships.tier as tier_key',
                'tiers.tier_label',
                'tiers.discount_percent',
                'venue_clusters.name as venue_name',
            ])
            ->each(function ($membership) use (&$lookup): void {
                $key = (string) $membership->user_id;
                if (! isset($lookup[$key]) || $lookup[$key]['venue_membership'] !== null) {
                    return;
                }

                $tierKey = (string) $membership->tier_key;
                $tierLabel = (string) ($membership->tier_label ?: 'Thường');
                $label = $tierKey === 'standard' ? 'Hội viên sân' : 'Hội viên '.$tierLabel;

                $lookup[$key]['venue_membership'] = [
                    'tier_key' => $tierKey,
                    'label' => $label,
                    'venue_name' => $membership->venue_name,
                    'discount_percent' => (float) $membership->discount_percent,
                    'icon' => match ($tierKey) {
                        'diamond' => 'sparkles',
                        'gold' => 'crown',
                        'silver' => 'star',
                        default => 'shieldCheck',
                    },
                ];
            });
    }
}
