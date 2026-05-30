<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ReportsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasTable('users')) {
            return;
        }

        $reporter = User::query()->where('username', 'user')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $hiddenPost = CommunityPost::query()->where('content', 'Bài viết mẫu đã bị ẩn do bị báo cáo spam.')->first();
        $hiddenComment = CommunityPostComment::query()->where('content', 'Bình luận mẫu bị ẩn do chứa nội dung quảng cáo.')->first();

        if (! $reporter) {
            return;
        }

        if ($hiddenPost) {
            Report::query()->updateOrCreate(
                [
                    'reporter_id' => $reporter->id,
                    'reportable_type' => CommunityPost::class,
                    'reportable_id' => $hiddenPost->id,
                ],
                [
                    'reason' => 'spam',
                    'description' => 'Bài viết lặp lại nội dung quảng cáo.',
                    'status' => 'resolved',
                    'action_taken' => 'content_hidden',
                    'action_note' => 'Đã ẩn bài viết sau khi kiểm tra.',
                    'reviewed_by' => $staff?->id,
                    'reviewed_at' => now()->subDay(),
                ]
            );
        }

        if ($hiddenComment) {
            Report::query()->updateOrCreate(
                [
                    'reporter_id' => $reporter->id,
                    'reportable_type' => CommunityPostComment::class,
                    'reportable_id' => $hiddenComment->id,
                ],
                [
                    'reason' => 'other',
                    'description' => 'Bình luận quảng cáo dịch vụ ngoài hệ thống.',
                    'status' => 'reviewing',
                    'action_taken' => null,
                    'action_note' => null,
                    'reviewed_by' => $staff?->id,
                    'reviewed_at' => now(),
                ]
            );
        }
    }
}
