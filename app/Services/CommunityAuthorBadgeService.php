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
            ];
        }

        $this->decorateVenueOwners($lookup, $ids);
        $this->decorateVipMembers($lookup, $ids);

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
                ];
            });
    }
}
