<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\VenueCluster;
use App\Models\VenueClusterAmenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenueClusterAmenitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('amenities')
            || ! Schema::hasTable('venue_clusters')
            || ! Schema::hasTable('venue_cluster_amenities')
        ) {
            return;
        }

        $clusters = VenueCluster::query()
            ->orderBy('name')
            ->limit(3)
            ->get();

        if ($clusters->isEmpty()) {
            return;
        }

        $groups = [
            [
                ['Wifi', 'Wifi miễn phí tại khu vực lễ tân.'],
                ['Bãi gửi xe', 'Có bãi gửi xe máy miễn phí.'],
                ['Điều hòa', 'Có điều hòa tại phòng chờ.'],
                ['Nhà vệ sinh', 'Có nhà vệ sinh nam/nữ riêng.'],
            ],
            [
                ['Wifi', 'Wifi miễn phí cho khách đặt sân.'],
                ['Đèn chiếu sáng', 'Hệ thống đèn LED phục vụ chơi buổi tối.'],
                ['Mái che', 'Khu sân có mái che.'],
                ['Nước uống', 'Có bán nước uống tại quầy.'],
            ],
            [
                ['Bãi gửi xe', 'Có khu vực gửi xe rộng phía trước sân.'],
                ['Căng tin', 'Có căng tin bán nước và đồ ăn nhẹ.'],
                ['Cho thuê vợt', 'Có cho thuê vợt theo giờ.'],
                ['Tủ gửi đồ', 'Có tủ gửi đồ cá nhân tại quầy.'],
            ],
        ];

        foreach ($clusters as $index => $cluster) {
            $group = $groups[$index] ?? $groups[0];
            foreach ($group as [$amenityName, $description]) {
                $amenity = Amenity::query()
                    ->where('name', $amenityName)
                    ->where('status', 'active')
                    ->first();

                if (! $amenity) {
                    continue;
                }

                VenueClusterAmenity::query()->updateOrCreate(
                    [
                        'venue_cluster_id' => $cluster->id,
                        'amenity_id' => $amenity->id,
                    ],
                    [
                        'description' => $description,
                        'is_visible' => true,
                    ]
                );
            }
        }
    }
}
