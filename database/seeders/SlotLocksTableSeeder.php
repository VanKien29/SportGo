<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SlotLocksTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('slot_locks') || ! Schema::hasTable('bookings') || ! Schema::hasTable('venue_courts')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $cluster = VenueCluster::query()->where('slug', 'sportgo-cau-giay')->first();
        $courts = VenueCourt::query()->whereIn('name', [
            'Sân cầu lông A1',
            'Sân cầu lông A2',
            'Sân pickleball P1',
            'Sân pickleball P2',
        ])->get()->keyBy('name');

        if (! $cluster || ! $owner || $courts->isEmpty()) {
            return;
        }

        $tomorrow = now()->addDay()->toDateString();
        $plus2 = now()->addDays(2)->toDateString();
        $plus3 = now()->addDays(3)->toDateString();
        $plus4 = now()->addDays(4)->toDateString();
        $plus5 = now()->addDays(5)->toDateString();
        $plus6 = now()->addDays(6)->toDateString();
        $today = now()->toDateString();

        $hasBookingItemId = Schema::hasColumn('slot_locks', 'booking_item_id');
        $hasReason = Schema::hasColumn('slot_locks', 'reason');

        // ===== AUTO LOCKS — cho confirmed bookings tương lai =====
        $autoLockBookings = [
            // [booking_code, court_name, date, start, end]
            ['BK-CONF-05', 'Sân cầu lông A1',  $tomorrow, '08:00:00', '09:00:00'],
            ['BK-CONF-06', 'Sân cầu lông A2',  $tomorrow, '10:00:00', '11:00:00'],
            ['BK-CONF-07', 'Sân pickleball P1', $plus2,    '09:00:00', '10:00:00'],
            ['BK-CONF-08', 'Sân cầu lông A1',  $plus3,    '15:00:00', '16:00:00'],
            // Counter bookings tương lai
            ['BK-COUN-03', 'Sân pickleball P1', $tomorrow, '08:00:00', '09:00:00'],
        ];

        foreach ($autoLockBookings as [$bookingCode, $courtName, $date, $start, $end]) {
            $booking = Booking::query()->where('booking_code', $bookingCode)->first();
            $court = $courts[$courtName] ?? null;

            if (! $booking || ! $court) {
                continue;
            }

            $bookingItem = BookingItem::query()->where('booking_id', $booking->id)->first();

            $data = [
                'venue_cluster_id' => $cluster->id,
                'venue_court_id' => $court->id,
                'lock_scope' => 'court',
                'booking_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'locked_by' => 'system:booking',
                'booking_id' => $booking->id,
                'lock_type' => 'auto',
                'expires_at' => now()->addDays(30),
            ];

            if ($hasBookingItemId && $bookingItem) {
                $data['booking_item_id'] = $bookingItem->id;
            }

            if ($hasReason) {
                $data['reason'] = 'Khóa tự động khi booking được xác nhận.';
            }

            SlotLock::query()->updateOrCreate(
                [
                    'venue_court_id' => $court->id,
                    'booking_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'booking_id' => $booking->id,
                ],
                $data,
            );
        }

        // ===== AUTO LOCKS — cho pending bookings (có expires_at gần) =====
        $pendingLockBookings = [
            // [booking_code, court_name, date, start, end]
            ['BK-PEND-01', 'Sân pickleball P1', $tomorrow, '17:00:00', '18:00:00'],
            ['BK-PEND-02', 'Sân cầu lông A2',  $tomorrow, '14:00:00', '15:00:00'],
            ['BK-PEND-03', 'Sân pickleball P2', $plus2,    '08:00:00', '09:00:00'],
            ['BK-PEND-04', 'Sân cầu lông A1',  $plus2,    '10:00:00', '11:00:00'],
            ['BK-PEND-05', 'Sân pickleball P1', $plus3,    '14:00:00', '15:00:00'],
        ];

        foreach ($pendingLockBookings as [$bookingCode, $courtName, $date, $start, $end]) {
            $booking = Booking::query()->where('booking_code', $bookingCode)->first();
            $court = $courts[$courtName] ?? null;

            if (! $booking || ! $court) {
                continue;
            }

            $bookingItem = BookingItem::query()->where('booking_id', $booking->id)->first();

            $data = [
                'venue_cluster_id' => $cluster->id,
                'venue_court_id' => $court->id,
                'lock_scope' => 'court',
                'booking_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'locked_by' => 'system:pending_hold',
                'booking_id' => $booking->id,
                'lock_type' => 'auto',
                'expires_at' => now()->addMinutes(20), // slot hold 20 phút
            ];

            if ($hasBookingItemId && $bookingItem) {
                $data['booking_item_id'] = $bookingItem->id;
            }

            if ($hasReason) {
                $data['reason'] = 'Giữ slot chờ thanh toán (20 phút).';
            }

            SlotLock::query()->updateOrCreate(
                [
                    'venue_court_id' => $court->id,
                    'booking_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'booking_id' => $booking->id,
                ],
                $data,
            );
        }

        // ===== MANUAL LOCKS — khóa sân thủ công bởi owner =====
        $manualLocks = [
            // [court_name, date, start, end, lock_scope, reason]
            ['Sân cầu lông A1', $plus4, '06:00:00', '12:00:00', 'court', 'Bảo trì sân A1 buổi sáng — sửa chữa lưới và thay đèn.'],
            ['Sân cầu lông A1', $plus4, '13:00:00', '18:00:00', 'court', 'Bảo trì sân A1 buổi chiều — sơn lại vạch sân.'],
            ['Sân pickleball P2', $plus5, '08:00:00', '22:00:00', 'court', 'Sân P2 tạm đóng cả ngày — sự kiện thi đấu nội bộ.'],
            ['Sân cầu lông A2', $plus5, '18:00:00', '22:00:00', 'court', 'Khóa sân A2 buổi tối — cho thuê riêng sự kiện.'],
            [null, $plus6, '06:00:00', '22:00:00', 'cluster', 'Bảo trì tổng thể cả cụm sân — vệ sinh định kỳ.'],
            ['Sân pickleball P1', $plus3, '06:00:00', '08:00:00', 'court', 'Sân P1 bảo trì sáng sớm.'],
        ];

        foreach ($manualLocks as [$courtName, $date, $start, $end, $lockScope, $reason]) {
            $court = $courtName ? ($courts[$courtName] ?? null) : null;

            // Nếu khóa theo court mà không tìm thấy court thì skip
            if ($lockScope === 'court' && ! $court) {
                continue;
            }

            $data = [
                'venue_cluster_id' => $cluster->id,
                'venue_court_id' => $court?->id,
                'lock_scope' => $lockScope,
                'booking_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'locked_by' => 'owner:' . $owner->username,
                'booking_id' => null,
                'lock_type' => 'manual',
                'expires_at' => now()->addDays(30),
            ];

            if ($hasReason) {
                $data['reason'] = $reason;
            }

            $uniqueKey = [
                'venue_cluster_id' => $cluster->id,
                'booking_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'lock_type' => 'manual',
            ];

            if ($court) {
                $uniqueKey['venue_court_id'] = $court->id;
            } else {
                $uniqueKey['lock_scope'] = 'cluster';
            }

            SlotLock::query()->updateOrCreate($uniqueKey, $data);
        }

        // ===== AUTO LOCKS cho bookings hôm nay (confirmed, counter) =====
        $todayLockBookings = [
            ['BK-CONF-03', 'Sân pickleball P1', $today, '14:00:00', '15:00:00'],
            ['BK-CONF-04', 'Sân pickleball P2', $today, '16:00:00', '17:00:00'],
            ['BK-COUN-01', 'Sân cầu lông A1',  $today, '18:00:00', '18:30:00'],
            ['BK-COUN-02', 'Sân cầu lông A2',  $today, '19:00:00', '20:00:00'],
        ];

        foreach ($todayLockBookings as [$bookingCode, $courtName, $date, $start, $end]) {
            $booking = Booking::query()->where('booking_code', $bookingCode)->first();
            $court = $courts[$courtName] ?? null;

            if (! $booking || ! $court) {
                continue;
            }

            $bookingItem = BookingItem::query()->where('booking_id', $booking->id)->first();

            $data = [
                'venue_cluster_id' => $cluster->id,
                'venue_court_id' => $court->id,
                'lock_scope' => 'court',
                'booking_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'locked_by' => 'system:booking',
                'booking_id' => $booking->id,
                'lock_type' => 'auto',
                'expires_at' => now()->addDays(1),
            ];

            if ($hasBookingItemId && $bookingItem) {
                $data['booking_item_id'] = $bookingItem->id;
            }

            if ($hasReason) {
                $data['reason'] = 'Khóa tự động khi booking được xác nhận.';
            }

            SlotLock::query()->updateOrCreate(
                [
                    'venue_court_id' => $court->id,
                    'booking_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'booking_id' => $booking->id,
                ],
                $data,
            );
        }
    }
}
