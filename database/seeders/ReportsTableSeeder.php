<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ReportsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasTable('users') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        $reporter = User::query()->where('username', 'user1')->first();
        $cluster = VenueCluster::query()->where('slug', 'green-sport-ba-dinh')->first();

        if (! $reporter || ! $cluster) {
            return;
        }

        Report::query()->updateOrCreate(
            [
                'reporter_id' => $reporter->id,
                'reportable_type' => VenueCluster::class,
                'reportable_id' => $cluster->id,
            ],
            [
                'violation_type_id' => null,
                'severity_level' => 'mild',
                'score_contribution' => 0,
                'auto_action_taken' => null,
                'auto_actioned_at' => null,
                'reason' => 'other',
                'description' => 'Khách phản ánh ảnh đại diện cụm sân chưa đúng khu vực thực tế, chờ admin kiểm tra.',
                'status' => 'pending',
                'action_taken' => null,
                'action_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );
    }
}
