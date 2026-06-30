<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenuePostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'venue_owner'))->get();
        if ($owners->isEmpty()) {
            $this->command->info('Không tìm thấy chủ sân nào, bỏ qua VenuePostSeeder.');
            return;
        }

        $clusters = VenueCluster::all();
        if ($clusters->isEmpty()) {
            $this->command->info('Không tìm thấy cụm sân nào, bỏ qua VenuePostSeeder.');
            return;
        }

        $postTypes = ['promotion', 'tournament', 'news', 'notice', 'recruitment'];
        $statuses = ['draft', 'pending_review', 'published', 'rejected', 'hidden'];

        foreach ($owners as $owner) {
            $ownerClusters = $clusters->where('owner_id', $owner->id);
            if ($ownerClusters->isEmpty()) {
                $ownerClusters = $clusters->take(2);
            }

            foreach ($ownerClusters as $cluster) {
                // Generate 3 posts for each cluster
                for ($i = 0; $i < 3; $i++) {
                    $title = "Bài viết mẫu số " . ($i + 1) . " của " . $cluster->name;
                    VenuePost::create([
                        'id' => (string) Str::uuid(),
                        'venue_cluster_id' => $cluster->id,
                        'author_id' => $owner->id,
                        'title' => $title,
                        'slug' => Str::slug($title) . '-' . Str::random(5),
                        'content' => "<p>Đây là nội dung bài viết mẫu số " . ($i + 1) . " dành cho <b>" . $cluster->name . "</b>. Bạn có thể thay đổi nội dung này sau.</p>",
                        'post_type' => $postTypes[array_rand($postTypes)],
                        'status' => $statuses[array_rand($statuses)],
                        'view_count' => rand(10, 500),
                        'like_count' => rand(0, 50),
                        'comment_count' => 0,
                    ]);
                }
            }
        }

        $this->command->info('Đã tạo dữ liệu giả cho Bài viết thành công.');
    }
}
