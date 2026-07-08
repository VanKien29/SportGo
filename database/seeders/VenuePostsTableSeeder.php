<?php

namespace Database\Seeders;

use App\Models\Media;
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
        $cluster = VenueCluster::query()->where('slug', 'green-sport-ba-dinh')->first();

        if (! $owner || ! $cluster) {
            return;
        }

        $guidePost = VenuePost::query()->updateOrCreate(
            ['slug' => 'green-sport-ba-dinh-dat-san-gio-cao-diem'],
            [
                'venue_cluster_id' => $cluster->id,
                'author_id' => $owner->id,
                'title' => 'Lưu ý khi đặt sân cầu lông giờ cao điểm',
                'short_description' => 'Green Sport Ba Đình gợi ý cách chọn khung giờ phù hợp cho nhóm chơi buổi tối.',
                'content' => '<p>Khung 18:00 đến 21:00 thường kín sân nhanh vì nhiều nhóm chơi sau giờ làm. Người chơi nên chọn ngày, giờ và loại sân trước khi so sánh các sân còn trống.</p><p>Với nhóm chơi cố định hằng tuần, đặt trước ít nhất một ngày giúp giữ khung giờ quen thuộc và hạn chế đổi lịch sát giờ.</p>',
                'meta_title' => 'Đặt sân Green Sport Ba Đình giờ cao điểm',
                'meta_description' => 'Gợi ý đặt sân cầu lông giờ cao điểm tại Green Sport Ba Đình.',
                'post_type' => 'news',
                'status' => 'published',
                'reviewed_by' => $staff?->id,
                'reviewed_at' => now(),
                'status_reason' => null,
                'view_count' => 128,
                'like_count' => 12,
                'comment_count' => 0,
            ],
        );

        if (Schema::hasTable('media')) {
            Media::query()->updateOrCreate(
                [
                    'mediable_type' => VenuePost::class,
                    'mediable_id' => $guidePost->id,
                    'collection' => 'thumbnail',
                ],
                [
                    'file_name' => 'badminton-cover.webp',
                    'file_path' => '/images/home/badminton-cover.webp',
                    'mime_type' => 'image/webp',
                    'file_size' => file_exists(public_path('images/home/badminton-cover.webp'))
                        ? filesize(public_path('images/home/badminton-cover.webp'))
                        : 0,
                    'sort_order' => 0,
                ],
            );
        }

        $posts = [
            [
                'green-sport-ba-dinh-khung-gio-sang',
                'Green Sport Ba Đình mở thêm khung giờ sáng',
                'Green Sport Ba Đình mở thêm khung 06:00 - 08:00 cho sân cầu lông A1 và A2 từ thứ Hai đến thứ Sáu.',
                'published',
                $staff?->id,
                now()->subDays(5),
                120,
                8,
            ],
            [
                'green-sport-ba-dinh-uu-dai-cuoi-tuan',
                'Ưu đãi cuối tuần đang chờ duyệt',
                'Green Sport Ba Đình gửi bài ưu đãi cuối tuần, chờ nhân viên hệ thống kiểm tra trước khi hiển thị.',
                'pending_review',
                null,
                null,
                0,
                0,
            ],
        ];

        foreach ($posts as [$slug, $title, $content, $status, $reviewedBy, $reviewedAt, $views, $likes]) {
            VenuePost::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'venue_cluster_id' => $cluster->id,
                    'author_id' => $owner->id,
                    'title' => $title,
                    'post_type' => 'news',
                    'content' => $content,
                    'short_description' => mb_substr($content, 0, 160),
                    'meta_title' => $title,
                    'meta_description' => mb_substr($content, 0, 160),
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'status_reason' => null,
                    'view_count' => $views,
                    'like_count' => $likes,
                    'comment_count' => 0,
                ],
            );
        }
    }
}
