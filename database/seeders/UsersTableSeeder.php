<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $users = [
            ['superadmin', 'Super Admin SportGo', 'superadmin@sportgo.vn', '0901000001'],
            ['admin', 'Admin SportGo', 'admin@sportgo.vn', '0901000002'],
            ['systemstaff', 'Nhân viên hệ thống SportGo', 'systemstaff@sportgo.vn', '0901000003'],
            ['moderator', 'Kiểm duyệt viên SportGo', 'moderator@sportgo.vn', '0901000007'],
            ['venue_manager', 'Quản lý cụm sân SportGo', 'venue.manager@sportgo.vn', '0901000008'],
            ['finance', 'Tài chính SportGo', 'finance@sportgo.vn', '0901000009'],
            ['policy_manager', 'Quản lý chính sách SportGo', 'policy@sportgo.vn', '0901000010'],
            ['staff_manager', 'Quản lý nhân sự SportGo', 'staff.manager@sportgo.vn', '0901000011'],
            ['owner', 'Chủ sân SportGo', 'owner@sportgo.vn', '0901000004'],
            ['venuestaff', 'Nhân viên sân SportGo', 'venuestaff@sportgo.vn', '0901000005'],
            ['user', 'Người dùng SportGo', 'user@sportgo.vn', '0901000006'],
            ['user1', 'Người dùng fake 1', 'user1@example.com', '0901000021'],
            ['user2', 'Người dùng fake 2', 'user2@example.com', '0901000022'],
            ['user3', 'Người dùng fake 3', 'user3@example.com', '0901000023'],
            ['user4', 'Người dùng fake 4', 'user4@example.com', '0901000024'],
            ['testlocked', 'Người dùng test tự khóa', 'testlocked@example.com', '0901000099'],
        ];

        foreach ($users as [$username, $fullName, $email, $phone]) {
            User::query()->updateOrCreate(
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
        }
    }
}
