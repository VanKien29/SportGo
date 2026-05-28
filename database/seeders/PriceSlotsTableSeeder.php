<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\PriceSlot;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PriceSlotsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('court_types') || ! Schema::hasTable('venue_clusters') || ! Schema::hasTable('price_slots')) {
            return;
        }

        $clusters = VenueCluster::query()->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])->get()->keyBy('slug');
        $types = CourtType::query()->whereIn('name', array_keys($this->prices()))->pluck('id', 'name');

        $clusterTypes = [
            'sportgo-cau-giay' => ['Cầu lông', 'Pickleball'],
            'sportgo-my-dinh' => ['Bóng đá 5 người', 'Bóng đá 7 người'],
        ];

        foreach ($clusterTypes as $clusterSlug => $courtTypeNames) {
            $cluster = $clusters[$clusterSlug] ?? null;

            if (! $cluster) {
                continue;
            }

            foreach ($courtTypeNames as $courtTypeName) {
                $courtTypeId = $types[$courtTypeName] ?? null;

                if (! $courtTypeId) {
                    continue;
                }

                foreach ($this->prices()[$courtTypeName] as [$startTime, $endTime, $price]) {
                    PriceSlot::query()->updateOrCreate(
                        [
                            'venue_cluster_id' => $cluster->id,
                            'court_type_id' => $courtTypeId,
                            'booking_type' => 'all',
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ],
                        [
                            'price' => $price,
                            'apply_to_days' => [0, 1, 2, 3, 4, 5, 6],
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }

    private function prices(): array
    {
        return [
            'Cầu lông' => [
                ['06:00:00', '17:00:00', 80000],
                ['17:00:00', '22:00:00', 120000],
            ],
            'Pickleball' => [
                ['06:00:00', '17:00:00', 100000],
                ['17:00:00', '22:00:00', 150000],
            ],
            'Bóng đá 5 người' => [
                ['06:00:00', '17:00:00', 300000],
                ['17:00:00', '22:00:00', 500000],
            ],
            'Bóng đá 7 người' => [
                ['06:00:00', '17:00:00', 500000],
                ['17:00:00', '22:00:00', 800000],
            ],
        ];
    }
}
