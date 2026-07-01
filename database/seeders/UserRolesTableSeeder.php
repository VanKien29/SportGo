<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UserRolesTableSeeder extends Seeder
{
    private const ZERO_UUID = '00000000-0000-0000-0000-000000000000';

    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return;
        }

        $roleByName = Role::query()->pluck('id', 'name');
        $defaultUserRoleId = $roleByName['user'] ?? null;

        $assignments = [
            'superadmin' => 'super_admin',
            'admin' => 'admin',
            'systemstaff' => 'system_staff',
            'moderator' => 'content_moderator',
            'venue_manager' => 'venue_manager',
            'finance' => 'finance_operator',
            'policy_manager' => 'policy_manager',
            'staff_manager' => 'staff_manager',
            'owner' => 'venue_owner',
            'venuestaff' => 'venue_staff',
            'user' => 'user',
        ];

        foreach ($assignments as $username => $roleName) {
            $user = User::query()->where('username', $username)->first();
            $roleId = $roleByName[$roleName] ?? null;

            if (! $user || ! $roleId) {
                continue;
            }

            if ($roleName !== 'user' && $defaultUserRoleId) {
                UserRole::query()
                    ->where('user_id', $user->id)
                    ->where('role_id', $defaultUserRoleId)
                    ->where('scope_type', 'system')
                    ->where('scope_id', self::ZERO_UUID)
                    ->delete();
            }

            UserRole::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'scope_type' => 'system',
                    'scope_id' => self::ZERO_UUID,
                ],
                ['granted_by' => null]
            );
        }
    }
}
