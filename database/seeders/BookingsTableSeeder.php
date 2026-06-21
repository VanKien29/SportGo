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

        $customers = User::query()->whereIn('username', ['user', 'user1', 'user2', 'user3', 'user4'])->get()->keyBy('username');
        $owner = User::query()->where('username', 'owner')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $courts = VenueCourt::query()->whereIn('name', [
            'Sân cầu lông A1',
            'Sân cầu lông A2',
            'Sân pickleball P1',
            'Sân pickleball P2',
        ])->get()->keyBy('name');

        if (! $cluster || ! $owner || $customers->isEmpty()) {
            return;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $plus2 = now()->addDays(2)->toDateString();
        $plus3 = now()->addDays(3)->toDateString();
        $minus2 = now()->subDays(2)->toDateString();
        $minus3 = now()->subDays(3)->toDateString();
        $minus4 = now()->subDays(4)->toDateString();
        $minus5 = now()->subDays(5)->toDateString();
        $minus6 = now()->subDays(6)->toDateString();

        // [code, customer_username, court_name, date, start, end, duration, total, payment_option, required_amount, source, booking_type, status, walk_in_name, status_reason, created_by_username]
        $bookings = [
            // ===== CONFIRMED (8) =====
            ['BK-CONF-01', 'user',  'Sân cầu lông A1',  $yesterday, '08:00:00', '09:00:00', 60, 120000, 'full_payment', 120000, 'online',  'single', 'confirmed', null, null, 'user'],
            ['BK-CONF-02', 'user1', 'Sân cầu lông A2',  $yesterday, '10:00:00', '11:00:00', 60, 130000, 'full_payment', 130000, 'online',  'single', 'confirmed', null, null, 'user1'],
            ['BK-CONF-03', 'user2', 'Sân pickleball P1', $today,     '14:00:00', '15:00:00', 60, 150000, 'deposit',      45000,  'online',  'single', 'confirmed', null, null, 'user2'],
            ['BK-CONF-04', 'user3', 'Sân pickleball P2', $today,     '16:00:00', '17:00:00', 60, 160000, 'full_payment', 160000, 'online',  'single', 'confirmed', null, null, 'user3'],
            ['BK-CONF-05', 'user4', 'Sân cầu lông A1',  $tomorrow,  '08:00:00', '09:00:00', 60, 120000, 'full_payment', 120000, 'online',  'single', 'confirmed', null, null, 'user4'],
            ['BK-CONF-06', 'user',  'Sân cầu lông A2',  $tomorrow,  '10:00:00', '11:00:00', 60, 130000, 'deposit',      39000,  'online',  'single', 'confirmed', null, null, 'user'],
            ['BK-CONF-07', 'user1', 'Sân pickleball P1', $plus2,     '09:00:00', '10:00:00', 60, 150000, 'full_payment', 150000, 'online',  'single', 'confirmed', null, null, 'user1'],
            ['BK-CONF-08', 'user2', 'Sân cầu lông A1',  $plus3,     '15:00:00', '16:00:00', 60, 120000, 'full_payment', 120000, 'online',  'single', 'confirmed', null, null, 'user2'],

            // ===== PENDING PAYMENT (5) — chưa thao tác, để test thanh toán =====
            ['BK-PEND-01', 'user',  'Sân pickleball P1', $tomorrow,  '17:00:00', '18:00:00', 60, 150000, 'full_payment', 150000, 'online',  'single', 'pending_payment', null, null, 'user'],
            ['BK-PEND-02', 'user1', 'Sân cầu lông A2',  $tomorrow,  '14:00:00', '15:00:00', 60, 130000, 'deposit',      39000,  'online',  'single', 'pending_payment', null, null, 'user1'],
            ['BK-PEND-03', 'user3', 'Sân pickleball P2', $plus2,     '08:00:00', '09:00:00', 60, 160000, 'full_payment', 160000, 'online',  'single', 'pending_payment', null, null, 'user3'],
            ['BK-PEND-04', 'user4', 'Sân cầu lông A1',  $plus2,     '10:00:00', '11:00:00', 60, 120000, 'deposit',      36000,  'online',  'single', 'pending_payment', null, null, 'user4'],
            ['BK-PEND-05', 'user2', 'Sân pickleball P1', $plus3,     '14:00:00', '15:00:00', 60, 150000, 'full_payment', 150000, 'online',  'single', 'pending_payment', null, null, 'user2'],

            // ===== CANCELLED (10) — đa dạng refund status =====
            ['BK-CANC-01', 'user',  'Sân cầu lông A1',  $minus3, '09:00:00', '10:00:00', 60, 120000, 'full_payment', 120000, 'online', 'single', 'cancelled', null, 'Khách hủy do thay đổi lịch.', 'user'],
            ['BK-CANC-02', 'user1', 'Sân cầu lông A2',  $minus3, '11:00:00', '12:00:00', 60, 130000, 'full_payment', 130000, 'online', 'single', 'cancelled', null, 'Khách hủy vì lý do cá nhân.', 'user1'],
            ['BK-CANC-03', 'user2', 'Sân pickleball P1', $minus4, '14:00:00', '15:00:00', 60, 150000, 'full_payment', 150000, 'online', 'single', 'cancelled', null, 'Thời tiết xấu không chơi được.', 'user2'],
            ['BK-CANC-04', 'user3', 'Sân pickleball P2', $minus5, '16:00:00', '17:00:00', 60, 160000, 'full_payment', 160000, 'online', 'single', 'cancelled', null, 'Khách yêu cầu hủy sớm.', 'user3'],
            ['BK-CANC-05', 'user4', 'Sân cầu lông A1',  $minus4, '08:00:00', '09:00:00', 60, 120000, 'full_payment', 120000, 'online', 'single', 'cancelled', null, 'Hủy sát giờ chơi.', 'user4'],
            ['BK-CANC-06', 'user',  'Sân cầu lông A2',  $minus2, '10:00:00', '11:00:00', 60, 130000, 'deposit',      39000,  'online', 'single', 'cancelled', null, 'Khách hủy booking đặt cọc.', 'user'],
            ['BK-CANC-07', 'user1', 'Sân pickleball P1', $minus2, '15:00:00', '16:00:00', 60, 150000, 'full_payment', 150000, 'online', 'single', 'cancelled', null, 'Hủy không yêu cầu hoàn tiền.', 'user1'],
            ['BK-CANC-08', 'user2', 'Sân cầu lông A1',  $minus6, '09:00:00', '10:00:00', 60, 120000, 'deposit',      36000,  'online', 'single', 'cancelled', null, 'Khách hủy booking đặt cọc sớm.', 'user2'],
            ['BK-CANC-09', 'user3', 'Sân pickleball P2', $minus3, '17:00:00', '18:00:00', 60, 160000, 'full_payment', 160000, 'online', 'single', 'cancelled', null, 'Bận công việc đột xuất.', 'user3'],
            ['BK-CANC-10', 'user4', 'Sân cầu lông A2',  $minus5, '14:00:00', '15:00:00', 60, 130000, 'full_payment', 130000, 'online', 'single', 'cancelled', null, 'Khách hủy do di chuyển xa.', 'user4'],

            // ===== COUNTER / WALK-IN (3) =====
            ['BK-COUN-01', null, 'Sân cầu lông A1',  $today,    '18:00:00', '18:30:00', 30, 60000,  'no_prepay', 0, 'counter', 'single', 'confirmed', 'Nguyễn Văn A', null, 'owner'],
            ['BK-COUN-02', null, 'Sân cầu lông A2',  $today,    '19:00:00', '20:00:00', 60, 130000, 'no_prepay', 0, 'counter', 'single', 'confirmed', 'Trần Thị B',   null, 'owner'],
            ['BK-COUN-03', null, 'Sân pickleball P1', $tomorrow, '08:00:00', '09:00:00', 60, 150000, 'no_prepay', 0, 'counter', 'single', 'confirmed', 'Lê Hoàng C',   null, 'owner'],

            // ===== EXPIRED (2) — hết hạn thanh toán =====
            ['BK-EXP-01', 'user',  'Sân pickleball P2', $today,     '06:00:00', '07:00:00', 60, 160000, 'full_payment', 160000, 'online', 'single', 'expired', null, 'Hết thời gian thanh toán.', 'user'],
            ['BK-EXP-02', 'user1', 'Sân cầu lông A1',  $today,     '07:00:00', '08:00:00', 60, 120000, 'deposit',      36000,  'online', 'single', 'expired', null, 'Hết thời gian thanh toán.', 'user1'],

            // ===== COMPLETED (2) — đã paid, đã hoàn thành (khách không đến) =====
            ['BK-NOSHOW-01', 'user2', 'Sân cầu lông A2',  $yesterday, '09:00:00', '10:00:00', 60, 130000, 'full_payment', 130000, 'online', 'single', 'completed', null, 'Khách không đến chơi.', 'user2'],
            ['BK-NOSHOW-02', 'user3', 'Sân pickleball P1', $yesterday, '11:00:00', '12:00:00', 60, 150000, 'full_payment', 150000, 'online', 'single', 'completed', null, 'Khách không đến chơi.', 'user3'],
        ];

        foreach ($bookings as [$code, $customerUsername, $courtName, $date, $startTime, $endTime, $duration, $total, $paymentOption, $requiredAmount, $source, $bookingType, $status, $walkInName, $reason, $createdByUsername]) {
            $court = $courts[$courtName] ?? null;
            $customer = $customerUsername ? ($customers[$customerUsername] ?? null) : null;
            $createdBy = $createdByUsername === 'owner' ? $owner : ($customers[$createdByUsername] ?? null);

            if (! $court) {
                continue;
            }

            Booking::query()->updateOrCreate(
                ['booking_code' => $code],
                [
                    'customer_id' => $customer?->id,
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
                    'walk_in_phone' => $walkInName ? '090' . rand(1000000, 9999999) : null,
                    'status_reason' => $reason,
                    'cancelled_by' => $status === 'cancelled' ? $customer?->id : null,
                    'cancelled_at' => $status === 'cancelled' ? now()->subDay() : null,
                    'created_by' => $createdBy?->id,
                    'court_changed_by' => null,
                    'court_changed_at' => null,
                    'court_changed_reason' => null,
                    'reminder_sent_at' => null,
                ],
            );
        }
    }
}
