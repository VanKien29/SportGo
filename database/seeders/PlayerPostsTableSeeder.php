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
            ->whereIn('username', ['user', 'user1', 'user2'])
            ->get()
            ->keyBy('username');

        $bookings = Booking::query()
            ->whereIn('booking_code', ['BOOKING_0001', 'BOOKING_0002'])
            ->get()
            ->keyBy('booking_code');

        if ($users->isEmpty() || $bookings->isEmpty()) {
            return;
        }

        $posts = [
            [
                'BOOKING_0001',
                'user',
                'Tìm 2 bạn giao lưu cầu lông tối nay',
                'Nhóm mình đã đặt sân A1 tại Green Sport Ba Đình, trình độ trung bình khá, ưu tiên vui vẻ đúng giờ.',
                2,
                60000,
                'open',
                null,
                now()->subHours(3),
            ],
            [
                'BOOKING_0002',
                'user1',
                'Pickleball cần thêm 1 bạn đánh đôi',
                'Sân P1 tại Green Sport Ba Đình đã có 3 người. Cần thêm 1 bạn biết luật cơ bản để đánh đủ set.',
                1,
                30000,
                'open',
                null,
                now()->subHours(2),
            ],
        ];

        foreach ($posts as [$bookingCode, $username, $title, $description, $neededPlayers, $costPerPlayer, $status, $statusReason, $createdAt]) {
            $booking = $bookings[$bookingCode] ?? null;
            $author = $users[$username] ?? null;

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
