<?php

use App\Services\Auth\SystemPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (SystemPermissionCatalog::definitions() as $code => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'group_name' => $definition['group_name'],
                    'created_at' => now(),
                ]
            );
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'code');
        $roleIds = DB::table('roles')->pluck('id', 'name');

        foreach (SystemPermissionCatalog::defaultRolePermissions() as $roleName => $codes) {
            $roleId = $roleIds[$roleName] ?? null;

            if (! $roleId) {
                continue;
            }

            foreach (array_unique($codes) as $code) {
                $permissionId = $permissionIds[$code] ?? null;

                if (! $permissionId) {
                    continue;
                }

                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $legacyCodes = [
            'dashboard.view', 'profile.view', 'profile.update',
            'user.view', 'user.lock', 'user.unlock',
            'staff.view', 'staff.create', 'staff.assign_role', 'staff.lock',
            'role.view', 'role.create', 'role.update', 'role.delete', 'role.permission.manage', 'role.manage',
            'policy.view', 'policy.create', 'policy.update', 'policy.publish', 'policy.rule.manage',
            'venue.view', 'venue.manage', 'venue.lock', 'partner.view', 'partner.review', 'court.view', 'court.manage',
            'booking.view', 'booking.manage', 'booking.support', 'price.view', 'price.manage',
            'content.view', 'content.manage', 'moderation.view', 'moderation.manage', 'moderation.approve', 'moderation.reject',
            'report.view', 'report.resolve', 'complaint.view', 'complaint.handle',
            'refund.view', 'refund.approve', 'payment.view', 'payment.manage', 'wallet.view',
            'withdrawal.manage', 'reconciliation.manage', 'audit.view',
        ];

        DB::table('permissions')
            ->whereIn('code', array_values(array_diff(array_keys(SystemPermissionCatalog::definitions()), $legacyCodes)))
            ->delete();
    }
};
