<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BookingsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasTable('users') || ! Schema::hasTable('venue_clusters')) {
            return;
        }

        $customer = User::query()->where('username', 'user')->first();
        $owner = User::query()->where('username', 'owner')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $courts = VenueCourt::query()->whereIn('name', [
            'Sân cầu lông A1',
            'Sân cầu lông A2',
            'Sân pickleball P1',
        ])->get()->keyBy('name');

        if (! $cluster || ! $owner) {
            return;
        }

        $bookings = [
            [
                'BKADMPAID1',
                $customer?->id,
                'Sân cầu lông A1',
                '2026-05-30',
                '08:00:00',
                '09:00:00',
                60,
                120000,
                'full_payment',
                120000,
                'online',
                'single',
                'confirmed',
                null,
                null,
                $customer?->id,
            ],
            [
                'BKADMREF1',
                $customer?->id,
                'Sân pickleball P1',
                '2026-05-29',
                '10:00:00',
                '11:00:00',
                60,
                150000,
                'full_payment',
                150000,
                'online',
                'single',
                'cancelled',
                null,
                'Khách yêu cầu hủy và hoàn tiền.',
                $customer?->id,
            ],
            [
                'BKADMPEND1',
                $customer?->id,
                'Sân pickleball P1',
                '2026-05-31',
                '15:00:00',
                '16:00:00',
                60,
                100000,
                'deposit',
                30000,
                'online',
                'single',
                'pending_payment',
                null,
                null,
                $customer?->id,
            ],
            [
                'BKADMCOUN1',
                null,
                'Sân cầu lông A2',
                '2026-05-30',
                '18:00:00',
                '18:30:00',
                30,
                80000,
                'no_prepay',
                0,
                'counter',
                'single',
                'confirmed',
                'Nguyễn Văn A',
                null,
                $owner->id,
            ],
        ];

        foreach ($bookings as [$code, $customerId, $courtName, $date, $startTime, $endTime, $duration, $total, $paymentOption, $requiredAmount, $source, $bookingType, $status, $walkInName, $reason, $createdBy]) {
            $court = $courts[$courtName] ?? null;

            if (! $court) {
                continue;
            }

            Booking::query()->updateOrCreate(
                ['booking_code' => $code],
                [
                    'customer_id' => $customerId,
                    'venue_court_id' => $court->id,
                    'requested_venue_court_id' => $court->id,
                    'venue_cluster_id' => $cluster->id,
                    'booking_date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $duration,
                    'total_price' => $total,
                    'payment_option' => $paymentOption,
                    'required_payment_amount' => $requiredAmount,
                    'source' => $source,
                    'booking_type' => $bookingType,
                    'recurring_group_code' => null,
                    'recurring_start_date' => null,
                    'recurring_end_date' => null,
                    'recurrence_type' => null,
                    'recurrence_interval' => null,
                    'recurrence_days_of_week' => null,
                    'recurrence_days_of_month' => null,
                    'status' => $status,
                    'walk_in_name' => $walkInName,
                    'walk_in_phone' => $walkInName ? '0901234567' : null,
                    'status_reason' => $reason,
                    'cancelled_by' => $status === 'cancelled' ? $customerId : null,
                    'cancelled_at' => $status === 'cancelled' ? now()->subDay() : null,
                    'created_by' => $createdBy,
                    'court_changed_by' => null,
                    'court_changed_at' => null,
                    'court_changed_reason' => null,
                    'reminder_sent_at' => null,
                ]
            );
        }
    }
}
