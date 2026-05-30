<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerApplication;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class NotificationsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('users')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $user = User::query()->where('username', 'user')->first();

        if (! $user) {
            return;
        }

        $this->notify(
            $user->id,
            'partner_application.reviewing',
            'Hồ sơ chủ sân đang được xem xét',
            'Hồ sơ SportGo Thanh Xuân đã được chuyển sang trạng thái đang xem xét.',
            PartnerApplication::class,
            PartnerApplication::query()->where('venue_name', 'SportGo Thanh Xuân')->value('id')
        );

        $this->notify(
            $user->id,
            'refund.pending',
            'Yêu cầu hoàn tiền đang chờ xác nhận',
            'Yêu cầu hoàn tiền của bạn đang chờ admin xác nhận.',
            Refund::class,
            Refund::query()->value('id')
        );

        $this->notify(
            $user->id,
            'complaint.processing',
            'Khiếu nại đang được xử lý',
            'Khiếu nại của bạn đã được nhân viên hệ thống tiếp nhận.',
            Complaint::class,
            Complaint::query()->where('status', 'processing')->value('id')
        );

        if ($owner) {
            $this->notify(
                $owner->id,
                'withdrawal.completed',
                'Yêu cầu rút tiền đã hoàn tất',
                'SportGo đã ghi nhận hoàn tất yêu cầu rút tiền WRADMCOMP1.',
                OwnerWithdrawalRequest::class,
                OwnerWithdrawalRequest::query()->where('request_code', 'WRADMCOMP1')->value('id')
            );
        }
    }

    private function notify(string $userId, string $type, string $title, string $body, string $referenceType, ?string $referenceId): void
    {
        if (! $referenceId) {
            return;
        }

        Notification::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
            [
                'title' => $title,
                'body' => $body,
                'data' => ['source' => 'seed'],
                'is_read' => false,
                'read_at' => null,
            ]
        );
    }
}
