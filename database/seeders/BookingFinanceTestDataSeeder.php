<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingFinanceTestDataSeeder extends Seeder
{
    private CarbonImmutable $baseDate;

    private array $ownerWalletBalances = [];

    private array $ownerWalletPending = [];

    private array $ownerWalletEarned = [];

    private array $ownerWalletWithdrawn = [];

    private array $userWalletBalances = [];

    private int $ownerLedgerSequence = 0;

    private int $userLedgerSequence = 0;

    private int $paymentLogSequence = 0;

    public function run(): void
    {
        if (! $this->hasRequiredTables()) {
            return;
        }

        $this->baseDate = CarbonImmutable::parse('2026-06-20 09:00:00', config('app.timezone'));
        $this->clearScenarioData();

        $admin = $this->user('admin');
        $owner = $this->user('owner');
        $ownerSun = $this->user('owner_sun');
        $staff = $this->user('venuestaff');
        $customerNam = $this->user('user');
        $customerLinh = $this->user('user1');
        $customerChau = $this->user('user2');
        $customerHa = $this->user('user3');

        $cluster = DB::table('venue_clusters')->where('slug', 'green-sport-ba-dinh')->first();
        if (! $admin || ! $owner || ! $staff || ! $customerNam || ! $customerLinh || ! $customerChau || ! $customerHa || ! $cluster) {
            return;
        }

        $courts = DB::table('venue_courts')
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->values();

        if ($courts->count() < 2) {
            return;
        }

        $systemBankId = $this->systemBankAccount();
        $ownerBankId = $this->ownerBankAccount($owner->id);
        $ownerWalletId = $this->ownerWallet($owner->id, $cluster->id);

        foreach ([$customerNam, $customerLinh, $customerChau, $customerHa] as $customer) {
            $walletId = $this->userWallet($customer->id, 0);
            $this->userPayoutAccount($customer);
        }

        $systemVoucher = DB::table('vouchers')->where('code', 'SPORTGO10')->first();
        $venueVoucher = DB::table('vouchers')->where('code', 'GREEN20')->first();

        $bookingA = $this->booking([
            'booking_code' => 'BOOKING_0001',
            'customer_id' => $customerNam->id,
            'venue_court_id' => $courts[0]->id,
            'requested_venue_court_id' => $courts[0]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $this->baseDate->addDays(2)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'duration_minutes' => 60,
            'total_price' => 140000,
            'original_amount' => 140000,
            'discount_amount' => 14000,
            'system_discount_amount' => 14000,
            'venue_discount_amount' => 0,
            'final_amount' => 126000,
            'voucher_id' => $systemVoucher?->id,
            'voucher_code_snapshot' => $systemVoucher?->code,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 126000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
            'created_by' => $customerNam->id,
            'created_at' => $this->baseDate->subDays(3),
            'updated_at' => $this->baseDate->subDays(3),
        ], [[
            'court_id' => $courts[0]->id,
            'start' => '18:00:00',
            'end' => '19:00:00',
            'price' => 140000,
            'status' => 'active',
        ]]);

        $paymentA = $this->payment($bookingA, $systemBankId, 126000, 'PAYMENT_0001', 'full', 'sepay', 'paid', 1);
        $this->paymentLog($paymentA['id'], 'payment_paid', null, 'paid', $paymentA['gateway_txn_id']);
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $bookingA['id'], $paymentA['id'], 126000, 'Khách thanh toán booking BOOKING_0001.');
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $bookingA['id'], null, 14000, 'SportGo bù phần voucher hệ thống cho BOOKING_0001.', 'voucher_subsidy', $bookingA['id']);
        $this->systemWalletOut($systemBankId, 14000, 'SYS_VOUCHER_BOOKING_0001', 'booking', $bookingA['id'], 'SportGo chịu voucher 14.000đ cho BOOKING_0001.');
        $this->voucherUsage($systemVoucher?->id, $customerNam->id, $bookingA['id'], $paymentA['id'], 14000);

        $bookingB = $this->booking([
            'booking_code' => 'BOOKING_0002',
            'customer_id' => $customerLinh->id,
            'venue_court_id' => $courts[1]->id,
            'requested_venue_court_id' => $courts[1]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $this->baseDate->addDays(3)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
            'duration_minutes' => 60,
            'total_price' => 140000,
            'original_amount' => 140000,
            'discount_amount' => 20000,
            'system_discount_amount' => 0,
            'venue_discount_amount' => 20000,
            'final_amount' => 120000,
            'venue_voucher_id' => $venueVoucher?->id,
            'venue_voucher_code_snapshot' => $venueVoucher?->code,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 120000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
            'created_by' => $customerLinh->id,
            'created_at' => $this->baseDate->subDays(2),
            'updated_at' => $this->baseDate->subDays(2),
        ], [[
            'court_id' => $courts[1]->id,
            'start' => '19:00:00',
            'end' => '20:00:00',
            'price' => 140000,
            'status' => 'active',
        ]]);

        $paymentB = $this->payment($bookingB, $systemBankId, 120000, 'PAYMENT_0002', 'full', 'sepay', 'paid', 2);
        $this->paymentLog($paymentB['id'], 'payment_paid', null, 'paid', $paymentB['gateway_txn_id']);
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $bookingB['id'], $paymentB['id'], 120000, 'Khách thanh toán booking BOOKING_0002 sau voucher chủ sân.');
        $this->voucherUsage($venueVoucher?->id, $customerLinh->id, $bookingB['id'], $paymentB['id'], 20000);

        $bookingRefund = $this->booking([
            'booking_code' => 'BOOKING_0003',
            'customer_id' => $customerNam->id,
            'venue_court_id' => $courts[0]->id,
            'requested_venue_court_id' => $courts[0]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $this->baseDate->addDays(4)->toDateString(),
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'original_amount' => 100000,
            'discount_amount' => 0,
            'system_discount_amount' => 0,
            'venue_discount_amount' => 0,
            'final_amount' => 100000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
            'status_reason' => 'Khách đã gửi yêu cầu hoàn tiền, đang chờ chủ sân xác nhận.',
            'created_by' => $customerNam->id,
            'created_at' => $this->baseDate->subDay(),
            'updated_at' => $this->baseDate->subDay(),
        ], [[
            'court_id' => $courts[0]->id,
            'start' => '07:00:00',
            'end' => '08:00:00',
            'price' => 100000,
            'status' => 'active',
        ]]);

        $paymentRefund = $this->payment($bookingRefund, $systemBankId, 100000, 'PAYMENT_0003', 'full', 'sepay', 'paid', 3);
        $this->paymentLog($paymentRefund['id'], 'payment_paid', null, 'paid', $paymentRefund['gateway_txn_id']);
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $bookingRefund['id'], $paymentRefund['id'], 100000, 'Khách thanh toán booking BOOKING_0003.');
        $this->refund($paymentRefund, $bookingRefund, $customerNam->id, $staff->id, 80000, 'pending_owner_confirmation', 'Khách bận đột xuất, yêu cầu hoàn 80% theo chính sách hiện tại.');

        $this->ownerWithdrawal($owner->id, $ownerWalletId, $ownerBankId, $admin->id, 'WD_OWNER_0001', 80000, 'pending');
        $this->ownerWithdrawal($owner->id, $ownerWalletId, $ownerBankId, $admin->id, 'WD_OWNER_0002', 100000, 'completed');

        $this->seedAdditionalOwnerBookings($owner, $cluster, $courts, $customerChau, $customerHa, $systemBankId, $ownerWalletId);
        $this->seedMembershipSettings($cluster->id);

        if ($ownerSun) {
            $this->seedOwnerSunScenario($ownerSun, $admin, $customerChau, $customerHa, $systemBankId);
        }

        $this->syncWalletSnapshots();
    }

    private function seedAdditionalOwnerBookings(
        object $owner,
        object $cluster,
        object $courts,
        object $customerChau,
        object $customerHa,
        ?string $systemBankId,
        string $ownerWalletId,
    ): void {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        $completed = $this->booking([
            'booking_code' => 'BOOKING_0004',
            'customer_id' => $customerChau->id,
            'venue_court_id' => $courts[0]->id,
            'requested_venue_court_id' => $courts[0]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->subDays(5)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'duration_minutes' => 60,
            'total_price' => 140000,
            'original_amount' => 140000,
            'final_amount' => 140000,
            'required_payment_amount' => 140000,
            'payment_option' => 'full_payment',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'completed',
            'created_by' => $customerChau->id,
            'created_at' => $this->baseDate->subDay(),
            'updated_at' => $this->baseDate->subDay(),
        ], [[
            'court_id' => $courts[0]->id,
            'start' => '18:00:00',
            'end' => '19:00:00',
            'price' => 140000,
        ]]);
        $payment = $this->payment($completed, $systemBankId, 140000, 'PAYMENT_0004', 'full', 'sepay', 'paid', 4);
        $this->paymentLog($payment['id'], 'payment_paid', null, 'paid', $payment['gateway_txn_id']);
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $completed['id'], $payment['id'], 140000, 'Khách thanh toán booking BOOKING_0004.');

        $this->booking([
            'booking_code' => 'BOOKING_0005',
            'customer_id' => $customerHa->id,
            'venue_court_id' => $courts[1]->id,
            'requested_venue_court_id' => $courts[1]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->addDays(2)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
            'duration_minutes' => 60,
            'total_price' => 140000,
            'original_amount' => 140000,
            'final_amount' => 140000,
            'required_payment_amount' => 0,
            'payment_option' => 'no_prepay',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_approval',
            'created_by' => $customerHa->id,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ], [[
            'court_id' => $courts[1]->id,
            'start' => '19:00:00',
            'end' => '20:00:00',
            'price' => 140000,
        ]]);

        $pending = $this->booking([
            'booking_code' => 'BOOKING_0006',
            'customer_id' => $customerChau->id,
            'venue_court_id' => $courts[0]->id,
            'requested_venue_court_id' => $courts[0]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->addDays(4)->toDateString(),
            'start_time' => '20:00:00',
            'end_time' => '21:00:00',
            'duration_minutes' => 60,
            'total_price' => 140000,
            'original_amount' => 140000,
            'final_amount' => 140000,
            'required_payment_amount' => 140000,
            'payment_option' => 'full_payment',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
            'created_by' => $customerChau->id,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ], [[
            'court_id' => $courts[0]->id,
            'start' => '20:00:00',
            'end' => '21:00:00',
            'price' => 140000,
        ]]);
        $this->payment($pending, $systemBankId, 140000, 'PAYMENT_0006', 'full', 'sepay', 'pending', 6);
    }

    private function seedOwnerSunScenario(
        object $owner,
        object $admin,
        object $customerChau,
        object $customerHa,
        ?string $systemBankId,
    ): void {
        $cluster = DB::table('venue_clusters')->where('slug', 'sun-sport-cau-giay')->first();
        if (! $cluster) {
            return;
        }

        $courts = DB::table('venue_courts')
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->values();

        if ($courts->count() < 2) {
            return;
        }

        $ownerBankId = $this->ownerBankAccount($owner->id);
        $ownerWalletId = $this->ownerWallet($owner->id, $cluster->id);
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        foreach (range(1, 5) as $index) {
            $code = 'SUN_BOOKING_000'.$index;
            $court = $courts[($index - 1) % 2];
            $amount = $court->id === $courts[0]->id ? 650000 : 900000;
            $booking = $this->booking([
                'booking_code' => $code,
                'customer_id' => $customerChau->id,
                'venue_court_id' => $court->id,
                'requested_venue_court_id' => $court->id,
                'venue_cluster_id' => $cluster->id,
                'booking_date' => $today->subDays(10 - $index)->toDateString(),
                'start_time' => $index % 2 === 0 ? '19:00:00' : '18:00:00',
                'end_time' => $index % 2 === 0 ? '20:00:00' : '19:00:00',
                'duration_minutes' => 60,
                'total_price' => $amount,
                'original_amount' => $amount,
                'final_amount' => $amount,
                'required_payment_amount' => $amount,
                'payment_option' => 'full_payment',
                'source' => 'online',
                'booking_type' => 'single',
                'status' => 'completed',
                'created_by' => $customerChau->id,
                'created_at' => $this->baseDate->subDays($index),
                'updated_at' => $this->baseDate->subDays($index),
            ], [[
                'court_id' => $court->id,
                'start' => $index % 2 === 0 ? '19:00:00' : '18:00:00',
                'end' => $index % 2 === 0 ? '20:00:00' : '19:00:00',
                'price' => $amount,
            ]]);
            $payment = $this->payment($booking, $systemBankId, $amount, 'PAYMENT_SUN_000'.$index, 'full', 'sepay', 'paid', 10 + $index);
            $this->paymentLog($payment['id'], 'payment_paid', null, 'paid', $payment['gateway_txn_id']);
            $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $booking['id'], $payment['id'], $amount, 'Khách thanh toán booking '.$code.'.');
        }

        $confirmed = $this->booking([
            'booking_code' => 'SUN_BOOKING_0006',
            'customer_id' => $customerHa->id,
            'venue_court_id' => $courts[1]->id,
            'requested_venue_court_id' => $courts[1]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->addDay()->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'duration_minutes' => 60,
            'total_price' => 900000,
            'original_amount' => 900000,
            'final_amount' => 900000,
            'required_payment_amount' => 900000,
            'payment_option' => 'full_payment',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
            'created_by' => $customerHa->id,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ], [[
            'court_id' => $courts[1]->id,
            'start' => '18:00:00',
            'end' => '19:00:00',
            'price' => 900000,
        ]]);
        $payment = $this->payment($confirmed, $systemBankId, 900000, 'PAYMENT_SUN_0006', 'full', 'sepay', 'paid', 16);
        $this->paymentLog($payment['id'], 'payment_paid', null, 'paid', $payment['gateway_txn_id']);
        $this->ownerCredit($ownerWalletId, $owner->id, $cluster->id, $confirmed['id'], $payment['id'], 900000, 'Khách thanh toán booking SUN_BOOKING_0006.');

        $this->booking([
            'booking_code' => 'SUN_BOOKING_0007',
            'customer_id' => $customerHa->id,
            'venue_court_id' => $courts[0]->id,
            'requested_venue_court_id' => $courts[0]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->addDays(3)->toDateString(),
            'start_time' => '20:00:00',
            'end_time' => '21:00:00',
            'duration_minutes' => 60,
            'total_price' => 650000,
            'original_amount' => 650000,
            'final_amount' => 650000,
            'required_payment_amount' => 0,
            'payment_option' => 'no_prepay',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_approval',
            'created_by' => $customerHa->id,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ], [[
            'court_id' => $courts[0]->id,
            'start' => '20:00:00',
            'end' => '21:00:00',
            'price' => 650000,
        ]]);

        $pending = $this->booking([
            'booking_code' => 'SUN_BOOKING_0008',
            'customer_id' => $customerHa->id,
            'venue_court_id' => $courts[1]->id,
            'requested_venue_court_id' => $courts[1]->id,
            'venue_cluster_id' => $cluster->id,
            'booking_date' => $today->addDays(5)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
            'duration_minutes' => 60,
            'total_price' => 900000,
            'original_amount' => 900000,
            'final_amount' => 900000,
            'required_payment_amount' => 900000,
            'payment_option' => 'full_payment',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
            'created_by' => $customerHa->id,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ], [[
            'court_id' => $courts[1]->id,
            'start' => '19:00:00',
            'end' => '20:00:00',
            'price' => 900000,
        ]]);
        $this->payment($pending, $systemBankId, 900000, 'PAYMENT_SUN_0008', 'full', 'sepay', 'pending', 18);

        $this->ownerWithdrawal($owner->id, $ownerWalletId, $ownerBankId, $admin->id, 'WD_OWNER_SUN_0001', 500000, 'pending');
        $this->ownerWithdrawal($owner->id, $ownerWalletId, $ownerBankId, $admin->id, 'WD_OWNER_SUN_0002', 800000, 'completed');
        $this->seedMembershipSettings($cluster->id);
    }

    private function seedMembershipSettings(string $clusterId): void
    {
        if (! Schema::hasTable('court_membership_tiers')) {
            return;
        }

        $tiers = [
            ['tier' => 'standard', 'discount_percent' => 0, 'min_bookings' => 0, 'min_spent_amount' => 0],
            ['tier' => 'silver', 'discount_percent' => 3, 'min_bookings' => 5, 'min_spent_amount' => 500000],
            ['tier' => 'gold', 'discount_percent' => 5, 'min_bookings' => 15, 'min_spent_amount' => 2000000],
            ['tier' => 'diamond', 'discount_percent' => 8, 'min_bookings' => 30, 'min_spent_amount' => 5000000],
        ];

        foreach ($tiers as $tier) {
            DB::table('court_membership_tiers')->updateOrInsert(
                [
                    'venue_cluster_id' => $clusterId,
                    'tier' => $tier['tier'],
                ],
                [
                    ...$tier,
                    'maintain_min_bookings' => null,
                    'maintain_min_spent' => null,
                    'maintain_period_months' => null,
                    'updated_at' => $this->baseDate,
                    'created_at' => $this->baseDate,
                ],
            );
        }
    }

    private function hasRequiredTables(): bool
    {
        foreach (['users', 'venue_clusters', 'venue_courts', 'bookings', 'booking_items', 'payments', 'refunds', 'slot_locks'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function clearScenarioData(): void
    {
        foreach ([
            'notifications',
            'internal_receipts',
            'refund_status_histories',
            'payment_logs',
            'voucher_usages',
            'reviews',
            'booking_support_requests',
            'partner_termination_booking_actions',
            'booking_services',
            'complaint_replies',
            'complaints',
            'player_post_participants',
            'player_posts',
            'owner_withdrawal_requests',
            'user_withdrawal_requests',
            'refunds',
            'payments',
            'slot_locks',
            'booking_items',
            'bookings',
            'owner_wallet_ledgers',
            'user_wallet_ledgers',
            'user_payout_accounts',
            'owner_wallets',
            'user_wallets',
            'system_wallet_ledgers',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        // Giữ ID booking mẫu ổn định sau mỗi lần seed để các link demo,
        // notification và browser smoke test không trỏ vào booking cũ.
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('bookings')) {
            DB::statement('ALTER TABLE bookings AUTO_INCREMENT = 1');
        }

        $this->ownerWalletBalances = [];
        $this->ownerWalletPending = [];
        $this->ownerWalletEarned = [];
        $this->ownerWalletWithdrawn = [];
        $this->userWalletBalances = [];
        $this->ownerLedgerSequence = 0;
        $this->userLedgerSequence = 0;
        $this->paymentLogSequence = 0;
    }

    private function booking(array $attrs, array $items): array
    {
        $now = $attrs['created_at'] ?? $this->baseDate;
        $row = array_merge([
            'discount_amount' => 0,
            'system_discount_amount' => 0,
            'venue_discount_amount' => 0,
            'voucher_id' => null,
            'voucher_code_snapshot' => null,
            'venue_voucher_id' => null,
            'venue_voucher_code_snapshot' => null,
            'vip_voucher_id' => null,
            'vip_voucher_code_snapshot' => null,
            'recurring_group_code' => null,
            'recurring_start_date' => null,
            'recurring_end_date' => null,
            'recurrence_type' => null,
            'recurrence_interval' => null,
            'recurrence_days_of_week' => null,
            'recurrence_days_of_month' => null,
            'walk_in_name' => null,
            'walk_in_phone' => null,
            'status_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'court_changed_by' => null,
            'court_changed_at' => null,
            'court_changed_reason' => null,
            'reminder_sent_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attrs);

        $id = DB::table('bookings')->insertGetId($row);
        $row['id'] = $id;

        foreach ($items as $sort => $item) {
            $itemId = DB::table('booking_items')->insertGetId([
                'booking_id' => $id,
                'venue_court_id' => $item['court_id'],
                'requested_venue_court_id' => $item['requested_court_id'] ?? $item['court_id'],
                'start_time' => $item['start'],
                'end_time' => $item['end'],
                'duration_minutes' => $this->minutes($item['start'], $item['end']),
                'unit_price' => $this->hourlyPrice($item['price'], $item['start'], $item['end']),
                'subtotal' => $item['price'],
                'status' => $item['status'] ?? 'active',
                'status_reason' => $item['status_reason'] ?? null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'maintenance_lock_id' => null,
                'court_changed_by' => null,
                'court_changed_at' => null,
                'court_changed_reason' => null,
                'sort_order' => $sort + 1,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);

            $this->slotLock(
                $row['venue_cluster_id'],
                $item['court_id'],
                $row['booking_date'],
                $item['start'],
                $item['end'],
                'booking-'.$row['booking_code'],
                $id,
                $itemId,
                'auto',
                'Giữ chỗ cho booking '.$row['booking_code'],
                $sort,
            );
        }

        return $row;
    }

    private function payment(array $booking, ?string $systemBankId, float $amount, string $paymentCode, string $kind, string $method, string $status, int $index): array
    {
        $row = [
            'payment_code' => $paymentCode,
            'payment_context' => 'booking',
            'booking_id' => $booking['id'],
            'subscription_id' => null,
            'system_bank_account_id' => $systemBankId,
            'user_wallet_id' => null,
            'user_wallet_ledger_id' => null,
            'amount' => $amount,
            'wallet_amount' => $method === 'wallet' ? $amount : 0,
            'gateway_amount' => $method === 'sepay' ? $amount : 0,
            'payment_kind' => $kind,
            'method' => $method,
            'gateway_txn_id' => $status === 'paid' ? 'SEPAY_'.$paymentCode : null,
            'gateway_response' => json_encode(['seed' => true, 'booking_code' => $booking['booking_code'], 'status' => $status], JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'paid_at' => $status === 'paid' ? $this->baseDate->subHours($index) : null,
            'created_at' => $booking['created_at'],
            'updated_at' => $booking['created_at'],
        ];

        $id = DB::table('payments')->insertGetId($row);
        $row['id'] = $id;

        return $row;
    }

    private function refund(array $payment, array $booking, string $customerId, string $staffId, float $amount, string $status, string $reason): void
    {
        $userWalletId = DB::table('user_wallets')->where('user_id', $customerId)->value('id');

        $id = DB::table('refunds')->insertGetId([
            'payment_id' => $payment['id'],
            'booking_id' => $booking['id'],
            'customer_id' => $customerId,
            'amount' => $amount,
            'refund_destination' => 'user_wallet',
            'user_wallet_id' => $userWalletId,
            'user_wallet_ledger_id' => null,
            'user_payout_account_id' => null,
            'owner_wallet_ledger_id' => null,
            'policy_id' => null,
            'policy_rule_id' => null,
            'policy_evaluation_log_id' => null,
            'reason' => $reason,
            'status' => $status,
            'status_reason' => null,
            'owner_confirmed_by' => null,
            'owner_confirmed_at' => null,
            'owner_confirm_note' => null,
            'processed_by' => null,
            'processed_at' => null,
            'admin_confirmed_by' => null,
            'admin_confirmed_at' => null,
            'completed_at' => null,
            'cash_refunded_by' => null,
            'cash_refunded_at' => null,
            'cash_refund_note' => null,
            'gateway_refund_txn_id' => null,
            'payout_transfer_code' => null,
            'payout_qr_created_at' => null,
            'created_at' => $this->baseDate->subHours(2),
            'updated_at' => $this->baseDate->subHours(2),
        ]);

        if (Schema::hasTable('refund_status_histories')) {
            DB::table('refund_status_histories')->insert([
                'refund_id' => $id,
                'old_status' => null,
                'new_status' => $status,
                'changed_by' => $staffId,
                'actor_type' => 'owner',
                'reason' => $reason,
                'metadata' => json_encode(['seed' => true, 'booking_code' => $booking['booking_code']], JSON_UNESCAPED_UNICODE),
                'created_at' => $this->baseDate->subHours(2),
            ]);
        }
    }

    private function ownerWithdrawal(string $ownerId, string $ownerWalletId, string $ownerBankId, string $adminId, string $requestCode, float $amount, string $status): void
    {
        $requestedAt = $status === 'completed'
            ? $this->baseDate->subDays(4)
            : $this->baseDate->subDay();

        $id = DB::table('owner_withdrawal_requests')->insertGetId([
            'request_code' => $requestCode,
            'source' => 'manual',
            'auto_created' => false,
            'owner_id' => $ownerId,
            'owner_wallet_id' => $ownerWalletId,
            'owner_bank_account_id' => $ownerBankId,
            'amount' => $amount,
            'status' => $status,
            'owner_note' => $status === 'pending'
                ? 'Chủ sân yêu cầu rút tiền về tài khoản ngân hàng đã xác minh.'
                : 'Yêu cầu rút tiền đã được SportGo chuyển khoản.',
            'reviewed_by' => $status === 'completed' ? $adminId : null,
            'reviewed_at' => $status === 'completed' ? $requestedAt->addMinutes(20) : null,
            'review_note' => $status === 'completed' ? 'Thông tin ngân hàng hợp lệ.' : null,
            'status_reason' => null,
            'completed_by' => $status === 'completed' ? $adminId : null,
            'completed_at' => $status === 'completed' ? $requestedAt->addHour() : null,
            'transfer_reference' => $status === 'completed' ? 'MB-'.$requestCode : null,
            'payout_transfer_code' => $status === 'completed' ? $requestCode : null,
            'payout_qr_created_at' => $status === 'completed' ? $requestedAt->addMinutes(30) : null,
            'metadata' => json_encode(['seed' => true, 'scenario' => 'owner_withdrawal'], JSON_UNESCAPED_UNICODE),
            'requested_at' => $requestedAt,
            'created_at' => $requestedAt,
            'updated_at' => $requestedAt,
        ]);

        if ($status === 'pending') {
            $this->ownerLedger($ownerWalletId, $ownerId, null, null, null, 'hold', 'debit', $amount, 'withdrawal', $id, 'Giữ tiền cho yêu cầu rút '.$requestCode.'.');
            return;
        }

        if ($status === 'completed') {
            $this->ownerLedger($ownerWalletId, $ownerId, null, null, null, 'debit', 'debit', $amount, 'withdrawal', $id, 'Đã chi trả yêu cầu rút '.$requestCode.'.');
            $this->ownerWalletWithdrawn[$ownerWalletId] = ($this->ownerWalletWithdrawn[$ownerWalletId] ?? 0) + $amount;
            $this->receipt('withdrawal', 'owner_withdrawal_requests', $id, $ownerId, $adminId, 'Phiếu chi rút tiền chủ sân '.$requestCode, $amount, $this->ownerLedgerSequence);
        }
    }

    private function ownerCredit(
        string $walletId,
        string $ownerId,
        string $clusterId,
        string $bookingId,
        ?string $paymentId,
        float $amount,
        string $description,
        string $referenceType = 'payment',
        ?string $referenceId = null
    ): void {
        $this->ownerLedger($walletId, $ownerId, $clusterId, $bookingId, $paymentId, 'credit', 'credit', $amount, $referenceType, $referenceId ?: (string) $paymentId, $description);
        $this->ownerWalletEarned[$walletId] = ($this->ownerWalletEarned[$walletId] ?? 0) + $amount;
    }

    private function ownerLedger(string $walletId, string $ownerId, ?string $clusterId, ?string $bookingId, ?string $paymentId, string $type, string $direction, float $amount, string $referenceType, string $referenceId, string $description): string
    {
        $balanceBefore = $this->ownerWalletBalances[$walletId] ?? 0;
        $balanceAfter = $direction === 'credit' ? $balanceBefore + $amount : $balanceBefore - $amount;
        $this->ownerWalletBalances[$walletId] = $balanceAfter;

        if ($type === 'hold') {
            $this->ownerWalletPending[$walletId] = ($this->ownerWalletPending[$walletId] ?? 0) + $amount;
        }

        $id = DB::table('owner_wallet_ledgers')->insertGetId([
            'owner_wallet_id' => $walletId,
            'owner_id' => $ownerId,
            'venue_cluster_id' => $clusterId,
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'reference_code' => strtoupper($referenceType).'_'.str_pad((string) (++$this->ownerLedgerSequence), 4, '0', STR_PAD_LEFT),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_code' => 'OWNER_LEDGER_'.str_pad((string) $this->ownerLedgerSequence, 4, '0', STR_PAD_LEFT),
            'description' => $description,
            'note' => $description,
            'metadata' => json_encode(['seed' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $this->baseDate->subMinutes($this->ownerLedgerSequence),
            'updated_at' => $this->baseDate->subMinutes($this->ownerLedgerSequence),
        ]);

        return $id;
    }

    private function systemWalletOut(?string $systemBankId, float $amount, string $transactionRef, string $referenceType, string $referenceId, string $description): void
    {
        if (! $systemBankId || ! Schema::hasTable('system_wallet_balances') || ! Schema::hasTable('system_wallet_ledgers')) {
            return;
        }

        $wallet = DB::table('system_wallet_balances')->where('system_bank_account_id', $systemBankId)->first();
        if (! $wallet) {
            return;
        }

        $before = (float) $wallet->current_balance;
        $after = $before - $amount;

        DB::table('system_wallet_ledgers')->insert([
            'system_bank_account_id' => $systemBankId,
            'transaction_ref' => $transactionRef,
            'direction' => 'out',
            'entry_kind' => 'voucher_subsidy',
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'refund_reserved_before' => (float) ($wallet->refund_reserved_balance ?? 0),
            'refund_reserved_after' => (float) ($wallet->refund_reserved_balance ?? 0),
            'voucher_reserved_before' => (float) ($wallet->voucher_reserved_balance ?? 0),
            'voucher_reserved_after' => (float) ($wallet->voucher_reserved_balance ?? 0),
            'transaction_type' => 'other',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => json_encode(['booking_code' => 'BOOKING_0001', 'source' => 'seed'], JSON_UNESCAPED_UNICODE),
            'transacted_at' => $this->baseDate->subHours(1),
            'synced_at' => $this->baseDate,
            'created_at' => $this->baseDate,
        ]);

        DB::table('system_wallet_balances')->where('id', $wallet->id)->update([
            'current_balance' => $after,
            'updated_at' => $this->baseDate,
        ]);
    }

    private function voucherUsage(?string $voucherId, string $userId, string $bookingId, string $paymentId, float $discountAmount): void
    {
        if (! $voucherId || ! Schema::hasTable('voucher_usages')) {
            return;
        }

        DB::table('voucher_usages')->insert([
            'voucher_id' => $voucherId,
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'discount_amount' => $discountAmount,
            'used_at' => $this->baseDate->subHours(1),
            'status' => 'applied',
            'created_at' => $this->baseDate->subHours(1),
            'updated_at' => $this->baseDate->subHours(1),
        ]);

        DB::table('vouchers')->where('id', $voucherId)->increment('used_quantity');
    }

    private function userLedger(string $walletId, string $type, string $direction, float $amount, string $referenceType, string $referenceId, string $note): string
    {
        $before = $this->userWalletBalances[$walletId] ?? 0;
        $after = $direction === 'credit' ? $before + $amount : $before - $amount;
        $this->userWalletBalances[$walletId] = $after;

        $id = DB::table('user_wallet_ledgers')->insertGetId([
            'user_wallet_id' => $walletId,
            'transaction_code' => 'USER_LEDGER_'.str_pad((string) (++$this->userLedgerSequence), 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'note' => $note,
            'created_by' => null,
            'created_at' => $this->baseDate->subMinutes($this->userLedgerSequence),
            'updated_at' => $this->baseDate->subMinutes($this->userLedgerSequence),
        ]);

        return $id;
    }

    private function paymentLog(string $paymentId, string $event, ?string $before, string $after, ?string $gatewayTxnId): void
    {
        if (! Schema::hasTable('payment_logs')) {
            return;
        }

        DB::table('payment_logs')->insert([
            'payment_id' => $paymentId,
            'event_type' => $event,
            'request_payload' => json_encode(['seed' => true], JSON_UNESCAPED_UNICODE),
            'response_payload' => json_encode(['status' => $after], JSON_UNESCAPED_UNICODE),
            'status_before' => $before,
            'status_after' => $after,
            'gateway_txn_id' => $gatewayTxnId,
            'error_code' => null,
            'error_message' => null,
            'created_at' => $this->baseDate->subMinutes(++$this->paymentLogSequence),
        ]);
    }

    private function slotLock(string $clusterId, string $courtId, string $bookingDate, string $start, string $end, string $lockedBy, string $bookingId, string $bookingItemId, string $lockType, string $reason, int $index): void
    {
        DB::table('slot_locks')->insert([
            'venue_cluster_id' => $clusterId,
            'venue_court_id' => $courtId,
            'lock_scope' => 'court',
            'booking_date' => $bookingDate,
            'start_time' => $start,
            'end_time' => $end,
            'locked_by' => $lockedBy,
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'lock_type' => $lockType,
            'reason' => $reason,
            'expires_at' => CarbonImmutable::parse($bookingDate.' '.$end, config('app.timezone'))->addHours(2),
            'created_at' => $this->baseDate->subMinutes($index + 1),
        ]);
    }

    private function user(string $username): ?object
    {
        return DB::table('users')->where('username', $username)->first();
    }

    private function systemBankAccount(): ?string
    {
        if (! Schema::hasTable('system_bank_accounts')) {
            return null;
        }

        $id = DB::table('system_bank_accounts')->where('is_default', true)->where('status', 'active')->value('id');
        if ($id) {
            return $id;
        }

        $id = DB::table('system_bank_accounts')->insertGetId([
            'name' => 'Tài khoản nhận tiền SportGo',
            'bank_name' => 'TPBank',
            'bank_code' => 'TPBank',
            'account_number' => '72906999999',
            'account_holder_name' => 'NGUYEN VAN KIEN',
            'status' => 'active',
            'is_default' => true,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        return $id;
    }

    private function ownerBankAccount(string $ownerId): string
    {
        $id = DB::table('owner_bank_accounts')->where('owner_id', $ownerId)->where('status', 'active')->value('id');
        if ($id) {
            return $id;
        }

        $owner = DB::table('users')->where('id', $ownerId)->first();
        $isSunOwner = $owner?->username === 'owner_sun';

        if ($isSunOwner) {
            // owner_sun đã có một tài khoản TPBank đang chờ duyệt từ seeder hồ sơ.
            // Tạo thêm tài khoản active để có thể kiểm tra luồng doanh thu/rút tiền.
            DB::table('owner_bank_accounts')
                ->where('owner_id', $ownerId)
                ->where('status', 'pending')
                ->update(['is_default' => false, 'updated_at' => $this->baseDate]);
        }

        $id = DB::table('owner_bank_accounts')->insertGetId([
            'owner_id' => $ownerId,
            'partner_application_id' => null,
            'bank_name' => $isSunOwner ? 'TPBank' : 'Techcombank',
            'bank_code' => $isSunOwner ? 'TPB' : 'TCB',
            'account_number' => $isSunOwner ? '0987654322' : '29206999999999',
            'account_holder_name' => $isSunOwner ? 'LE HOANG ANH' : 'NGUYEN MINH QUAN',
            'branch_name' => 'Hà Nội',
            'status' => 'active',
            'is_default' => true,
            'verified_by' => $this->user('admin')?->id,
            'verified_at' => $this->baseDate,
            'rejected_reason' => null,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        return $id;
    }

    private function ownerWallet(string $ownerId, string $clusterId): string
    {
        $id = DB::table('owner_wallets')->insertGetId([
            'owner_id' => $ownerId,
            'venue_cluster_id' => $clusterId,
            'available_balance' => 0,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        $this->ownerWalletBalances[$id] = 0;
        $this->ownerWalletPending[$id] = 0;
        $this->ownerWalletEarned[$id] = 0;
        $this->ownerWalletWithdrawn[$id] = 0;

        return $id;
    }

    private function userWallet(string $userId, float $openingBalance): string
    {
        $id = DB::table('user_wallets')->insertGetId([
            'user_id' => $userId,
            'balance' => $openingBalance,
            'locked_balance' => 0,
            'status' => 'active',
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        $this->userWalletBalances[$id] = $openingBalance;

        if ($openingBalance > 0) {
            $this->userLedger($id, 'deposit', 'credit', $openingBalance, 'seed', $userId, 'Số dư ví khởi tạo cho scenario.');
        }

        return $id;
    }

    private function userPayoutAccount(object $user): void
    {
        DB::table('user_payout_accounts')->insert([
            'user_id' => $user->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => strtoupper(Str::ascii($user->full_name ?? 'SPORTGO USER')),
            'bank_branch' => 'Hà Nội',
            'is_default' => true,
            'status' => 'active',
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);
    }

    private function receipt(string $type, string $receiptableType, string $receiptableId, ?string $issuedTo, ?string $issuedBy, string $title, float $amount, int $index): void
    {
        if (! Schema::hasTable('internal_receipts')) {
            return;
        }

        DB::table('internal_receipts')->insert([
            'receipt_code' => 'RECEIPT_'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'receipt_type' => $type,
            'receiptable_type' => $receiptableType,
            'receiptable_id' => $receiptableId,
            'issued_to_user_id' => $issuedTo,
            'issued_by' => $issuedBy,
            'title' => $title,
            'amount' => $amount,
            'currency' => 'VND',
            'status' => 'issued',
            'issued_at' => $this->baseDate->subMinutes($index + 1),
            'cancelled_at' => null,
            'cancel_reason' => null,
            'file_path' => null,
            'metadata' => json_encode(['seed' => true], JSON_UNESCAPED_UNICODE),
            'created_at' => $this->baseDate->subMinutes($index + 1),
            'updated_at' => $this->baseDate->subMinutes($index + 1),
        ]);
    }

    private function syncWalletSnapshots(): void
    {
        foreach ($this->ownerWalletBalances as $walletId => $balance) {
            DB::table('owner_wallets')->where('id', $walletId)->update([
                'available_balance' => max(0, $balance),
                'pending_withdrawal_balance' => $this->ownerWalletPending[$walletId] ?? 0,
                'total_earned' => $this->ownerWalletEarned[$walletId] ?? 0,
                'total_withdrawn' => $this->ownerWalletWithdrawn[$walletId] ?? 0,
                'updated_at' => $this->baseDate,
            ]);
        }

        foreach ($this->userWalletBalances as $walletId => $balance) {
            DB::table('user_wallets')->where('id', $walletId)->update([
                'balance' => max(0, $balance),
                'updated_at' => $this->baseDate,
            ]);
        }
    }

    private function minutes(string $start, string $end): int
    {
        return CarbonImmutable::parse('2026-01-01 '.$start)->diffInMinutes(CarbonImmutable::parse('2026-01-01 '.$end));
    }

    private function hourlyPrice(float $subtotal, string $start, string $end): float
    {
        $minutes = max(1, $this->minutes($start, $end));

        return round($subtotal / ($minutes / 60), 2);
    }

}
