<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\HolidayPrice;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HolidayPricesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('court_types') || ! Schema::hasTable('venue_clusters') || ! Schema::hasTable('holiday_prices')) {
            return;
        }

        $clusters = VenueCluster::query()
            ->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])
            ->get()
            ->keyBy('slug');
        $types = CourtType::query()
            ->whereIn('name', ['Cầu lông (Sân tiêu chuẩn)', 'Bóng Đá (Sân 7)'])
            ->pluck('id', 'name');

        $prices = [
            ['sportgo-cau-giay', 'Cầu lông (Sân tiêu chuẩn)', '2026-01-01', 144000],
            ['sportgo-my-dinh', 'Bóng Đá (Sân 7)', '2026-01-01', 600000],
        ];

        foreach ($prices as [$clusterSlug, $courtTypeName, $date, $price]) {
            $cluster = $clusters[$clusterSlug] ?? null;
            $courtTypeId = $types[$courtTypeName] ?? null;

            if (! $cluster || ! $courtTypeId) {
                continue;
            }

            HolidayPrice::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'court_type_id' => $courtTypeId,
                    'holiday_date' => $date,
                    'start_time' => '06:00:00',
                    'end_time' => '22:00:00',
                    'booking_type' => 'all',
                ],
                [
                    'date_type' => 'holiday',
                    'price' => $price,
                    'note' => 'Giá mẫu ngày lễ 01/01, cao hơn giá thường khoảng 20%.',
                    'is_active' => true,
                ],
            );
        }
    }
}
