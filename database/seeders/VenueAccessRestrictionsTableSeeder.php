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

        $this->seedRestriction('sportgo-cau-giay', 'admin_manual', 'full', 'Owner đủ quyền quản lý cụm sân đang hoạt động.', now()->subDays(10), null, 'active', $admin?->id);
        $this->seedRestriction('sportgo-my-dinh', 'platform_fee_overdue', 'limited', 'Khoản phí quá hạn đã được thanh toán đầy đủ.', now()->subDays(2), now(), 'expired', $admin?->id);
        $this->seedRestriction('sportgo-ha-dong', 'contract_termination', 'transition', 'Cụm sân đang trong thời gian chuyển tiếp 30 ngày sau khi chấm dứt hợp đồng.', now()->subDays(3), now()->addDays(27), 'active', $admin?->id);
        $this->seedRestriction('sportgo-ba-dinh', 'contract_termination', 'blocked', 'Đã hết thời gian chuyển tiếp, owner bị chặn quyền quản lý cụm sân.', now()->subDays(40), null, 'active', $admin?->id);
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

        if ($status === 'active' && ($accessMode === 'limited' || $accessMode === 'blocked')) {
            $cluster->forceFill([
                'status' => 'locked',
                'status_reason' => $reason,
                'locked_at' => $startsAt,
                'locked_by' => $createdBy,
            ])->save();
        } elseif ($clusterSlug === 'sportgo-my-dinh' && $status === 'expired') {
            $cluster->forceFill([
                'status' => 'active',
                'status_reason' => null,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
            ])->save();
        }
    }
}
