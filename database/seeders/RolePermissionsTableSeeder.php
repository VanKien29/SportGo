<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolePermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionCodes = Permission::query()->pluck('id', 'code');
        $roles = Role::query()->whereIn('name', array_keys($this->permissionMap()))->pluck('id', 'name');

        foreach ($this->permissionMap() as $roleName => $codes) {
            $roleId = $roles[$roleName] ?? null;

            if (! $roleId) {
                continue;
            }

            RolePermission::query()->where('role_id', $roleId)->delete();

            foreach ($codes as $code) {
                $permissionId = $permissionCodes[$code] ?? null;

                if (! $permissionId) {
                    continue;
                }

                RolePermission::query()->firstOrCreate([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function permissionMap(): array
    {
        $all = [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'user.view',
            'user.lock',
            'user.unlock',
            'role.view',
            'role.manage',
            'venue.view',
            'venue.manage',
            'court.view',
            'court.manage',
            'booking.view',
            'booking.manage',
            'price.view',
            'price.manage',
            'content.view',
            'content.manage',
            'moderation.view',
            'moderation.manage',
            'audit.view',
        ];

        return [
            'super_admin' => $all,
            'admin' => [
                'dashboard.view',
                'profile.view',
                'profile.update',
                'user.view',
                'user.lock',
                'user.unlock',
                'role.view',
                'role.manage',
                'venue.view',
                'venue.manage',
                'court.view',
                'court.manage',
                'booking.view',
                'booking.manage',
                'price.view',
                'price.manage',
                'content.view',
                'content.manage',
                'moderation.view',
                'moderation.manage',
                'audit.view',
            ],
            'system_staff' => [
                'dashboard.view',
                'profile.view',
                'profile.update',
                'user.view',
                'user.lock',
                'user.unlock',
                'content.view',
                'content.manage',
                'moderation.view',
                'moderation.manage',
            ],
            'venue_owner' => [
                'dashboard.view',
                'profile.view',
                'profile.update',
                'venue.view',
                'venue.manage',
                'court.view',
                'court.manage',
                'booking.view',
                'booking.manage',
                'price.view',
                'price.manage',
            ],
            'venue_staff' => [
                'dashboard.view',
                'profile.view',
                'profile.update',
                'court.view',
                'booking.view',
                'booking.manage',
            ],
            'user' => [
                'profile.view',
                'profile.update',
                'booking.view',
            ],
        ];
    }
}
