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

        $permissionIds = Permission::query()->pluck('id', 'code');
        $roleIds = Role::query()->whereIn('name', array_keys($this->permissionMap()))->pluck('id', 'name');

        foreach ($this->permissionMap() as $roleName => $codes) {
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

    private function permissionMap(): array
    {
        $all = [
            'dashboard.view', 'profile.view', 'profile.update',
            'user.view', 'user.lock', 'user.unlock',
            'staff.view', 'staff.create', 'staff.assign_role', 'staff.lock',
            'role.view', 'role.create', 'role.update', 'role.delete', 'role.permission.manage', 'role.manage',
            'policy.view', 'policy.create', 'policy.update', 'policy.publish', 'policy.rule.manage',
            'venue.view', 'venue.manage', 'venue.lock', 'partner.view', 'partner.review', 'court.view', 'court.manage',
            'booking.view', 'booking.manage', 'booking.support',
            'price.view', 'price.manage',
            'content.view', 'content.manage', 'moderation.view', 'moderation.manage', 'moderation.approve', 'moderation.reject',
            'report.view', 'report.resolve', 'complaint.view', 'complaint.handle',
            'refund.view', 'refund.approve', 'payment.view', 'payment.manage', 'wallet.view', 'withdrawal.manage', 'reconciliation.manage',
            'audit.view',
        ];

        $common = ['dashboard.view', 'profile.view', 'profile.update'];

        return [
            'super_admin' => $all,
            'admin' => array_values(array_diff($all, ['staff.create'])),
            'system_staff' => array_merge($common, ['user.view', 'content.view', 'moderation.view', 'report.view', 'booking.view', 'venue.view', 'policy.view']),
            'content_moderator' => array_merge($common, ['content.view', 'content.manage', 'moderation.view', 'moderation.manage', 'moderation.approve', 'moderation.reject', 'report.view', 'report.resolve']),
            'complaint_handler' => array_merge($common, ['complaint.view', 'complaint.handle', 'booking.view', 'venue.view', 'report.view']),
            'venue_manager' => array_merge($common, ['venue.view', 'venue.manage', 'venue.lock', 'court.view', 'court.manage', 'partner.view', 'booking.view']),
            'partner_manager' => array_merge($common, ['partner.view', 'partner.review', 'venue.view', 'court.view']),
            'booking_support' => array_merge($common, ['booking.view', 'booking.manage', 'booking.support', 'payment.view', 'venue.view', 'court.view']),
            'finance_operator' => array_merge($common, ['payment.view', 'payment.manage', 'refund.view', 'refund.approve', 'wallet.view', 'withdrawal.manage', 'reconciliation.manage', 'booking.view', 'audit.view']),
            'policy_manager' => array_merge($common, ['policy.view', 'policy.create', 'policy.update', 'policy.publish', 'policy.rule.manage', 'audit.view']),
            'staff_manager' => array_merge($common, ['staff.view', 'staff.create', 'staff.assign_role', 'staff.lock', 'user.view', 'user.lock', 'user.unlock', 'role.view']),

            'venue_owner' => array_merge($common, ['venue.view', 'venue.manage', 'court.view', 'court.manage', 'booking.view', 'booking.manage', 'price.view', 'price.manage']),
            'venue_staff' => array_merge($common, ['court.view', 'booking.view', 'booking.manage']),
            'user' => ['profile.view', 'profile.update', 'booking.view'],
        ];
    }
}
