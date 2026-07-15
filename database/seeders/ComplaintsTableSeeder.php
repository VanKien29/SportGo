<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ComplaintsTableSeeder extends Seeder
{
    public const SYSTEM_COMPLAINT_CONTENT = 'Khách cần SportGo kiểm tra thời gian cập nhật trạng thái hoàn tiền.';
    public const VENUE_COMPLAINT_CONTENT = 'Khách phản ánh sân mở cửa trễ 10 phút so với giờ đặt.';

    public function run(): void
    {
        if (! Schema::hasTable('complaints') || ! Schema::hasTable('bookings')) {
            return;
        }

        $customer = User::query()->where('username', 'user')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $booking = Booking::query()->where('booking_code', 'BOOKING_0001')->first();

        if (! $customer || ! $booking) {
            return;
        }

        Complaint::query()->updateOrCreate(
            [
                'booking_id' => null,
                'customer_id' => $customer->id,
                'content' => self::SYSTEM_COMPLAINT_CONTENT,
            ],
            [
                'complaint_type' => 'system',
                'venue_cluster_id' => null,
                'status' => 'open',
                'assigned_to' => $staff?->id,
                'resolved_by' => null,
                'resolve_note' => null,
                'status_reason' => null,
                'resolved_at' => null,
            ],
        );

        Complaint::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'content' => self::VENUE_COMPLAINT_CONTENT,
            ],
            [
                'complaint_type' => 'venue',
                'venue_cluster_id' => $booking->venue_cluster_id,
                'status' => 'resolved',
                'assigned_to' => $staff?->id,
                'resolved_by' => $staff?->id,
                'resolve_note' => 'Nhân viên sân đã xác nhận sự cố và tặng khách 1 lượt nước miễn phí.',
                'status_reason' => null,
                'resolved_at' => now()->subHours(5),
            ],
        );
    }
}
