<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VenueAccessRestriction;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenueAccessRestrictionsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_access_restrictions') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $this->seedRestriction(
            'green-sport-ba-dinh',
            'admin_manual',
            'full',
            'Owner đủ quyền quản lý cụm sân Green Sport Ba Đình đang hoạt động.',
            now()->subDays(10),
            null,
            'active',
            $admin?->id,
        );

        $this->seedRestriction(
            'sun-sport-cau-giay',
            'admin_manual',
            'limited',
            'Cụm sân Sun Sport Cầu Giấy đang chờ duyệt hồ sơ đối tác.',
            now()->subDays(2),
            null,
            'active',
            $admin?->id,
        );
    }

    private function seedRestriction(
        string $clusterSlug,
        string $restrictionType,
        string $accessMode,
        string $reason,
        mixed $startsAt,
        mixed $endsAt,
        string $status,
        ?string $createdBy
    ): void {
        $cluster = VenueCluster::query()->where('slug', $clusterSlug)->first();

        if (! $cluster) {
            return;
        }

        VenueAccessRestriction::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'restriction_type' => $restrictionType,
                'access_mode' => $accessMode,
            ],
            [
                'reason' => $reason,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $createdBy,
                'status' => $status,
            ],
        );
    }
}
