<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VenuePost;
use Illuminate\Auth\Access\Response;

class VenuePostPolicy
{
    private function hasRole(User $user, array $rolesToCheck): bool
    {
        $userRoles = $user->roles()->pluck('roles.name')->all();
        return count(array_intersect($userRoles, $rolesToCheck)) > 0;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, ['admin', 'super_admin', 'venue_owner', 'owner']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VenuePost $venuePost): bool
    {
        if ($this->hasRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        if ($venuePost->status === 'published') {
            return true;
        }

        return $user->id === $venuePost->author_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->hasRole($user, ['admin', 'super_admin', 'venue_owner', 'owner']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VenuePost $venuePost): bool
    {
        if ($this->hasRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        // Owner không được sửa bài đang chờ duyệt
        if ($venuePost->status === 'pending_review') {
            return false;
        }

        return $user->id === $venuePost->author_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VenuePost $venuePost): bool
    {
        if ($this->hasRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        return $user->id === $venuePost->author_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VenuePost $venuePost): bool
    {
        if ($this->hasRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        return $user->id === $venuePost->author_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VenuePost $venuePost): bool
    {
        return false; // Soft delete only
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, VenuePost $venuePost): bool
    {
        return $this->hasRole($user, ['admin', 'super_admin']);
    }
}
