<?php

namespace App\Services\Auth;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

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

    private const MEMBERSHIP_TIERS = [
        ['key' => 'regular', 'label' => 'Thường', 'min_bookings' => 0, 'discount_percent' => 0],
        ['key' => 'silver', 'label' => 'Bạc', 'min_bookings' => 5, 'discount_percent' => 3],
        ['key' => 'gold', 'label' => 'Vàng', 'min_bookings' => 15, 'discount_percent' => 5],
        ['key' => 'diamond', 'label' => 'Kim cương', 'min_bookings' => 30, 'discount_percent' => 8],
    ];

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
            'membership_tier' => $this->membershipTierPayload($user),
        ];
    }

    private function membershipTierPayload(User $user): array
    {
        $completedBookings = Booking::query()
            ->where('customer_id', $user->id)
            ->where('status', 'completed')
            ->count();

        $currentIndex = 0;
        foreach (self::MEMBERSHIP_TIERS as $index => $tier) {
            if ($completedBookings >= $tier['min_bookings']) {
                $currentIndex = $index;
            }
        }

        $currentTier = self::MEMBERSHIP_TIERS[$currentIndex];
        $nextTier = self::MEMBERSHIP_TIERS[$currentIndex + 1] ?? null;
        $progressPercent = 100;

        if ($nextTier) {
            $rangeStart = $currentTier['min_bookings'];
            $rangeSize = max(1, $nextTier['min_bookings'] - $rangeStart);
            $progressPercent = min(100, max(0, round((($completedBookings - $rangeStart) / $rangeSize) * 100)));
        }

        return [
            'key' => $currentTier['key'],
            'label' => $currentTier['label'],
            'completed_bookings' => $completedBookings,
            'min_bookings' => $currentTier['min_bookings'],
            'discount_percent' => $currentTier['discount_percent'],
            'next_tier' => $nextTier,
            'remaining_bookings' => $nextTier ? max(0, $nextTier['min_bookings'] - $completedBookings) : 0,
            'progress_percent' => $progressPercent,
        ];
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
