<?php

namespace Database\Seeders;

use App\Models\BookingConfig;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BookingConfigsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('venue_clusters') || ! Schema::hasTable('booking_configs')) {
            return;
        }

        $clusters = VenueCluster::query()->whereIn('slug', ['sportgo-cau-giay', 'sportgo-my-dinh'])->get();

        foreach ($clusters as $cluster) {
            BookingConfig::query()->updateOrCreate(
                ['venue_cluster_id' => $cluster->id],
                [
                    'min_duration_minutes' => 30,
                    'max_duration_minutes' => null,
                    'slot_hold_minutes' => 20,
                    'reminder_before_minutes' => 30,
                    'allow_full_payment' => true,
                    'allow_deposit' => true,
                    'allow_no_prepay' => true,
                    'auto_approve_full_payment' => false,
                    'deposit_percent' => 30,
                    'cancel_before_hours' => 2,
                    'refund_percent' => 80,
                ]
            );
        }
    }
}
