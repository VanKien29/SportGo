<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CommunityPostsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('community_posts') || ! Schema::hasTable('users')) {
            return;
        }

        $nam = User::query()->where('username', 'user')->first();
        $linh = User::query()->where('username', 'user1')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();

        if (! $nam || ! $linh) {
            return;
        }

        $posts = [
            [
                $nam,
                'Cuối tuần này nhóm mình chơi cầu lông tại Green Sport Ba Đình, ai muốn giao lưu nhẹ nhàng có thể bình luận để ghép đội.',
                'published',
                $staff?->id,
                now()->subDays(2),
                null,
                32,
                5,
                1,
            ],
            [
                $linh,
                'Mình đang tìm thêm bạn tập pickleball buổi sáng, ưu tiên người mới muốn tập đều mỗi tuần.',
                'published',
                $staff?->id,
                now()->subDay(),
                null,
                18,
                3,
                0,
            ],
        ];

        foreach ($posts as [$author, $content, $status, $reviewedBy, $reviewedAt, $reason, $views, $likes, $comments]) {
            CommunityPost::query()->updateOrCreate(
                [
                    'author_id' => $author->id,
                    'content' => $content,
                ],
                [
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'status_reason' => $reason,
                    'view_count' => $views,
                    'like_count' => $likes,
                    'comment_count' => $comments,
                ]
            );
        }
    }
}
