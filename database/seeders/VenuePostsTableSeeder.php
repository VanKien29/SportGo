<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenuePostsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_posts') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $staff = User::query()->where('username', 'systemstaff')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();

        if (! $owner || ! $cluster) {
            return;
        }

        $posts = [
            [
                'SportGo Cầu Giấy mở thêm khung giờ sáng cho cầu lông.',
                'published',
                $staff?->id,
                now()->subDays(5),
                null,
                120,
                8,
                0,
            ],
            [
                'Bài đăng sân đang chờ duyệt: ưu đãi cuối tuần.',
                'pending_review',
                null,
                null,
                null,
                0,
                0,
                0,
            ],
        ];

        foreach ($posts as [$content, $status, $reviewedBy, $reviewedAt, $reason, $views, $likes, $comments]) {
            VenuePost::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'author_id' => $owner->id,
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
