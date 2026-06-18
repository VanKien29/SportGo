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

        // Thêm dữ liệu fake
        $user1 = User::query()->where('username', 'user1')->first();
        $user2 = User::query()->where('username', 'user2')->first();
        $user3 = User::query()->where('username', 'user3')->first();
        $user4 = User::query()->where('username', 'user4')->first();

        $post1 = CommunityPost::query()->where('content', "Tuyển gấp 2 bạn đánh giao lưu tối nay lúc 20h tại sân Cầu Giấy. Trình độ trung bình khá trở lên, vui vẻ hòa đồng. Ai đi được comment nhé!")->first();
        $post2 = CommunityPost::query()->where('content', "Góc thanh lý: Mình cần pass lại vợt Yonex Astrox 88D Pro tình trạng 95%, giá cả học sinh sinh viên. Ib để ép giá :))")->first();

        if ($post1 && $user1 && $user2 && $user3 && $user4) {
            $c1 = CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post1->id, 'user_id' => $user2->id, 'content' => 'Xin địa chỉ cụ thể của sân đi bác chủ.'],
                ['status' => 'visible']
            );

            CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post1->id, 'user_id' => $user1->id, 'parent_id' => $c1->id, 'content' => 'Sân ở ngõ 233 Xuân Thủy bạn nhé.'],
                ['status' => 'visible']
            );

            CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post1->id, 'user_id' => $user3->id, 'parent_id' => $c1->id, 'content' => 'Chỗ này hơi xa nhỉ :('],
                ['status' => 'visible']
            );

            $c2 = CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post1->id, 'user_id' => $user4->id, 'content' => 'Trình độ gà mờ mới tập chơi tham gia được không ạ?'],
                ['status' => 'visible']
            );

            CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post1->id, 'user_id' => $user1->id, 'parent_id' => $c2->id, 'content' => 'Dạ trình độ gà thì hơi khó ghép nhóm giao lưu hôm nay bạn ạ, nhóm mình đánh căng lắm, để hôm khác nha.'],
                ['status' => 'visible']
            );
        }

        if ($post2 && $user2 && $user3) {
            $c3 = CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post2->id, 'user_id' => $user3->id, 'content' => 'Cho xin giá fix với tình trạng lưới đang căng bao nhiêu kg ạ?'],
                ['status' => 'visible']
            );

            CommunityPostComment::query()->updateOrCreate(
                ['post_id' => $post2->id, 'user_id' => $user2->id, 'parent_id' => $c3->id, 'content' => 'Đang căng Exbolt 65 11kg nhé bác, giá fix inbox e.'],
                ['status' => 'visible']
            );
        }
    }
}
