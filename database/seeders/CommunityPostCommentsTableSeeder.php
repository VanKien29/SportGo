<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CommunityPostCommentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('community_post_comments') || ! Schema::hasTable('community_posts')) {
            return;
        }

        $user = User::query()->where('username', 'user')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $post = CommunityPost::query()->where('content', 'Tìm đội giao lưu cầu lông tối thứ 7 tại Cầu Giấy.')->first();

        if (! $user || ! $post) {
            return;
        }

        CommunityPostComment::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $user->id,
                'content' => 'Mình tham gia được, còn slot không?',
            ],
            [
                'parent_id' => null,
                'status' => 'visible',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'status_reason' => null,
            ]
        );

        if (Schema::hasColumn('community_post_comments', 'reviewed_by')) {
            CommunityPostComment::query()->updateOrCreate(
                [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'content' => 'Bình luận mẫu bị ẩn do chứa nội dung quảng cáo.',
                ],
                [
                    'parent_id' => null,
                    'status' => 'hidden',
                    'reviewed_by' => $staff?->id,
                    'reviewed_at' => now()->subDay(),
                    'status_reason' => 'Bình luận chứa nội dung quảng cáo không phù hợp.',
                ]
            );
        }
    }
}
