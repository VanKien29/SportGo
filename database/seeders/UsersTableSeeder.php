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
            ['admin', 'Admin Vận Hành SportGo', 'admin@sportgo.vn', '0901000002'],
            ['systemstaff', 'Nhân viên hệ thống SportGo', 'systemstaff@sportgo.vn', '0901000003'],
            ['moderator', 'Kiểm duyệt viên SportGo', 'moderator@sportgo.vn', '0901000007'],
            ['venue_manager', 'Quản lý cụm sân SportGo', 'venue.manager@sportgo.vn', '0901000008'],
            ['finance', 'Tài chính SportGo', 'finance@sportgo.vn', '0901000009'],
            ['policy_manager', 'Quản lý chính sách SportGo', 'policy@sportgo.vn', '0901000010'],
            ['staff_manager', 'Quản lý nhân sự SportGo', 'staff.manager@sportgo.vn', '0901000011'],
            ['owner', 'Chủ sân Nguyễn Minh Quân', 'owner@sportgo.vn', '0901000004'],
            ['owner_sun', 'Chủ sân Lê Hoàng Anh', 'owner.sun@sportgo.vn', '0901000012'],
            ['venuestaff', 'Nhân viên sân Green Sport', 'venuestaff@sportgo.vn', '0901000005'],
            ['user', 'Người dùng Trần Hoàng Nam', 'user@sportgo.vn', '0901000006'],
            ['user1', 'Người dùng Phạm Ngọc Linh', 'user1@example.com', '0901000021'],
            ['user2', 'Nguoi dung Do Minh Chau', 'user2@example.com', '0901000022'],
            ['user3', 'Nguoi dung Hoang Thu Ha', 'user3@example.com', '0901000023'],
            ['user4', 'Nguoi dung Le Bao Long', 'user4@example.com', '0901000024'],
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
