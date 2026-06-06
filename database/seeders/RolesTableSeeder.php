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
            'super_admin' => ['Super Admin', 'Toàn quyền quản trị hệ thống SportGo. Chỉ nhóm này được tạo/gán Admin.'],
            'admin' => ['Admin', 'Quản trị vận hành hệ thống, không được tạo hoặc gán Super Admin/Admin khác.'],
            'system_staff' => ['Nhân viên hệ thống', 'Nhóm nền cho nhân sự vận hành nội bộ SportGo.'],
            'content_moderator' => ['Kiểm duyệt bài viết', 'Duyệt, từ chối, ẩn nội dung và xử lý báo cáo bài viết.'],
            'complaint_handler' => ['Xử lý khiếu nại', 'Tiếp nhận, phản hồi và giải quyết khiếu nại của người dùng.'],
            'venue_manager' => ['Quản lý cụm sân', 'Theo dõi, duyệt, khóa/mở khóa và cập nhật trạng thái cụm sân.'],
            'partner_manager' => ['Quản lý đối tác', 'Xử lý hồ sơ đăng ký chủ sân và giấy tờ liên quan.'],
            'booking_support' => ['Quản lý booking hỗ trợ', 'Theo dõi booking và hỗ trợ cập nhật trạng thái theo phạm vi được cấp.'],
            'finance_operator' => ['Tài chính / Đối soát', 'Xử lý thanh toán, hoàn tiền, ví và đối soát tài chính.'],
            'policy_manager' => ['Quản lý chính sách', 'Tạo phiên bản, cấu hình rule, publish và theo dõi lịch sử chính sách.'],
            'staff_manager' => ['Quản lý nhân sự hệ thống', 'Tạo và gán nhóm quyền thấp hơn Admin cho nhân sự hệ thống.'],

            'venue_owner' => ['Chủ sân', 'Vai trò nghiệp vụ cố định của chủ cụm sân, không cấu hình tại màn nhóm quyền admin.'],
            'venue_staff' => ['Nhân viên sân', 'Vai trò nghiệp vụ cố định của nhân viên sân, không cấu hình tại màn nhóm quyền admin.'],
            'user' => ['Người dùng', 'Vai trò nghiệp vụ cố định của khách đặt sân, không cấu hình tại màn nhóm quyền admin.'],
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
