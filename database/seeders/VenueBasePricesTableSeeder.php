<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VenueBasePricesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('venue_base_prices')
            || ! Schema::hasTable('venue_courts')
            || ! Schema::hasTable('venue_clusters')
        ) {
            return;
        }

        $pairs = DB::table('venue_courts')
            ->where('status', 'active')
            ->select('venue_cluster_id', 'court_type_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $price = DB::table('price_slots')
                ->where('venue_cluster_id', $pair->venue_cluster_id)
                ->where('court_type_id', $pair->court_type_id)
                ->where('is_active', true)
                ->min('price') ?: 100000;

            $existingId = DB::table('venue_base_prices')
                ->where('venue_cluster_id', $pair->venue_cluster_id)
                ->where('court_type_id', $pair->court_type_id)
                ->value('id');

            $payload = [
                'venue_cluster_id' => $pair->venue_cluster_id,
                'court_type_id' => $pair->court_type_id,
                'price' => $price,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('venue_base_prices')->where('id', $existingId)->update($payload);
                continue;
            }

            $payload['created_at'] = now();
            DB::table('venue_base_prices')->insert($payload);
        }
    }
}
