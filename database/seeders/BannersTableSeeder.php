<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BannersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('banners')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $banners = [
            ['Banner trang chủ SportGo', 'banners/default-home.jpg', 1],
            ['Banner hướng dẫn đặt sân', 'banners/booking-guide.jpg', 2],
        ];

        foreach ($banners as [$title, $imagePath, $sortOrder]) {
            Banner::query()->updateOrCreate(
                [
                    'title' => $title,
                    'position' => 'home',
                ],
                [
                    'image_path' => $imagePath,
                    'link_url' => null,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'starts_at' => null,
                    'ends_at' => null,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]
            );
        }
    }
}
