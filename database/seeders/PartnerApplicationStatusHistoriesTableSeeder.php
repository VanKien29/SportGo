<?php

namespace Database\Seeders;

use App\Models\PartnerApplication;
use App\Models\PartnerApplicationStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerApplicationStatusHistoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_application_status_histories') || ! Schema::hasTable('partner_applications')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();

        $rows = [
            'SportGo Cầu Giấy' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin bắt đầu kiểm tra hồ sơ.'],
                ['reviewing', 'approved_pending_contract', 'admin', 'Hồ sơ hợp lệ, chờ sinh hợp đồng.'],
                ['approved_pending_contract', 'completed', 'admin', 'Hợp đồng đã đủ chữ ký hai bên và có hiệu lực.'],
            ],
            'SportGo Thanh Xuân' => [
                [null, 'submitted', 'user', 'Người dùng gửi hồ sơ đăng ký đối tác.'],
            ],
            'SportGo Mỹ Đình' => [
                [null, 'submitted', 'user', 'Người dùng gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'system_staff', 'Nhân viên hệ thống đang kiểm tra hồ sơ pháp lý.'],
            ],
            'SportGo Hồ Tây' => [
                [null, 'submitted', 'user', 'Người dùng gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'system_staff', 'Nhân viên hệ thống kiểm tra hồ sơ.'],
                ['reviewing', 'need_supplement', 'system_staff', 'Cần bổ sung giấy tờ chứng minh quyền sử dụng mặt bằng.'],
            ],
            'SportGo Long Biên' => [
                [null, 'submitted', 'user', 'Người dùng gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin kiểm tra hồ sơ pháp lý.'],
                ['reviewing', 'rejected', 'admin', 'Thông tin pháp lý và tài khoản nhận tiền không khớp.'],
            ],
            'SportGo Đống Đa' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin kiểm tra hồ sơ.'],
                ['reviewing', 'approved_pending_contract', 'admin', 'Hồ sơ hợp lệ, chờ sinh hợp đồng.'],
                ['approved_pending_contract', 'contract_pending_owner_signature', 'admin', 'Hợp đồng đã sinh, đang chờ chủ sân ký.'],
            ],
            'SportGo Hà Đông' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin kiểm tra hồ sơ.'],
                ['reviewing', 'approved_pending_contract', 'admin', 'Hồ sơ hợp lệ, chờ sinh hợp đồng.'],
            ],
            'SportGo Ba Đình' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin kiểm tra hồ sơ.'],
                ['reviewing', 'approved_pending_contract', 'admin', 'Hồ sơ hợp lệ, chờ sinh hợp đồng.'],
                ['approved_pending_contract', 'contract_pending_owner_signature', 'admin', 'Hợp đồng đã sinh, đang chờ chủ sân ký.'],
                ['contract_pending_owner_signature', 'contract_pending_sportgo_signature', 'owner', 'Chủ sân đã ký, đang chờ SportGo ký xác nhận.'],
            ],
            'SportGo Tây Hồ' => [
                [null, 'submitted', 'user', 'Người dùng gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'cancelled', 'user', 'Người đăng ký hủy hồ sơ trước khi admin duyệt.'],
            ],
        ];

        foreach ($rows as $venueName => $historyRows) {
            $application = PartnerApplication::query()->where('venue_name', $venueName)->first();

            if (! $application) {
                continue;
            }

            foreach ($historyRows as [$oldStatus, $newStatus, $actorType, $reason]) {
                PartnerApplicationStatusHistory::query()->firstOrCreate(
                    [
                        'partner_application_id' => $application->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'reason' => $reason,
                    ],
                    [
                        'changed_by' => $actorType === 'system_staff' ? $staff?->id : ($actorType === 'admin' ? $admin?->id : $application->user_id),
                        'actor_type' => $actorType,
                        'metadata' => ['source' => 'PartnerApplicationStatusHistoriesTableSeeder'],
                        'created_at' => now()->subDays(10),
                    ],
                );
            }
        }
    }
}
