<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealisticOwnerDashboardSeeder extends Seeder
{
    private CarbonImmutable $today;
    private int $ownerId = 9; // Nguyễn Minh Quân
    private array $customers = [];

    public function run(): void
    {
        $this->today = CarbonImmutable::parse('2026-08-21 17:00:00', config('app.timezone'));
        $bookingSequence = 0;
        $paymentSequence = 0;

        // 1. Resolve Owner and Customers
        $owner = DB::table('users')->where('id', $this->ownerId)->first();
        if (! $owner) {
            $this->command->error("Owner with ID {$this->ownerId} not found.");
            return;
        }

        $customerUsers = DB::table('users')
            ->whereIn('id', [12, 13, 14, 15, 16, 22, 25, 26, 27, 28, 29])
            ->get()
            ->all();

        if (empty($customerUsers)) {
            $customerUsers = DB::table('users')->where('id', '!=', $this->ownerId)->limit(8)->get()->all();
        }
        $this->customers = $customerUsers;

        // 2. Clear old test bookings & related records for Owner clusters
        $ownerClusterIds = DB::table('venue_clusters')
            ->where('owner_id', $this->ownerId)
            ->pluck('id')
            ->all();

        if (empty($ownerClusterIds)) {
            return;
        }

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $oldBookingIds = DB::table('bookings')
            ->whereIn('venue_cluster_id', $ownerClusterIds)
            ->pluck('id')
            ->all();

        if (! empty($oldBookingIds)) {
            $oldRefundIds = DB::table('refunds')->whereIn('booking_id', $oldBookingIds)->pluck('id')->all();
            if (! empty($oldRefundIds)) {
                DB::table('refund_status_histories')->whereIn('refund_id', $oldRefundIds)->delete();
            }
            $oldPaymentIds = DB::table('payments')->whereIn('booking_id', $oldBookingIds)->pluck('id')->all();
            if (! empty($oldPaymentIds)) {
                DB::table('payment_logs')->whereIn('payment_id', $oldPaymentIds)->delete();
            }
            DB::table('complaints')->whereIn('booking_id', $oldBookingIds)->delete();
            DB::table('refunds')->whereIn('booking_id', $oldBookingIds)->delete();
            DB::table('payments')->whereIn('booking_id', $oldBookingIds)->delete();
            DB::table('booking_items')->whereIn('booking_id', $oldBookingIds)->delete();
            DB::table('bookings')->whereIn('id', $oldBookingIds)->delete();
        }

        DB::table('complaints')->whereIn('venue_cluster_id', $ownerClusterIds)->delete();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // 3. Seed for Diamond Sport Thanh Xuân (Cluster 7) and Green Sport Ba Đình (Cluster 1/4)
        $clusters = DB::table('venue_clusters')
            ->whereIn('id', [7, 1, 4])
            ->get();

        $totalEarnedCluster7 = 0;
        $totalEarnedCluster1 = 0;

        foreach ($clusters as $cluster) {
            $courts = DB::table('venue_courts')
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'active')
                ->get()
                ->all();

            if (empty($courts)) {
                continue;
            }

            // Get unit base price for cluster
            $basePriceRecord = DB::table('venue_base_prices')
                ->where('venue_cluster_id', $cluster->id)
                ->first();
            $unitPricePerHour = $basePriceRecord ? (float) $basePriceRecord->price : 110000.0;

            // Generate 30 days of data (from 2026-07-23 to 2026-08-21)
            for ($dayOffset = 30; $dayOffset >= 0; $dayOffset--) {
                $targetDate = $this->today->subDays($dayOffset);
                $dateStr = $targetDate->toDateString();
                $isToday = ($dayOffset === 0);

                // Bookings count per day: more on weekends (Saturday=6, Sunday=0)
                $dayOfWeek = $targetDate->dayOfWeek;
                $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
                $bookingsCount = $isToday ? 10 : ($isWeekend ? rand(6, 9) : rand(3, 6));

                // Time slots pool with peak hours between 17:00 and 22:00
                $availableSlots = [
                    ['06:00:00', '07:00:00', 60],
                    ['07:00:00', '08:30:00', 90],
                    ['08:30:00', '10:00:00', 90],
                    ['14:00:00', '15:30:00', 90],
                    ['15:30:00', '17:00:00', 90],
                    ['17:00:00', '18:30:00', 90],
                    ['18:00:00', '20:00:00', 120],
                    ['19:00:00', '21:00:00', 120],
                    ['20:00:00', '22:00:00', 120],
                    ['21:00:00', '22:30:00', 90],
                ];

                shuffle($availableSlots);
                $selectedSlots = array_slice($availableSlots, 0, min($bookingsCount, count($availableSlots)));

                foreach ($selectedSlots as $slotIdx => $slotInfo) {
                    [$startTime, $endTime, $durationMinutes] = $slotInfo;
                    $court = $courts[$slotIdx % count($courts)];
                    $customer = $this->customers[($slotIdx + $dayOffset) % count($this->customers)];
                    $isOnline = ($slotIdx % 3 !== 0); // 67% Online, 33% Walk-in counter

                    // Calculate strict price
                    $hours = $durationMinutes / 60.0;
                    $totalPrice = round($hours * $unitPricePerHour, -3); // e.g. 110,000 or 165,000 or 220,000
                    $discountAmount = 0;
                    $finalAmount = $totalPrice;

                    // Apply occasional 10% voucher
                    if ($isOnline && $slotIdx % 4 === 0) {
                        $discountAmount = round($totalPrice * 0.1, -3);
                        $finalAmount = $totalPrice - $discountAmount;
                    }

                    // Determine realistic status based on date and time
                    if (! $isToday) {
                        // Past days are mostly completed, 5% cancelled
                        $status = ($slotIdx === 1 && $dayOffset % 5 === 0) ? 'cancelled' : 'completed';
                    } else {
                        // Today's schedule:
                        if ($startTime < '15:00:00') {
                            $status = 'completed';
                        } elseif ($startTime >= '15:00:00' && $startTime <= '18:00:00') {
                            $status = 'checked_in'; // Playing right now!
                        } elseif ($startTime > '18:00:00' && $slotIdx % 4 === 0) {
                            $status = 'pending_approval'; // Needs owner review
                        } elseif ($startTime > '18:00:00' && $slotIdx % 4 === 1) {
                            $status = 'pending_payment'; // Waiting customer payment
                        } else {
                            $status = 'confirmed'; // Confirmed for tonight
                        }
                    }

                    $bookingCode = sprintf('BK%s%02d%05d', $targetDate->format('ymd'), $cluster->id, ++$bookingSequence);

                    $bookingId = DB::table('bookings')->insertGetId([
                        'booking_code' => $bookingCode,
                        'customer_id' => $customer->id,
                        'venue_court_id' => $court->id,
                        'requested_venue_court_id' => $court->id,
                        'venue_cluster_id' => $cluster->id,
                        'booking_date' => $dateStr,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'duration_minutes' => $durationMinutes,
                        'total_price' => $totalPrice,
                        'original_amount' => $totalPrice,
                        'discount_amount' => $discountAmount,
                        'system_discount_amount' => $discountAmount,
                        'venue_discount_amount' => 0,
                        'final_amount' => $finalAmount,
                        'voucher_id' => $discountAmount > 0 ? 1 : null,
                        'voucher_code_snapshot' => $discountAmount > 0 ? 'SPORTGO10' : null,
                        'payment_option' => 'full_payment',
                        'required_payment_amount' => $finalAmount,
                        'source' => $isOnline ? 'online' : 'counter',
                        'booking_type' => 'single',
                        'status' => $status,
                        'walk_in_name' => $isOnline ? null : $customer->full_name,
                        'walk_in_phone' => $isOnline ? null : $customer->phone,
                        'created_by' => $customer->id,
                        'created_at' => $targetDate->setTime(8, 0, 0)->toDateTimeString(),
                        'updated_at' => $targetDate->toDateTimeString(),
                    ]);

                    // Insert matching Booking Item
                    DB::table('booking_items')->insert([
                        'booking_id' => $bookingId,
                        'venue_court_id' => $court->id,
                        'requested_venue_court_id' => $court->id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'duration_minutes' => $durationMinutes,
                        'unit_price' => $unitPricePerHour,
                        'subtotal' => $totalPrice,
                        'status' => $status,
                        'created_at' => $targetDate->toDateTimeString(),
                        'updated_at' => $targetDate->toDateTimeString(),
                    ]);

                    // Insert Payment if booking is paid (confirmed, checked_in, completed)
                    if (in_array($status, ['confirmed', 'checked_in', 'completed'])) {
                        $paymentCode = sprintf('PAY%s%02d%05d', $targetDate->format('ymd'), $cluster->id, ++$paymentSequence);
                        DB::table('payments')->insert([
                            'payment_code' => $paymentCode,
                            'payment_context' => 'booking',
                            'booking_id' => $bookingId,
                            'amount' => $finalAmount,
                            'wallet_amount' => 0,
                            'gateway_amount' => $finalAmount,
                            'payment_kind' => 'full',
                            'method' => $isOnline ? 'sepay' : 'cash',
                            'status' => 'paid',
                            'paid_at' => $targetDate->setTime(8, 15, 0)->toDateTimeString(),
                            'created_at' => $targetDate->toDateTimeString(),
                            'updated_at' => $targetDate->toDateTimeString(),
                        ]);

                        if ($cluster->id === 7) {
                            $totalEarnedCluster7 += $finalAmount;
                        } elseif ($cluster->id === 1 || $cluster->id === 4) {
                            $totalEarnedCluster1 += $finalAmount;
                        }
                    }
                }
            }
        }

        // 4. Update Owner Wallets with matching exact math
        $pendingWithdrawalAmt = 1500000.0;
        $totalWithdrawnAmt = 5000000.0;

        // Ensure owner wallet exists for Diamond Sport Thanh Xuân (cluster 7)
        $availBalanceCluster7 = max(0, $totalEarnedCluster7 - $pendingWithdrawalAmt - $totalWithdrawnAmt);
        DB::table('owner_wallets')->updateOrInsert(
            ['owner_id' => $this->ownerId, 'venue_cluster_id' => 7],
            [
                'available_balance' => $availBalanceCluster7,
                'pending_withdrawal_balance' => $pendingWithdrawalAmt,
                'total_earned' => $totalEarnedCluster7,
                'total_withdrawn' => $totalWithdrawnAmt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Ensure owner wallet for Green Sport Ba Đình (cluster 1)
        DB::table('owner_wallets')->updateOrInsert(
            ['owner_id' => $this->ownerId, 'venue_cluster_id' => 1],
            [
                'available_balance' => max(0, $totalEarnedCluster1 - 500000),
                'pending_withdrawal_balance' => 0,
                'total_earned' => $totalEarnedCluster1,
                'total_withdrawn' => 500000,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 5. Seed 1 Pending Owner Withdrawal Request for Diamond Sport Thanh Xuân
        $ownerWallet = DB::table('owner_wallets')->where('owner_id', $this->ownerId)->where('venue_cluster_id', 7)->first();
        $bankAccount = DB::table('owner_bank_accounts')->where('owner_id', $this->ownerId)->first();

        if ($ownerWallet && $bankAccount) {
            DB::table('owner_withdrawal_requests')->where('owner_id', $this->ownerId)->where('status', 'pending')->delete();
            DB::table('owner_withdrawal_requests')->insert([
                'request_code' => 'WDR' . date('ymd') . '0001',
                'source' => 'manual',
                'auto_created' => 0,
                'owner_id' => $this->ownerId,
                'owner_wallet_id' => $ownerWallet->id,
                'owner_bank_account_id' => $bankAccount->id,
                'amount' => $pendingWithdrawalAmt,
                'status' => 'pending',
                'owner_note' => 'Rút doanh thu tuần 3 tháng 8/2026',
                'requested_at' => $this->today->subHours(3)->toDateTimeString(),
                'created_at' => $this->today->subHours(3)->toDateTimeString(),
                'updated_at' => $this->today->subHours(3)->toDateTimeString(),
            ]);
        }

        // 6. Seed 1 Pending Refund Request linked to a real booking
        $cancelledBooking = DB::table('bookings')
            ->where('venue_cluster_id', 7)
            ->where('status', 'completed')
            ->first();

        if ($cancelledBooking) {
            $payment = DB::table('payments')->where('booking_id', $cancelledBooking->id)->first();
            if ($payment) {
                DB::table('refunds')->where('customer_id', $cancelledBooking->customer_id)->where('status', 'pending_owner_confirmation')->delete();
                DB::table('refunds')->insert([
                    'payment_id' => $payment->id,
                    'booking_id' => $cancelledBooking->id,
                    'customer_id' => $cancelledBooking->customer_id,
                    'amount' => round((float) $cancelledBooking->final_amount * 0.8, -3), // 80% refund policy
                    'refund_destination' => 'user_wallet',
                    'reason' => 'Khách hàng bận lịch đột xuất, xin hoàn 80% tiền sân theo chính sách hoàn hủy.',
                    'status' => 'pending_owner_confirmation',
                    'created_at' => $this->today->subHours(2)->toDateTimeString(),
                    'updated_at' => $this->today->subHours(2)->toDateTimeString(),
                ]);
            }
        }

        // 7. Seed 1 Open Complaint linked to Diamond Sport Thanh Xuân
        DB::table('complaints')->insert([
            'complaint_type' => 'venue',
            'is_vip_priority' => 0,
            'booking_id' => $cancelledBooking ? $cancelledBooking->id : null,
            'venue_cluster_id' => 7,
            'customer_id' => $this->customers[0]->id,
            'content' => 'Khách hàng phản ánh đèn góc sân A2 hơi chói mắt vào ca tối, nhờ chủ sân kiểm tra chụp đèn.',
            'status' => 'open',
            'created_at' => $this->today->subHours(4)->toDateTimeString(),
            'updated_at' => $this->today->subHours(4)->toDateTimeString(),
        ]);
    }
}
