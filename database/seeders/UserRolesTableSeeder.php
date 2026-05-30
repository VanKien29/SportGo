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

        $assignments = [
            'superadmin' => 'super_admin',
            'admin' => 'admin',
            'systemstaff' => 'system_staff',
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

            UserRole::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'scope_type' => 'system',
                    'scope_id' => self::ZERO_UUID,
                ],
                [
                    'granted_by' => null,
                ]
            );
        }
    }
}
