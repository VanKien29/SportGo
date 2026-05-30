<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SystemPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Tạo tài khoản Admin mẫu
        $admin = User::create([
            'username' => 'admin',
            'full_name' => 'Hệ thống Admin',
            'email' => 'admin@sportgo.com',
            'phone' => '0987654321',
            'password' => Hash::make('123456'),
            'status' => 'active',
        ]);

        // Tạo tài khoản người dùng mẫu
        User::create([
            'username' => 'user',
            'full_name' => 'Người dùng mẫu',
            'email' => 'user@sportgo.com',
            'phone' => '0123456789',
            'password' => Hash::make('123456'),
            'status' => 'active',
        ]);

        // Tạo chính sách mẫu loại general để popup hiển thị ngay
        SystemPolicy::create([
            'key' => 'general',
            'version' => 1,
            'title' => 'Điều khoản sử dụng SportGo',
            'content' => '<h3>Chào mừng bạn đến với SportGo!</h3><p>Đây là nội dung chính sách hệ thống phiên bản v1. Bạn cần chấp thuận để tiếp tục sử dụng dịch vụ.</p><ul><li>Bảo mật thông tin cá nhân.</li><li>Quy tắc đặt sân và hoàn tiền.</li><li>Hành vi ứng xử trong cộng đồng.</li></ul>',
            'type' => 'general',
            'is_active' => true,
            'effective_from' => now(),
            'created_by' => $admin->id,
        ]);
    }
}
