<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueStaffAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const ZERO_UUID = '00000000-0000-0000-0000-000000000000';

    public function run(): void
    {
        $roles = $this->seedRoles();
        $permissions = $this->seedPermissions();
        $this->seedRolePermissions($roles, $permissions);
        $users = $this->seedUsers($roles);
        $this->seedOwnerTestData($users);
    }

    private function seedRoles(): array
    {
        DB::table('user_roles')
            ->whereIn('role_id', Role::query()->where('name', 'player')->pluck('id'))
            ->delete();

        Role::query()->where('name', 'player')->delete();

        $roles = [
            'super_admin' => ['Super Admin', 'Toàn quyền hệ thống'],
            'admin' => ['Admin', 'Quản trị hệ thống'],
            'system_staff' => ['System Staff', 'Nhân viên hệ thống'],
            'venue_owner' => ['Venue Owner', 'Chủ sân'],
            'venue_staff' => ['Venue Staff', 'Nhân viên sân'],
            'user' => ['User', 'Người dùng thường'],
        ];

        return collect($roles)->mapWithKeys(function (array $role, string $name): array {
            $model = Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $role[0],
                    'description' => $role[1],
                    'is_system' => true,
                ]
            );

            return [$name => $model];
        })->all();
    }

    private function seedPermissions(): array
    {
        $permissions = [
            'dashboard.view' => ['Xem dashboard', 'dashboard'],
            'user.view' => ['Xem tài khoản', 'user'],
            'user.lock' => ['Khóa tài khoản', 'user'],
            'user.unlock' => ['Mở khóa tài khoản', 'user'],
            'auth.login' => ['Đăng nhập', 'auth'],
            'venue.manage' => ['Quản lý sân', 'venue'],
            'booking.manage' => ['Quản lý booking', 'booking'],
            'booking.view' => ['Xem booking', 'booking'],
            'profile.view' => ['Xem hồ sơ', 'profile'],
            'profile.update' => ['Cập nhật hồ sơ', 'profile'],
        ];

        return collect($permissions)->mapWithKeys(function (array $permission, string $code): array {
            $model = Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $permission[0],
                    'group_name' => $permission[1],
                    'created_at' => now(),
                ]
            );

            return [$code => $model];
        })->all();
    }

    private function seedRolePermissions(array $roles, array $permissions): void
    {
        $map = [
            'super_admin' => array_keys($permissions),
            'admin' => [
                'dashboard.view',
                'user.view',
                'user.lock',
                'user.unlock',
                'auth.login',
                'profile.view',
                'profile.update',
            ],
            'system_staff' => [
                'dashboard.view',
                'user.view',
                'user.lock',
                'user.unlock',
                'auth.login',
                'profile.view',
                'profile.update',
            ],
            'venue_owner' => [
                'dashboard.view',
                'auth.login',
                'venue.manage',
                'booking.manage',
                'booking.view',
                'profile.view',
                'profile.update',
            ],
            'venue_staff' => [
                'dashboard.view',
                'auth.login',
                'booking.manage',
                'booking.view',
                'profile.view',
                'profile.update',
            ],
            'user' => [
                'auth.login',
                'booking.view',
                'profile.view',
                'profile.update',
            ],
        ];

        foreach ($map as $roleName => $permissionCodes) {
            foreach ($permissionCodes as $code) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roles[$roleName]->id,
                    'permission_id' => $permissions[$code]->id,
                ]);
            }
        }
    }

    private function seedUsers(array $roles): array
    {
        $accounts = [
            'super_admin' => ['superadmin', 'SportGo Super Admin', 'superadmin@sportgo.vn', '0910000001'],
            'admin' => ['admin', 'SportGo Admin', 'admin@sportgo.vn', '0910000002'],
            'system_staff' => ['systemstaff', 'SportGo System Staff', 'systemstaff@sportgo.vn', '0910000003'],
            'venue_owner' => ['owner', 'SportGo Venue Owner', 'owner@sportgo.vn', '0910000004'],
            'venue_staff' => ['venuestaff', 'SportGo Venue Staff', 'venuestaff@sportgo.vn', '0910000005'],
            'user' => ['user', 'SportGo User', 'user@sportgo.vn', '0910000006'],
        ];

        return collect($accounts)->mapWithKeys(function (array $account, string $roleName) use ($roles): array {
            [$username, $fullName, $email, $phone] = $account;

            $user = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make('12345678'),
                    'status' => 'active',
                    'verification_channel' => 'email',
                    'email_verified_at' => now(),
                    'phone_verified_at' => null,
                    'lock_type' => null,
                    'status_reason' => null,
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                ]
            );

            UserRole::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $roles[$roleName]->id,
                    'scope_type' => 'system',
                    'scope_id' => self::ZERO_UUID,
                ],
                [
                    'granted_by' => null,
                    'created_at' => now(),
                ]
            );

            return [$roleName => $user];
        })->all();
    }

    private function seedOwnerTestData(array $users): void
    {
        if (! isset($users['venue_owner'], $users['venue_staff'])) {
            return;
        }

        $courtType = CourtType::query()->updateOrCreate(
            ['name' => 'Badminton'],
            [
                'description' => 'Sân cầu lông',
                'player_count' => 4,
                'is_active' => true,
            ]
        );

        $cluster = VenueCluster::query()->updateOrCreate(
            ['slug' => 'sportgo-test-cluster'],
            [
                'owner_id' => $users['venue_owner']->id,
                'name' => 'SportGo Test Cluster',
                'description' => 'Cụm sân mẫu để test màn chủ sân',
                'phone_contact' => '0900000100',
                'address' => 'Ha Noi',
                'map_url' => null,
                'latitude' => 21.0278000,
                'longitude' => 105.8342000,
                'amenities' => ['parking', 'lighting'],
                'status' => 'active',
                'status_reason' => null,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
                'rating_avg' => 0,
                'rating_count' => 0,
            ]
        );

        VenueStaffAssignment::query()->updateOrCreate(
            [
                'user_id' => $users['venue_staff']->id,
                'venue_cluster_id' => $cluster->id,
                'scope_key' => 'court_type:'.$courtType->id,
            ],
            [
                'scope_type' => 'court_type',
                'court_type_id' => $courtType->id,
                'assigned_by' => $users['venue_owner']->id,
                'status' => 'active',
            ]
        );
    }
}
