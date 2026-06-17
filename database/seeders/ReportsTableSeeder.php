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

        // Tạo 30 reports cho user testlocked
        $testLockedUser = User::query()->where('username', 'testlocked')->first();
        if ($testLockedUser) {
            // Need to create random users to be reporters because reporter_target is unique
            for ($i = 0; $i < 30; $i++) {
                $tempReporter = User::factory()->create([
                    'status' => 'active'
                ]);
                Report::query()->create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'reporter_id' => $tempReporter->id,
                    'reportable_type' => User::class,
                    'reportable_id' => $testLockedUser->id,
                    'reason' => 'spam',
                    'description' => 'Spam comment ' . $i,
                    'status' => 'resolved',
                    'action_taken' => null,
                    'action_note' => 'Đã xử lý ' . $i,
                    'reviewed_by' => $staff?->id,
                    'reviewed_at' => now(),
                    'created_at' => now()->subMinutes(rand(1, 1000)),
                ]);
            }
        }
    }
}
