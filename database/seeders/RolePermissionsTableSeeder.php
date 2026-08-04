<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\Auth\SystemPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolePermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionIds = Permission::query()->pluck('id', 'code');
        $permissionMap = SystemPermissionCatalog::defaultRolePermissions();
        $roleIds = Role::query()->whereIn('name', array_keys($permissionMap))->pluck('id', 'name');

        foreach ($permissionMap as $roleName => $codes) {
            $roleId = $roleIds[$roleName] ?? null;

            if (! $roleId) {
                continue;
            }

            RolePermission::query()->where('role_id', $roleId)->delete();

            foreach (array_unique($codes) as $code) {
                $permissionId = $permissionIds[$code] ?? null;

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

}
