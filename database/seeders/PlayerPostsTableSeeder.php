<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\PlayerPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PlayerPostsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('player_posts') || ! Schema::hasTable('bookings') || ! Schema::hasTable('users')) {
            return;
        }

        $users = User::query()
            ->whereIn('username', ['user', 'user1', 'user2', 'user3', 'user4'])
            ->get()
            ->keyBy('username');

        if ($users->isEmpty()) {
            return;
        }

        $bookings = Booking::query()
            ->whereIn('booking_code', [
                'BKADMPAID1',
                'BKADMPEND1',
                'BKADMCOUN1',
                'BKADMREF1',
                'BKADMREFPROC1',
            ])
            ->get()
            ->keyBy('booking_code');

        if ($bookings->isEmpty()) {
            return;
        }

        $posts = [
            [
                'BKADMPAID1',
                'user',
                'Tìm 2 bạn giao lưu cầu lông tối nay',
                'Nhóm mình đã đặt sân A1, trình độ trung bình khá, ưu tiên vui vẻ đúng giờ.',
                2,
                60000,
                'open',
                null,
                now()->subHours(3),
            ],
            [
                'BKADMPEND1',
                'user1',
                'Pickleball cần thêm 1 bạn đánh đôi',
                'Sân P1, đã có 3 người. Cần thêm 1 bạn biết luật cơ bản để đánh đủ set.',
                1,
                30000,
                'open',
                null,
                now()->subHours(2),
            ],
            [
                'BKADMCOUN1',
                'user2',
                'Giao lưu cầu lông sau giờ làm',
                'Nhóm đặt tại quầy, còn thiếu 2 người. Có thể chia tiền sân sau buổi chơi.',
                2,
                40000,
                'full',
                null,
                now()->subDay(),
            ],
            [
                'BKADMREF1',
                'user3',
                'Bài giao lưu đã hủy do lịch thay đổi',
                'Lịch cũ không còn phù hợp nên nhóm đã hủy buổi giao lưu này.',
                1,
                50000,
                'cancelled',
                'Người đăng hủy vì đổi lịch cá nhân.',
                now()->subDays(2),
            ],
            [
                'BKADMREFPROC1',
                'user4',
                'Bài giao lưu đã đóng bởi chủ sân',
                'Bài mẫu để kiểm tra trạng thái đóng và lý do xử lý trên màn owner.',
                3,
                45000,
                'closed',
                'Ẩn bởi chủ sân. Lý do: Nội dung không còn phù hợp với lịch hiện tại.',
                now()->subDays(4),
            ],
        ];

        foreach ($posts as [$bookingCode, $username, $title, $description, $neededPlayers, $costPerPlayer, $status, $statusReason, $createdAt]) {
            $booking = $bookings[$bookingCode] ?? null;
            $author = $users[$username] ?? $users->first();

            if (! $booking || ! $author) {
                continue;
            }

            PlayerPost::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'title' => $title,
                ],
                [
                    'author_id' => $author->id,
                    'description' => $description,
                    'needed_players' => $neededPlayers,
                    'cost_per_player' => $costPerPlayer,
                    'status' => $status,
                    'status_reason' => $statusReason,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }
    }
}
