<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()->where('code', 'auth.login')->delete();

        $permissions = [
            'dashboard.view' => ['Xem dashboard', 'Dashboard'],
            'profile.view' => ['Xem hồ sơ cá nhân', 'Hồ sơ'],
            'profile.update' => ['Cập nhật hồ sơ cá nhân', 'Hồ sơ'],
            'user.view' => ['Xem người dùng', 'Người dùng'],
            'user.lock' => ['Khóa người dùng', 'Người dùng'],
            'user.unlock' => ['Mở khóa người dùng', 'Người dùng'],
            'role.view' => ['Xem vai trò', 'Phân quyền'],
            'role.manage' => ['Quản lý vai trò', 'Phân quyền'],
            'venue.view' => ['Xem cụm sân', 'Cụm sân'],
            'venue.manage' => ['Quản lý cụm sân', 'Cụm sân'],
            'court.view' => ['Xem sân con', 'Sân con'],
            'court.manage' => ['Quản lý sân con', 'Sân con'],
            'booking.view' => ['Xem đặt sân', 'Đặt sân'],
            'booking.manage' => ['Quản lý đặt sân', 'Đặt sân'],
            'price.view' => ['Xem bảng giá', 'Bảng giá'],
            'price.manage' => ['Quản lý bảng giá', 'Bảng giá'],
            'content.view' => ['Xem nội dung', 'Nội dung'],
            'content.manage' => ['Quản lý nội dung', 'Nội dung'],
            'moderation.view' => ['Xem kiểm duyệt', 'Kiểm duyệt'],
            'moderation.manage' => ['Quản lý kiểm duyệt', 'Kiểm duyệt'],
            'audit.view' => ['Xem nhật ký hệ thống', 'Audit'],
            'banner.view' => ['Xem Banner', 'Hệ thống'],
            'banner.manage' => ['Quản lý Banner', 'Hệ thống'],
            'partner_application.view' => ['Xem đơn đăng ký chủ sân', 'Đối tác'],
            'partner_application.manage' => ['Xử lý đơn đăng ký chủ sân', 'Đối tác'],
        ];

        foreach ($permissions as $code => [$name, $groupName]) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'group_name' => $groupName,
                ]
            );
        }
    }
}
