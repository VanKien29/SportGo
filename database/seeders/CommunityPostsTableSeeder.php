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

        $user = User::query()->where('username', 'user')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();

        if (! $user) {
            return;
        }

        $posts = [
            [
                'Tìm đội giao lưu cầu lông tối thứ 7 tại Cầu Giấy.',
                'published',
                $staff?->id,
                now()->subDays(2),
                null,
                32,
                5,
                1,
            ],
            [
                'Bài viết đang chờ duyệt: tuyển thêm người chơi pickleball cuối tuần.',
                'pending_review',
                null,
                null,
                null,
                0,
                0,
                0,
            ],
            [
                'Bài viết mẫu đã bị ẩn do bị báo cáo spam.',
                'hidden',
                $staff?->id,
                now()->subDay(),
                'Nội dung bị nhiều người báo cáo là spam.',
                12,
                1,
                0,
            ],
        ];

        foreach ($posts as [$content, $status, $reviewedBy, $reviewedAt, $reason, $views, $likes, $comments]) {
            CommunityPost::query()->updateOrCreate(
                [
                    'author_id' => $user->id,
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
