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
            ],
            [
                'Hướng dẫn đặt sân trên SportGo',
                'Chọn cụm sân, chọn loại sân, chọn khung giờ phù hợp rồi gửi yêu cầu đặt sân.',
            ],
        ];

        foreach ($posts as [$title, $content]) {
            SystemPost::query()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'author_id' => $admin?->id,
                    'title' => $title,
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
