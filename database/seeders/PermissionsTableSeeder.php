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

            'user.view' => ['Xem tài khoản', 'Tài khoản'],
            'user.lock' => ['Khóa tài khoản', 'Tài khoản'],
            'user.unlock' => ['Mở khóa tài khoản', 'Tài khoản'],
            'staff.view' => ['Xem nhân sự hệ thống', 'Tài khoản nhân sự'],
            'staff.create' => ['Tạo nhân sự hệ thống', 'Tài khoản nhân sự'],
            'staff.assign_role' => ['Gán nhóm quyền nhân sự', 'Tài khoản nhân sự'],
            'staff.lock' => ['Khóa/mở khóa nhân sự', 'Tài khoản nhân sự'],

            'role.view' => ['Xem nhóm quyền', 'Phân quyền'],
            'role.create' => ['Tạo nhóm quyền', 'Phân quyền'],
            'role.update' => ['Cập nhật nhóm quyền', 'Phân quyền'],
            'role.delete' => ['Xóa nhóm quyền', 'Phân quyền'],
            'role.permission.manage' => ['Quản lý quyền của nhóm', 'Phân quyền'],
            'role.manage' => ['Quản lý vai trò', 'Phân quyền'],

            'policy.view' => ['Xem chính sách', 'Chính sách'],
            'policy.create' => ['Tạo chính sách', 'Chính sách'],
            'policy.update' => ['Cập nhật chính sách', 'Chính sách'],
            'policy.publish' => ['Kích hoạt chính sách', 'Chính sách'],
            'policy.rule.manage' => ['Quản lý quy tắc chính sách', 'Chính sách'],

            'venue.view' => ['Xem cụm sân', 'Cụm sân'],
            'venue.manage' => ['Quản lý cụm sân', 'Cụm sân'],
            'venue.lock' => ['Khóa/mở khóa cụm sân', 'Cụm sân'],
            'partner.view' => ['Xem hồ sơ đối tác', 'Đối tác'],
            'partner.review' => ['Duyệt/từ chối hồ sơ đối tác', 'Đối tác'],
            'court.view' => ['Xem sân con', 'Sân con'],
            'court.manage' => ['Quản lý sân con', 'Sân con'],

            'booking.view' => ['Xem đặt sân', 'Đặt sân'],
            'booking.manage' => ['Quản lý đặt sân', 'Đặt sân'],
            'booking.support' => ['Hỗ trợ xử lý booking', 'Đặt sân'],

            'price.view' => ['Xem bảng giá', 'Bảng giá'],
            'price.manage' => ['Quản lý bảng giá', 'Bảng giá'],

            'content.view' => ['Xem nội dung', 'Nội dung'],
            'content.manage' => ['Quản lý nội dung', 'Nội dung'],
            'moderation.view' => ['Xem kiểm duyệt', 'Kiểm duyệt'],
            'moderation.manage' => ['Quản lý kiểm duyệt', 'Kiểm duyệt'],
            'moderation.approve' => ['Duyệt nội dung', 'Kiểm duyệt'],
            'moderation.reject' => ['Từ chối nội dung', 'Kiểm duyệt'],
            'report.view' => ['Xem báo cáo vi phạm', 'Báo cáo'],
            'report.resolve' => ['Xử lý báo cáo vi phạm', 'Báo cáo'],
            'complaint.view' => ['Xem khiếu nại', 'Khiếu nại'],
            'complaint.handle' => ['Xử lý khiếu nại', 'Khiếu nại'],

            'refund.view' => ['Xem yêu cầu hoàn tiền', 'Tài chính'],
            'refund.approve' => ['Duyệt hoàn tiền', 'Tài chính'],
            'payment.view' => ['Xem thanh toán', 'Tài chính'],
            'payment.manage' => ['Quản lý thanh toán', 'Tài chính'],
            'wallet.view' => ['Xem ví người dùng/chủ sân', 'Tài chính'],
            'withdrawal.manage' => ['Xử lý yêu cầu rút tiền', 'Tài chính'],
            'reconciliation.manage' => ['Xử lý đối soát', 'Tài chính'],

            'audit.view' => ['Xem nhật ký hệ thống', 'Audit'],
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
