<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BookingItemsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('booking_items') || ! Schema::hasTable('bookings') || ! Schema::hasTable('venue_courts')) {
            return;
        }

        $courts = VenueCourt::query()->whereIn('name', [
            'Sân cầu lông A1',
            'Sân cầu lông A2',
            'Sân pickleball P1',
        ])->get()->keyBy('name');

        $items = [
            ['BKADMPAID1', 'Sân cầu lông A1', '08:00:00', '09:00:00', 60, 120000, 120000],
            ['BKADMREF1', 'Sân pickleball P1', '10:00:00', '11:00:00', 60, 150000, 150000],
            ['BKADMREFPROC1', 'Sân cầu lông A1', '09:00:00', '10:00:00', 60, 180000, 180000],
            ['BKADMREFCOMP1', 'Sân cầu lông A2', '14:00:00', '15:00:00', 60, 90000, 90000],
            ['BKADMREFFAIL1', 'Sân pickleball P1', '16:00:00', '17:00:00', 60, 125000, 125000],
            ['BKADMREFREJ1', 'Sân cầu lông A1', '19:00:00', '20:00:00', 60, 110000, 110000],
            ['BKADMPEND1', 'Sân pickleball P1', '15:00:00', '16:00:00', 60, 100000, 100000],
            ['BKADMCOUN1', 'Sân cầu lông A2', '18:00:00', '18:30:00', 30, 160000, 80000],
        ];

        foreach ($items as [$bookingCode, $courtName, $start, $end, $duration, $unitPrice, $subtotal]) {
            $booking = Booking::query()->where('booking_code', $bookingCode)->first();
            $court = $courts[$courtName] ?? null;

            if (! $booking || ! $court) {
                continue;
            }

            BookingItem::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'sort_order' => 1,
                ],
                [
                    'venue_court_id' => $court->id,
                    'requested_venue_court_id' => $court->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => $duration,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'court_changed_by' => null,
                    'court_changed_at' => null,
                    'court_changed_reason' => null,
                ],
            );
        }
    }
}
