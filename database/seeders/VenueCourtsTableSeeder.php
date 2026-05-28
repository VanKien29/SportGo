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
            'Cầu lông',
            'Pickleball',
            'Bóng đá 5 người',
            'Bóng đá 7 người',
        ])->pluck('id', 'name');

        $courts = [
            ['sportgo-cau-giay', 'Cầu lông', 'Sân cầu lông A1', 1],
            ['sportgo-cau-giay', 'Cầu lông', 'Sân cầu lông A2', 2],
            ['sportgo-cau-giay', 'Pickleball', 'Sân pickleball P1', 3],
            ['sportgo-my-dinh', 'Bóng đá 5 người', 'Sân bóng đá 5 người F1', 1],
            ['sportgo-my-dinh', 'Bóng đá 7 người', 'Sân bóng đá 7 người F2', 2],
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
