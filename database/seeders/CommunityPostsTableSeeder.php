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

        // Thêm dữ liệu fake
        $user1 = User::query()->where('username', 'user1')->first();
        $user2 = User::query()->where('username', 'user2')->first();

        if ($user1) {
            CommunityPost::query()->updateOrCreate(
                ['content' => "Tuyển gấp 2 bạn đánh giao lưu tối nay lúc 20h tại sân Cầu Giấy. Trình độ trung bình khá trở lên, vui vẻ hòa đồng. Ai đi được comment nhé!"],
                [
                    'author_id' => $user1->id,
                    'status' => 'published',
                    'view_count' => 120,
                    'like_count' => 5,
                    'comment_count' => 4,
                ]
            );
        }

        if ($user2) {
            CommunityPost::query()->updateOrCreate(
                ['content' => "Góc thanh lý: Mình cần pass lại vợt Yonex Astrox 88D Pro tình trạng 95%, giá cả học sinh sinh viên. Ib để ép giá :))"],
                [
                    'author_id' => $user2->id,
                    'status' => 'published',
                    'view_count' => 85,
                    'like_count' => 12,
                    'comment_count' => 2,
                ]
            );
        }
    }
}
