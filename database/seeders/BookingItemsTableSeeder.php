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
            'Sân pickleball P2',
        ])->get()->keyBy('name');

        // [booking_code, court_name, start, end, duration, unit_price, subtotal]
        $items = [
            // Confirmed
            ['BK-CONF-01', 'Sân cầu lông A1',  '08:00:00', '09:00:00', 60, 120000, 120000],
            ['BK-CONF-02', 'Sân cầu lông A2',  '10:00:00', '11:00:00', 60, 130000, 130000],
            ['BK-CONF-03', 'Sân pickleball P1', '14:00:00', '15:00:00', 60, 150000, 150000],
            ['BK-CONF-04', 'Sân pickleball P2', '16:00:00', '17:00:00', 60, 160000, 160000],
            ['BK-CONF-05', 'Sân cầu lông A1',  '08:00:00', '09:00:00', 60, 120000, 120000],
            ['BK-CONF-06', 'Sân cầu lông A2',  '10:00:00', '11:00:00', 60, 130000, 130000],
            ['BK-CONF-07', 'Sân pickleball P1', '09:00:00', '10:00:00', 60, 150000, 150000],
            ['BK-CONF-08', 'Sân cầu lông A1',  '15:00:00', '16:00:00', 60, 120000, 120000],

            // Pending payment
            ['BK-PEND-01', 'Sân pickleball P1', '17:00:00', '18:00:00', 60, 150000, 150000],
            ['BK-PEND-02', 'Sân cầu lông A2',  '14:00:00', '15:00:00', 60, 130000, 130000],
            ['BK-PEND-03', 'Sân pickleball P2', '08:00:00', '09:00:00', 60, 160000, 160000],
            ['BK-PEND-04', 'Sân cầu lông A1',  '10:00:00', '11:00:00', 60, 120000, 120000],
            ['BK-PEND-05', 'Sân pickleball P1', '14:00:00', '15:00:00', 60, 150000, 150000],

            // Cancelled
            ['BK-CANC-01', 'Sân cầu lông A1',  '09:00:00', '10:00:00', 60, 120000, 120000],
            ['BK-CANC-02', 'Sân cầu lông A2',  '11:00:00', '12:00:00', 60, 130000, 130000],
            ['BK-CANC-03', 'Sân pickleball P1', '14:00:00', '15:00:00', 60, 150000, 150000],
            ['BK-CANC-04', 'Sân pickleball P2', '16:00:00', '17:00:00', 60, 160000, 160000],
            ['BK-CANC-05', 'Sân cầu lông A1',  '08:00:00', '09:00:00', 60, 120000, 120000],
            ['BK-CANC-06', 'Sân cầu lông A2',  '10:00:00', '11:00:00', 60, 130000, 130000],
            ['BK-CANC-07', 'Sân pickleball P1', '15:00:00', '16:00:00', 60, 150000, 150000],
            ['BK-CANC-08', 'Sân cầu lông A1',  '09:00:00', '10:00:00', 60, 120000, 120000],
            ['BK-CANC-09', 'Sân pickleball P2', '17:00:00', '18:00:00', 60, 160000, 160000],
            ['BK-CANC-10', 'Sân cầu lông A2',  '14:00:00', '15:00:00', 60, 130000, 130000],

            // Counter
            ['BK-COUN-01', 'Sân cầu lông A1',  '18:00:00', '18:30:00', 30, 120000, 60000],
            ['BK-COUN-02', 'Sân cầu lông A2',  '19:00:00', '20:00:00', 60, 130000, 130000],
            ['BK-COUN-03', 'Sân pickleball P1', '08:00:00', '09:00:00', 60, 150000, 150000],

            // Expired
            ['BK-EXP-01', 'Sân pickleball P2', '06:00:00', '07:00:00', 60, 160000, 160000],
            ['BK-EXP-02', 'Sân cầu lông A1',  '07:00:00', '08:00:00', 60, 120000, 120000],

            // No show
            ['BK-NOSHOW-01', 'Sân cầu lông A2',  '09:00:00', '10:00:00', 60, 130000, 130000],
            ['BK-NOSHOW-02', 'Sân pickleball P1', '11:00:00', '12:00:00', 60, 150000, 150000],
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
