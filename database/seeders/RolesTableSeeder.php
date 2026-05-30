<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = [
            'super_admin' => ['Super Admin', 'Toàn quyền quản trị hệ thống SportGo.'],
            'admin' => ['Admin', 'Quản trị vận hành hệ thống.'],
            'system_staff' => ['Nhân viên hệ thống', 'Nhân viên hỗ trợ vận hành SportGo.'],
            'venue_owner' => ['Chủ sân', 'Chủ sở hữu và quản lý cụm sân.'],
            'venue_staff' => ['Nhân viên sân', 'Nhân viên hỗ trợ vận hành cụm sân.'],
            'user' => ['Người dùng', 'Người dùng đặt sân trên SportGo.'],
        ];

        foreach ($roles as $name => [$displayName, $description]) {
            Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => $description,
                    'is_system' => true,
                ]
            );
        }
    }
}
