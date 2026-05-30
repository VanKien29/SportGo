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
            ['owner', 'Chủ sân SportGo', 'owner@sportgo.vn', '0901000004'],
            ['venuestaff', 'Nhân viên sân SportGo', 'venuestaff@sportgo.vn', '0901000005'],
            ['user', 'Người dùng SportGo', 'user@sportgo.vn', '0901000006'],
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
