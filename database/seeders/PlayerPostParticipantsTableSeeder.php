<?php

namespace Database\Seeders;

use App\Models\PlayerPost;
use App\Models\PlayerPostParticipant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PlayerPostParticipantsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('player_post_participants') || ! Schema::hasTable('player_posts') || ! Schema::hasTable('users')) {
            return;
        }

        $users = User::query()
            ->whereIn('username', ['user', 'user1', 'user2', 'user3', 'user4'])
            ->get()
            ->keyBy('username');

        if ($users->isEmpty()) {
            return;
        }

        $posts = PlayerPost::query()
            ->whereIn('title', [
                'Tìm 2 bạn giao lưu cầu lông tối nay',
                'Pickleball cần thêm 1 bạn đánh đôi',
                'Giao lưu cầu lông sau giờ làm',
            ])
            ->get()
            ->keyBy('title');

        $participants = [
            [
                'Tìm 2 bạn giao lưu cầu lông tối nay',
                'user1',
                'pending',
                'Mình đánh được tối nay, trình trung bình khá.',
                null,
            ],
            [
                'Tìm 2 bạn giao lưu cầu lông tối nay',
                'user2',
                'approved',
                'Mình tham gia 1 slot nhé.',
                now()->subHours(2),
            ],
            [
                'Pickleball cần thêm 1 bạn đánh đôi',
                'user3',
                'pending',
                'Mình mới chơi được vài tháng, nếu phù hợp thì cho mình tham gia.',
                null,
            ],
            [
                'Giao lưu cầu lông sau giờ làm',
                'user4',
                'approved',
                'Đã xác nhận tham gia.',
                now()->subHours(5),
            ],
            [
                'Giao lưu cầu lông sau giờ làm',
                'user1',
                'rejected',
                'Hôm đó mình bận, xin hủy.',
                now()->subHours(4),
            ],
        ];

        foreach ($participants as [$postTitle, $username, $status, $message, $respondedAt]) {
            $post = $posts[$postTitle] ?? null;
            $user = $users[$username] ?? null;

            if (! $post || ! $user || $post->author_id === $user->id) {
                continue;
            }

            PlayerPostParticipant::query()->updateOrCreate(
                [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => $status,
                    'message' => $message,
                    'responded_at' => $respondedAt,
                ]
            );
        }
    }
}
