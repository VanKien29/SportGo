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
        $ownerSun = User::query()->where('username', 'owner_sun')->first();
        $customer = User::query()->where('username', 'user')->first();

        if (! $customer) {
            return;
        }

        if ($ownerSun) {
            $this->notify(
                $ownerSun->id,
                'partner_application.pending',
                'Hồ sơ đối tác đang chờ duyệt',
                'Hồ sơ Sun Sport Cầu Giấy đã được ghi nhận và đang chờ SportGo duyệt.',
                PartnerApplication::class,
                PartnerApplication::query()->where('venue_name', 'Sun Sport Cầu Giấy')->value('id'),
            );
        }

        $this->notify(
            $customer->id,
            'refund.pending_owner_confirmation',
            'Yêu cầu hoàn tiền đang chờ xác nhận',
            'Yêu cầu hoàn tiền cho BOOKING_0003 đang chờ chủ sân xác nhận theo quy trình hiện tại.',
            Refund::class,
            Refund::query()
                ->whereHas('booking', fn ($query) => $query->where('booking_code', 'BOOKING_0003'))
                ->value('id'),
        );

        $this->notify(
            $customer->id,
            'complaint.open',
            'Khiếu nại hệ thống đang chờ xử lý',
            'SportGo đã ghi nhận khiếu nại của bạn và sẽ phản hồi sau khi kiểm tra.',
            Complaint::class,
            Complaint::query()->where('status', 'open')->value('id'),
        );

        if ($owner) {
            $this->notify(
                $owner->id,
                'withdrawal.completed',
                'Yêu cầu rút tiền đã hoàn tất',
                'SportGo đã ghi nhận hoàn tất yêu cầu rút tiền WD_OWNER_0002.',
                OwnerWithdrawalRequest::class,
                OwnerWithdrawalRequest::query()->where('request_code', 'WD_OWNER_0002')->value('id'),
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
            ],
        );
    }
}
