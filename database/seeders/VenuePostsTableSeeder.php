<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        $demoPost = VenuePost::query()->updateOrCreate(
            [
                'slug' => 'huong-dan-dat-san-cau-long-gio-cao-diem',
            ],
            [
                'venue_cluster_id' => $cluster->id,
                'author_id' => $owner->id,
                'title' => '5 lưu ý khi đặt sân cầu lông vào giờ cao điểm',
                'short_description' => 'Một bài viết demo có ảnh bìa, nội dung đầy đủ và liên kết tới cụm sân để kiểm thử trang tin tức của SportGo.',
                'content' => '<p>Giờ cao điểm thường rơi vào khung 18:00 đến 21:00, khi người chơi đi làm về và các nhóm cố định bắt đầu đặt lịch. Nếu đặt sân sát giờ, bạn rất dễ gặp tình trạng hết sân đẹp hoặc phải đổi sang khung giờ không phù hợp.</p><h2>Chọn khung giờ trước khi lọc sân</h2><p>Hãy bắt đầu bằng ngày chơi và giờ bắt đầu. SportGo sẽ giữ ngữ cảnh tìm kiếm này khi bạn chuyển từ trang chủ sang danh sách sân, giúp việc so sánh cụm sân nhanh hơn.</p><h2>Ưu tiên sân có thông tin rõ ràng</h2><p>Một cụm sân tốt nên có ảnh bìa rõ, địa chỉ dễ tìm, loại sân cụ thể và giá hiển thị minh bạch. Với cầu lông, bạn nên kiểm tra thêm số lượng sân và khung giờ còn trống trước khi rủ đủ đội hình.</p><h2>Đặt sớm cho nhóm chơi cố định</h2><p>Nếu nhóm của bạn chơi hàng tuần, hãy đặt trước ít nhất một ngày. Điều này giúp giảm rủi ro đổi lịch vào phút cuối và giữ được khung giờ quen thuộc.</p>',
                'meta_title' => '5 lưu ý khi đặt sân cầu lông vào giờ cao điểm',
                'meta_description' => 'Demo trang nội dung tin tức SportGo với ảnh bìa và bài viết đầy đủ.',
                'post_type' => 'news',
                'status' => 'published',
                'reviewed_by' => $staff?->id,
                'reviewed_at' => now(),
                'status_reason' => null,
                'view_count' => 128,
                'like_count' => 12,
                'comment_count' => 0,
            ]
        );

        if (Schema::hasTable('media')) {
            Media::query()->updateOrCreate(
                [
                    'mediable_type' => VenuePost::class,
                    'mediable_id' => $demoPost->id,
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
                ]
            );
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
                    'title' => Str::limit(strip_tags($content), 120, ''),
                    'slug' => Str::slug(Str::limit(strip_tags($content), 80, '')) ?: (string) Str::uuid(),
                    'post_type' => 'news',
                    'short_description' => substr($content, 0, 50),
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
