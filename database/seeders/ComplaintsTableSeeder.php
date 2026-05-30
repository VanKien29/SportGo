<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ComplaintsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('complaints') || ! Schema::hasTable('bookings')) {
            return;
        }

        $customer = User::query()->where('username', 'user')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $booking = Booking::query()->where('booking_code', 'BKADMPAID1')->first();

        if (! $customer || ! $booking) {
            return;
        }

        Complaint::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'content' => 'Khách phản ánh sân mở cửa trễ 10 phút so với giờ đặt.',
            ],
            [
                'complaint_type' => 'venue',
                'venue_cluster_id' => $booking->venue_cluster_id,
                'status' => 'processing',
                'assigned_to' => $staff?->id,
                'resolved_by' => null,
                'resolve_note' => null,
                'status_reason' => null,
                'resolved_at' => null,
            ]
        );

        Complaint::query()->updateOrCreate(
            [
                'booking_id' => null,
                'customer_id' => $customer->id,
                'content' => 'Khách cần hỗ trợ kiểm tra trạng thái hoàn tiền.',
            ],
            [
                'complaint_type' => 'system',
                'venue_cluster_id' => null,
                'status' => 'resolved',
                'assigned_to' => $staff?->id,
                'resolved_by' => $staff?->id,
                'resolve_note' => 'Đã hướng dẫn khách theo dõi yêu cầu hoàn tiền trong mục lịch sử.',
                'status_reason' => null,
                'resolved_at' => now()->subHours(5),
            ]
        );
    }
}
