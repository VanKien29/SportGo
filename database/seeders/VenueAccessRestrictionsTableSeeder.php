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
        $this->seedRestriction('sportgo-my-dinh', 'platform_fee_overdue', 'limited', 'Cụm sân quá hạn phí duy trì, owner chỉ được xem dữ liệu cũ và thanh toán phí.', now()->subDays(2), null, 'active', $admin?->id);
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

        if ($accessMode === 'limited') {
            $cluster->forceFill([
                'status' => 'locked',
                'status_reason' => 'Quá hạn phí duy trì hệ thống.',
                'locked_at' => $startsAt,
                'locked_by' => $createdBy,
            ])->save();
        }
    }
}
