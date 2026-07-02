<?php

namespace Database\Seeders;

use App\Models\SystemPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SystemPostsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('system_posts')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $posts = [
            [
                'Chào mừng đến với SportGo',
                'SportGo giúp người chơi tìm sân, đặt sân và theo dõi lịch đặt một cách thuận tiện.',
                'Chào mừng các bạn đã đến với nền tảng SportGo, nơi tốt nhất để kết nối cộng đồng thể thao.',
                'Thông báo'
            ],
            [
                'Hướng dẫn đặt sân trên SportGo',
                'Chọn cụm sân, chọn loại sân, chọn khung giờ phù hợp rồi gửi yêu cầu đặt sân.',
                'Bài viết hướng dẫn chi tiết các bước đặt sân dành cho người mới sử dụng.',
                'Hướng dẫn'
            ],
        ];

        foreach ($posts as [$title, $content, $shortDesc, $category]) {
            SystemPost::query()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'author_id' => $admin?->id,
                    'title' => $title,
                    'short_description' => $shortDesc,
                    'category' => $category,
                    'content' => $content,
                    'thumbnail_path' => null,
                    'status' => 'published',
                    'published_at' => now(),
                    'view_count' => 0,
                ]
            );
        }
    }
}
