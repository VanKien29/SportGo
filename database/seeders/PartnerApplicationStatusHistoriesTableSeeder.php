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

        $rows = [
            'Green Sport Ba Đình' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ đăng ký đối tác.'],
                ['submitted', 'reviewing', 'admin', 'Admin bắt đầu kiểm tra hồ sơ.'],
                ['reviewing', 'approved_pending_contract', 'admin', 'Hồ sơ hợp lệ, chờ sinh hợp đồng.'],
                ['approved_pending_contract', 'completed', 'admin', 'Hợp đồng đã đủ chữ ký hai bên và có hiệu lực.'],
            ],
            'Sun Sport Cầu Giấy' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ, đang chờ SportGo tiếp nhận.'],
            ],
            'Victory Sport Hà Đông' => [
                [null, 'submitted', 'owner', 'Chủ sân gửi hồ sơ tham chiếu cho cụm sân mẫu.'],
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
                        'changed_by' => $actorType === 'admin' ? $admin?->id : $application->user_id,
                        'actor_type' => $actorType,
                        'metadata' => ['source' => 'PartnerApplicationStatusHistoriesTableSeeder'],
                        'created_at' => now()->subDays(10),
                    ],
                );
            }
        }
    }
}
