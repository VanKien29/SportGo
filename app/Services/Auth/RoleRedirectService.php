<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Memberships\VenueMembershipService;

class RoleRedirectService
{
    private const ADMIN_ROLES = [
        'super_admin',
        'admin',
        'system_staff',
        'content_moderator',
        'complaint_handler',
        'venue_manager',
        'partner_manager',
        'booking_support',
        'finance_operator',
        'policy_manager',
        'staff_manager',
    ];

    private const OWNER_ROLES = ['venue_owner', 'venue_staff'];

    public function __construct(private readonly VenueMembershipService $venueMemberships) {}

    public function roles(User $user): array
    {
        return $user->roles()
            ->pluck('roles.name')
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

    public function isAdminAreaUser(User $user): bool
    {
        return $this->roleGroup($this->roles($user)) === 'admin';
    }

    public function redirectTo(string $roleGroup): string
    {
        return match ($roleGroup) {
            'admin' => '/admin/dashboard',
            'owner' => '/owner/dashboard',
            default => '/',
        };
    }

    public function payload(User $user, ?string $token = null): array
    {
        $roles = $this->roles($user);
        $roleGroup = $this->roleGroup($roles);

        return array_filter([
            'token' => $token,
            'user' => $this->userPayload($user, $roleGroup),
            'roles' => $roles,
            'role_group' => $roleGroup,
            'redirect_to' => $this->redirectTo($roleGroup),
        ], fn ($value) => $value !== null);
    }

    public function userPayload(User $user, ?string $roleGroup = null): array
    {
        $payload = [
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

        if (($roleGroup ?: $user->role_group) === 'user') {
            $memberships = $this->venueMemberships->membershipsForUser($user);
            $payload['membership_tier'] = $memberships[0] ?? null;
            $payload['venue_memberships'] = $memberships;
        }

        return $payload;
    }

    public function assignDefaultUserRole(User $user): void
    {
        $role = Role::query()->where('name', 'user')->first();

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
