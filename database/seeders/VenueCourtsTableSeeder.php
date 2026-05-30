<?php

namespace Database\Seeders;

use App\Models\CourtType;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class VenueCourtsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('court_types') || ! Schema::hasTable('venue_clusters') || ! Schema::hasTable('venue_courts')) {
            return;
        }

        $clusters = VenueCluster::query()->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])->get()->keyBy('slug');
        $types = CourtType::query()->whereIn('name', [
            'Cầu lông (Sân tiêu chuẩn)',
            'Pickleball (Sân tiêu chuẩn)',
            'Bóng Đá (Sân 7)',
            'Bóng Đá (Sân 11)',
            'Bóng rổ (Sân tiêu chuẩn)',
            'Bóng chuyền (Sân tiêu chuẩn)',
            'Tennis (Sân tiêu chuẩn)',
        ])->pluck('id', 'name');

        $courts = [
            ['sportgo-cau-giay', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A1', 1],
            ['sportgo-cau-giay', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A2', 2],
            ['sportgo-cau-giay', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P1', 3],
            ['sportgo-cau-giay', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P2', 4],
            ['sportgo-my-dinh', 'Bóng Đá (Sân 7)', 'Sân bóng đá F1', 1],
            ['sportgo-my-dinh', 'Bóng Đá (Sân 11)', 'Sân bóng đá F2', 2],
        ];

        foreach ($courts as [$clusterSlug, $courtTypeName, $courtName, $sortOrder]) {
            $cluster = $clusters[$clusterSlug] ?? null;
            $courtTypeId = $types[$courtTypeName] ?? null;

            if (! $cluster || ! $courtTypeId) {
                continue;
            }

            VenueCourt::query()->updateOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => $courtName,
                ],
                [
                    'court_type_id' => $courtTypeId,
                    'status' => 'active',
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }
}
