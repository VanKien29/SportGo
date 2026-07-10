<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenuePostSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'venue_owner'))->get();

        if ($owners->isEmpty()) {
            $this->command->info('No venue owner found, skip VenuePostSeeder.');
            return;
        }

        $clusters = VenueCluster::query()->orderBy('slug')->get();

        if ($clusters->isEmpty()) {
            $this->command->info('No venue cluster found, skip VenuePostSeeder.');
            return;
        }

        $postTypes = ['news', 'promotion', 'notice'];
        $statuses = ['published', 'pending_review', 'hidden'];

        foreach ($owners as $owner) {
            $ownerClusters = $clusters->where('owner_id', $owner->id);

            if ($ownerClusters->isEmpty()) {
                $ownerClusters = $clusters->take(2);
            }

            foreach ($ownerClusters as $cluster) {
                for ($i = 0; $i < 3; $i++) {
                    $title = 'Seed venue post ' . ($i + 1) . ' - ' . $cluster->name;
                    $slug = Str::slug($title) . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);

                    VenuePost::query()->updateOrCreate(
                        [
                            'venue_cluster_id' => $cluster->id,
                            'slug' => $slug,
                        ],
                        [
                            'author_id' => $owner->id,
                            'title' => $title,
                            'content' => '<p>Seed content for ' . e($cluster->name) . ' post ' . ($i + 1) . '.</p>',
                            'post_type' => $postTypes[$i % count($postTypes)],
                            'status' => $statuses[$i % count($statuses)],
                            'view_count' => 50 + ($i * 25),
                            'like_count' => 5 + $i,
                            'comment_count' => 0,
                        ]
                    );
                }
            }
        }

        $this->command->info('VenuePostSeeder completed with deterministic data.');
    }
}
