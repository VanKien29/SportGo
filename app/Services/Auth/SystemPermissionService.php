<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SystemPermissionService
{
    private array $permissionCache = [];

    private array $roleCache = [];

    public function roles(User $user): array
    {
        return $this->roleCache[$user->id] ??= $user->roles()
            ->pluck('roles.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isSuperAdmin(User $user): bool
    {
        return in_array('super_admin', $this->roles($user), true);
    }

    public function codes(User $user): array
    {
        if (array_key_exists($user->id, $this->permissionCache)) {
            return $this->permissionCache[$user->id];
        }

        $codes = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('permissions.code')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this->permissionCache[$user->id] = SystemPermissionCatalog::effectiveCodes($codes);
    }

    public function hasAny(User $user, string|array $permissions): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return collect((array) $permissions)->intersect($this->codes($user))->isNotEmpty();
    }

    public function hasAll(User $user, string|array $permissions): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return collect((array) $permissions)->diff($this->codes($user))->isEmpty();
    }

    public function authorizeAny(?User $user, string|array $permissions): void
    {
        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        if (! $this->hasAny($user, $permissions)) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }

    public function authorizeAll(?User $user, string|array $permissions): void
    {
        if (! $user) {
            throw new AuthorizationException('Bạn cần đăng nhập để thực hiện thao tác này.');
        }

        if (! $this->hasAll($user, $permissions)) {
            throw new AuthorizationException('Bạn không có quyền thực hiện thao tác này.');
        }
    }
}
