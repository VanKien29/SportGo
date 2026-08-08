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

        $clusters = VenueCluster::query()
            ->whereIn('slug', [
                'green-sport-ba-dinh',
                'sun-sport-cau-giay',
                'victory-sport-ha-dong',
                'green-sport-cau-giay',
                'green-sport-tay-ho',
            ])
            ->get()
            ->keyBy('slug');

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
            ['green-sport-ba-dinh', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A1', 1, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A2', 2, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A3', 3, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông A4', 4, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P1', 5, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P2', 6, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P3', 7, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball P4', 8, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Tennis (Sân tiêu chuẩn)', 'Sân tennis T1', 9, null, null, null, null, 0],
            ['green-sport-ba-dinh', 'Bóng rổ (Sân tiêu chuẩn)', 'Sân bóng rổ B1', 10, null, null, null, null, 0],
            ['sun-sport-cau-giay', 'Bóng Đá (Sân 7)', 'Sân bóng đá F1', 1, null, null, null, null, 0],
            ['sun-sport-cau-giay', 'Bóng Đá (Sân 11)', 'Sân bóng đá F2', 2, null, null, null, null, 0],
            ['victory-sport-ha-dong', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông V1', 1, null, null, null, null, 0],
            ['victory-sport-ha-dong', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball V2', 2, null, null, null, null, 0],
            ['victory-sport-ha-dong', 'Bóng Đá (Sân 7)', 'Sân bóng đá V3', 3, null, null, null, null, 0],
            ['green-sport-cau-giay', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông CG1', 1, null, null, null, null, 0],
            ['green-sport-cau-giay', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông CG2', 2, null, null, null, null, 0],
            ['green-sport-cau-giay', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball CG3', 3, null, null, null, null, 0],
            ['green-sport-cau-giay', 'Tennis (Sân tiêu chuẩn)', 'Sân tennis CG4', 4, null, null, null, null, 0],
            ['green-sport-tay-ho', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball TH1', 1, null, null, null, null, 0],
            ['green-sport-tay-ho', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball TH2', 2, null, null, null, null, 0],
            ['green-sport-tay-ho', 'Tennis (Sân tiêu chuẩn)', 'Sân tennis TH3', 3, null, null, null, null, 0],
            ['green-sport-tay-ho', 'Bóng Đá (Sân 7)', 'Sân bóng đá TH4', 4, null, null, null, null, 0],
            ['diamond-sport-thanh-xuan', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông TX1', 1, null, null, null, null, 0],
            ['diamond-sport-thanh-xuan', 'Tennis (Sân tiêu chuẩn)', 'Sân tennis TX2', 2, null, null, null, null, 0],
            ['star-arena-nam-tu-liem', 'Bóng Đá (Sân 7)', 'Sân bóng đá NTL1', 1, null, null, null, null, 0],
            ['star-arena-nam-tu-liem', 'Bóng rổ (Sân tiêu chuẩn)', 'Sân bóng rổ NTL2', 2, null, null, null, null, 0],
            ['elite-sport-hoan-kiem', 'Pickleball (Sân tiêu chuẩn)', 'Sân pickleball HK1', 1, null, null, null, null, 0],
            ['elite-sport-hoan-kiem', 'Cầu lông (Sân tiêu chuẩn)', 'Sân cầu lông HK2', 2, null, null, null, null, 0],
        ];

        foreach ($courts as [$clusterSlug, $courtTypeName, $courtName, $sortOrder, $x, $y, $w, $h, $rot]) {
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
                    'layout_x' => $x,
                    'layout_y' => $y,
                    'layout_w' => $w,
                    'layout_h' => $h,
                    'layout_rotation' => $rot,
                ],
            );
        }
    }
}
