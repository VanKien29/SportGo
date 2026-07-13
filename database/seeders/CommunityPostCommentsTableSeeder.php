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

        $nam = User::query()->where('username', 'user')->first();
        $linh = User::query()->where('username', 'user1')->first();
        $post = CommunityPost::query()
            ->where('content', 'Cuối tuần này nhóm mình chơi cầu lông tại Green Sport Ba Đình, ai muốn giao lưu nhẹ nhàng có thể bình luận để ghép đội.')
            ->first();

        if (! $nam || ! $linh || ! $post) {
            return;
        }

        CommunityPostComment::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $linh->id,
                'content' => 'Mình tham gia được sáng Chủ nhật, còn slot không?',
            ],
            [
                'parent_id' => null,
                'status' => 'visible',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'status_reason' => null,
            ]
        );
    }
}
