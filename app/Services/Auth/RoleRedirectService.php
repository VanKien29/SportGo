<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

class RoleRedirectService
{
    private const ADMIN_ROLES = ['admin', 'super_admin', 'system_staff'];

    private const OWNER_ROLES = ['venue_owner', 'venue_staff'];

    public function roles(User $user): array
    {
        return $user->roles()
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function roleGroup(array $roles): string
    {
        if (array_intersect($roles, self::ADMIN_ROLES)) {
            return 'admin';
        }

        if (array_intersect($roles, self::OWNER_ROLES)) {
            return 'owner';
        }

        return 'user';
    }

    public function redirectTo(string $roleGroup): string
    {
        return match ($roleGroup) {
            'admin' => '/admin/dashboard',
            'owner' => '/owner/select-cluster',
            default => '/',
        };
    }

    public function payload(User $user, ?string $token = null): array
    {
        $roles = $this->roles($user);
        $roleGroup = $this->roleGroup($roles);

        return array_filter([
            'token' => $token,
            'user' => $this->userPayload($user),
            'roles' => $roles,
            'role_group' => $roleGroup,
            'redirect_to' => $this->redirectTo($roleGroup),
        ], fn ($value) => $value !== null);
    }

    public function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'avatar_url' => $user->avatar_url,
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_until' => $user->locked_until,
        ];
    }

    public function assignDefaultUserRole(User $user): void
    {
        $role = Role::query()
            ->whereIn('name', ['user', 'player'])
            ->orderByRaw("CASE name WHEN 'user' THEN 0 WHEN 'player' THEN 1 ELSE 2 END")
            ->first();

        if (! $role) {
            return;
        }

        UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);
    }
}
