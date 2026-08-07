<?php

namespace App\Services;

use App\Models\User;
use App\Models\VenueCourt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueStaffAccessService
{
    public function isStaff(User $user): bool
    {
        return $user->roles()->where('roles.name', 'venue_staff')->exists();
    }

    public function ownsCluster(User $user, string $clusterId): bool
    {
        return $user->roles()->where('roles.name', 'venue_owner')->exists()
            && DB::table('venue_clusters')
                ->where('id', $clusterId)
                ->where('owner_id', $user->id)
                ->exists();
    }

    /** Null means the staff member has access to every court type in the cluster. */
    public function allowedCourtTypeIds(User $user, string $clusterId): ?Collection
    {
        if ($this->ownsCluster($user, $clusterId) || ! $this->isStaff($user)) {
            return null;
        }

        $assignments = DB::table('venue_staff_assignments')
            ->where('user_id', $user->id)
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'active')
            ->get(['scope_type', 'court_type_id']);

        if ($assignments->contains('scope_type', 'all_cluster')) {
            return null;
        }

        return $assignments->where('scope_type', 'court_type')
            ->pluck('court_type_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function assertClusterAccess(User $user, string $clusterId): void
    {
        if ($this->ownsCluster($user, $clusterId)) {
            return;
        }

        if (DB::table('venue_staff_assignments')
            ->where('user_id', $user->id)
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'active')
            ->exists()) {
            return;
        }

        throw new AuthorizationException('Bạn không được phân công tại cụm sân này.');
    }

    public function assertCourtAccess(User $user, VenueCourt $court): void
    {
        $this->assertClusterAccess($user, (string) $court->venue_cluster_id);
        $allowed = $this->allowedCourtTypeIds($user, (string) $court->venue_cluster_id);

        if ($allowed !== null && ! $allowed->contains((int) $court->court_type_id)) {
            throw new AuthorizationException('Bạn không được phân công loại sân này.');
        }
    }
}
