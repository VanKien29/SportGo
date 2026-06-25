<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function run(): void
    {
        if (! $this->hasRequiredTables()) {
            return;
        }

        $this->baseDate = CarbonImmutable::parse('2026-06-20 09:00:00', config('app.timezone'));

        $this->clearScenarioData();

        $admin = $this->user('superadmin');
        $owner = $this->user('owner');
        $staff = $this->user('venuestaff');
        $customers = $this->customerUsers();
        $walkInUsers = $this->walkInUsers();

        $cluster = DB::table('venue_clusters')->where('slug', 'sportgo-cau-giay')->first()
            ?: DB::table('venue_clusters')->orderBy('created_at')->first();

        if (! $cluster) {
            return;
        }

        $courts = DB::table('venue_courts')
            ->where('venue_cluster_id', $cluster->id)
            ->orderBy('sort_order')
            ->get();

        if ($courts->isEmpty()) {
            return;
        }

        $systemBankId = $this->systemBankAccount();
        $ownerBankId = $this->ownerBankAccount($owner->id);
        $ownerWalletId = $this->ownerWallet($owner->id, $cluster->id);

        foreach ([...$customers, ...$walkInUsers] as $user) {
            $walletId = $this->userWallet($user->id, 50000);
            $this->userPayoutAccount($user, $walletId);
        }

        $this->seedSingleBookings(
            cluster: $cluster,
            courts: $courts->values()->all(),
            ownerId: $owner->id,
            staffId: $staff->id,
            customers: $customers,
            walkInUsers: $walkInUsers,
            ownerWalletId: $ownerWalletId,
            systemBankId: $systemBankId,
        );

        $this->seedRecurringBookings(
            cluster: $cluster,
            courts: $courts->values()->all(),
            ownerId: $owner->id,
            staffId: $staff->id,
            customers: $customers,
            ownerWalletId: $ownerWalletId,
            systemBankId: $systemBankId,
        );

        $this->seedManualLocks($cluster, $courts->values()->all(), $owner->id);
        $this->seedOwnerWithdrawals($owner->id, $ownerWalletId, $ownerBankId, $admin->id);
        $this->seedUserWithdrawals($customers, $admin->id);

        $this->syncWalletSnapshots();
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
        Schema::disableForeignKeyConstraints();

        foreach ([
            'notifications',
            'internal_receipts',
            'refund_status_histories',
            'payment_logs',
            'voucher_usages',
            'reviews',
            'complaints',
            'player_post_participants',
            'player_posts',
            'owner_wallet_ledgers',
            'owner_withdrawal_requests',
            'user_withdrawal_requests',
            'user_wallet_ledgers',
            'refunds',
            'payments',
            'slot_locks',
            'booking_items',
            'bookings',
            'user_payout_accounts',
            'user_wallets',
            'owner_wallets',
            'venue_unlock_requests',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->ownerWalletBalances = [];
        $this->ownerWalletPending = [];
        $this->ownerWalletEarned = [];
        $this->ownerWalletWithdrawn = [];
        $this->userWalletBalances = [];
    }

    private function seedSingleBookings(object $cluster, array $courts, string $ownerId, string $staffId, array $customers, array $walkInUsers, string $ownerWalletId, ?string $systemBankId): void
    {
        $scenarios = [
            ['online full paid', 1, 6, '08:00:00', '09:00:00', 120000, 'full_payment', 'online', 'confirmed', $customers[0], 'sepay', 'paid'],
            ['online deposit pending', 1, 6, '09:30:00', '10:30:00', 120000, 'deposit', 'online', 'pending_payment', $customers[1], 'sepay', 'pending'],
            ['online full failed', 1, 6, '11:00:00', '12:00:00', 120000, 'full_payment', 'online', 'pending_payment', $customers[2], 'sepay', 'failed'],
            ['counter cash paid', 2, 0, '08:00:00', '09:00:00', 120000, 'full_payment', 'counter', 'confirmed', $walkInUsers[0], 'cash', 'paid'],
            ['counter transfer waiting', 2, 0, '10:00:00', '11:00:00', 120000, 'full_payment', 'counter', 'pending_payment', $walkInUsers[1], 'sepay', 'pending'],
            ['counter pay later', 2, 0, '13:00:00', '14:00:00', 120000, 'no_prepay', 'counter', 'confirmed', $walkInUsers[2], null, null],
            ['counter overdue unpaid', -1, 1, '18:00:00', '19:00:00', 120000, 'no_prepay', 'counter', 'expired', $walkInUsers[3], null, null],
            ['online completed', -2, 2, '07:00:00', '08:30:00', 180000, 'full_payment', 'online', 'completed', $customers[3], 'sepay', 'paid'],
            ['online wallet paid', 3, 0, '15:00:00', '16:00:00', 120000, 'full_payment', 'online', 'confirmed', $customers[4], 'wallet', 'paid'],
            ['counter cancelled unpaid', 4, 0, '16:30:00', '17:30:00', 120000, 'no_prepay', 'counter', 'cancelled', $walkInUsers[4], null, null],
        ];

        foreach ($scenarios as $i => [$label, $dayOffset, $courtIndex, $start, $end, $price, $paymentOption, $source, $status, $customer, $method, $paymentStatus]) {
            $requiredAmount = match ($paymentOption) {
                'deposit' => round($price * 0.3),
                'no_prepay' => 0,
                default => $price,
            };

            $booking = $this->booking([
                'booking_code' => 'BKTS'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'venue_court_id' => $courts[$courtIndex % count($courts)]->id,
                'requested_venue_court_id' => $courts[$courtIndex % count($courts)]->id,
                'venue_cluster_id' => $cluster->id,
                'booking_date' => $this->baseDate->addDays($dayOffset)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $this->minutes($start, $end),
                'total_price' => $price,
                'original_amount' => $price,
                'discount_amount' => 0,
                'system_discount_amount' => 0,
                'venue_discount_amount' => 0,
                'final_amount' => $price,
                'payment_option' => $paymentOption,
                'required_payment_amount' => $requiredAmount,
                'source' => $source,
                'booking_type' => 'single',
                'status' => $status,
                'walk_in_name' => str_starts_with($customer->username, 'walkin_') ? $customer->full_name : null,
                'walk_in_phone' => str_starts_with($customer->username, 'walkin_') ? $customer->phone : null,
                'created_by' => $source === 'counter' ? $staffId : $customer->id,
                'status_reason' => $status === 'cancelled' ? 'Khách đổi kế hoạch trước giờ chơi.' : null,
                'cancelled_by' => $status === 'cancelled' ? $staffId : null,
                'cancelled_at' => $status === 'cancelled' ? $this->baseDate->subHours(2) : null,
                'created_at' => $this->baseDate->subDays(2)->addMinutes($i),
                'updated_at' => $this->baseDate->subDays(2)->addMinutes($i),
            ], [[
                'court_id' => $courts[$courtIndex % count($courts)]->id,
                'start' => $start,
                'end' => $end,
                'price' => $price,
                'status' => $status === 'cancelled' ? 'cancelled' : 'active',
            ]]);

            if ($method) {
                $paymentAmount = $paymentOption === 'deposit' ? $requiredAmount : $price;
                $payment = $this->payment($booking, $systemBankId, $paymentAmount, $paymentOption === 'deposit' ? 'deposit' : 'full', $method, $paymentStatus, $i);
                $this->paymentLog($payment['id'], $paymentStatus === 'paid' ? 'payment_paid' : 'payment_attempt', null, $paymentStatus, $payment['gateway_txn_id']);

                if ($paymentStatus === 'paid' && $method === 'sepay') {
                    $this->ownerCredit($ownerWalletId, $ownerId, $cluster->id, $booking['id'], $payment['id'], $paymentAmount);
                }

                if ($paymentStatus === 'paid' && $method === 'wallet') {
                    $this->walletPaymentDebit($customer->id, $booking['id'], $payment['id'], $paymentAmount);
                }
            }
        }

        $this->seedRefundCases($cluster, $courts, $ownerId, $staffId, $customers, $walkInUsers, $ownerWalletId, $systemBankId);
    }

    private function seedRefundCases(object $cluster, array $courts, string $ownerId, string $staffId, array $customers, array $walkInUsers, string $ownerWalletId, ?string $systemBankId): void
    {
        $cases = [
            ['pending owner', 'pending_owner_confirmation', 'user_wallet', 1, 0, 90000, 90000, 'Khách yêu cầu hủy trước giờ chơi, chờ chủ sân xác nhận.'],
            ['pending owner 2', 'pending_owner_confirmation', 'user_wallet', 1, 1, 60000, 60000, 'Khách báo bận đột xuất, chờ chủ sân duyệt hoàn.'],
            ['pending owner maintenance', 'pending_owner_confirmation', 'user_wallet', 2, 0, 120000, 120000, 'Sân có lịch bảo trì, chờ chủ sân xác nhận hoàn 100%.'],
            ['completed wallet', 'completed', 'user_wallet', -1, 1, 100000, 100000, 'Chủ sân đã xác nhận, hệ thống hoàn vào ví khách.'],
            ['completed wallet maintenance', 'completed', 'user_wallet', -1, 2, 150000, 150000, 'Hoàn 100% do chủ sân khóa sân/bảo trì.'],
            ['cash refund', 'completed_cash', 'cash', -1, 3, 50000, 50000, 'Khách tại quầy nhận tiền mặt khi sân gặp sự cố.'],
            ['owner rejected', 'owner_rejected', 'user_wallet', -2, 0, 80000, 0, 'Khách hủy sát giờ, chủ sân từ chối theo chính sách.'],
            ['zero policy', 'cancelled', 'user_wallet', -3, 1, 110000, 0, 'Không phát sinh số tiền hoàn theo chính sách hiện tại.'],
            ['pending small', 'pending_owner_confirmation', 'user_wallet', 3, 2, 30000, 30000, 'Test yêu cầu hoàn số tiền nhỏ.'],
            ['pending cash option', 'pending_owner_confirmation', 'cash', 3, 3, 45000, 45000, 'Khách tại quầy có thể được chủ sân hoàn tiền mặt.'],
            ['completed wallet guest', 'completed', 'user_wallet', -2, 2, 70000, 70000, 'Khách vãng lai đã có ví, hoàn vào ví SportGo.'],
            ['cash incident', 'completed_cash', 'cash', 0, 3, 40000, 40000, 'Hoàn tiền mặt phần thời gian chưa chơi do sự cố đột xuất.'],
        ];

        foreach ($cases as $i => [$label, $refundStatus, $destination, $dayOffset, $courtIndex, $paidAmount, $refundAmount, $reason]) {
            $customer = $i >= 10 ? $walkInUsers[$i % count($walkInUsers)] : $customers[$i % count($customers)];
            $start = ['08:00:00', '09:30:00', '15:00:00', '19:00:00'][$i % 4];
            $end = ['09:00:00', '10:30:00', '16:00:00', '20:00:00'][$i % 4];
            $court = $courts[$courtIndex % count($courts)];

            $booking = $this->booking([
                'booking_code' => 'BKR'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'venue_court_id' => $court->id,
                'requested_venue_court_id' => $court->id,
                'venue_cluster_id' => $cluster->id,
                'booking_date' => $this->baseDate->addDays($dayOffset)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'duration_minutes' => $this->minutes($start, $end),
                'total_price' => $paidAmount,
                'original_amount' => $paidAmount,
                'discount_amount' => 0,
                'final_amount' => $paidAmount,
                'payment_option' => 'full_payment',
                'required_payment_amount' => $paidAmount,
                'source' => str_starts_with($customer->username, 'walkin_') ? 'counter' : 'online',
                'booking_type' => 'single',
                'status' => in_array($refundStatus, ['completed', 'completed_cash', 'cancelled', 'owner_rejected'], true) ? 'cancelled' : 'confirmed',
                'walk_in_name' => str_starts_with($customer->username, 'walkin_') ? $customer->full_name : null,
                'walk_in_phone' => str_starts_with($customer->username, 'walkin_') ? $customer->phone : null,
                'status_reason' => $reason,
                'cancelled_by' => in_array($refundStatus, ['completed', 'completed_cash', 'cancelled', 'owner_rejected'], true) ? $staffId : null,
                'cancelled_at' => in_array($refundStatus, ['completed', 'completed_cash', 'cancelled', 'owner_rejected'], true) ? $this->baseDate->subHours($i + 1) : null,
                'created_by' => str_starts_with($customer->username, 'walkin_') ? $staffId : $customer->id,
                'created_at' => $this->baseDate->subDays(5)->addMinutes($i),
                'updated_at' => $this->baseDate->subDays(5)->addMinutes($i),
            ], [[
                'court_id' => $court->id,
                'start' => $start,
                'end' => $end,
                'price' => $paidAmount,
                'status' => 'cancelled',
                'status_reason' => $reason,
            ]]);

            $payment = $this->payment($booking, $systemBankId, $paidAmount, 'full', 'sepay', 'paid', 100 + $i);
            $this->ownerCredit($ownerWalletId, $ownerId, $cluster->id, $booking['id'], $payment['id'], $paidAmount);

            $this->refund(
                payment: $payment,
                booking: $booking,
                customerId: $customer->id,
                ownerId: $ownerId,
                staffId: $staffId,
                ownerWalletId: $ownerWalletId,
                amount: $refundAmount,
                destination: $destination,
                status: $refundStatus,
                reason: $reason,
                index: $i,
            );
        }
    }

    private function seedRecurringBookings(object $cluster, array $courts, string $ownerId, string $staffId, array $customers, string $ownerWalletId, ?string $systemBankId): void
    {
        $groups = [
            ['RGWAIT01', $customers[0], $courts[0], [3, 4, 5, 10, 11, 12], '07:00:00', '08:30:00', 'full_payment', 'pending_payment', 150000],
            ['RGPAID01', $customers[1], $courts[1], [2, 3, 4, 9, 10, 11], '18:00:00', '19:30:00', 'full_payment', 'confirmed', 150000],
            ['RGCASH01', $customers[2], $courts[2], [6, 13, 20, 27], '09:00:00', '10:00:00', 'no_prepay', 'confirmed', 100000],
        ];

        foreach ($groups as $g => [$code, $customer, $court, $offsets, $start, $end, $paymentOption, $status, $price]) {
            foreach ($offsets as $j => $offset) {
                $date = $this->baseDate->addDays($offset);
                $bookingStatus = $status;
                $itemStatus = 'active';
                $reason = null;

                if ($code === 'RGPAID01' && in_array($j, [2, 3], true)) {
                    $bookingStatus = 'cancelled';
                    $itemStatus = 'cancelled_by_maintenance';
                    $reason = 'Hủy buổi trong chuỗi do khóa sân/bảo trì, hoàn 100% phần đã thanh toán.';
                }

                $booking = $this->booking([
                    'booking_code' => $code.'-'.str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'venue_court_id' => $court->id,
                    'requested_venue_court_id' => $court->id,
                    'venue_cluster_id' => $cluster->id,
                    'booking_date' => $date->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => $this->minutes($start, $end),
                    'total_price' => $price,
                    'original_amount' => $price,
                    'discount_amount' => 0,
                    'final_amount' => $price,
                    'payment_option' => $paymentOption,
                    'required_payment_amount' => $paymentOption === 'no_prepay' ? 0 : $price,
                    'source' => 'counter',
                    'booking_type' => 'recurring',
                    'recurring_group_code' => $code,
                    'recurring_start_date' => $this->baseDate->addDays(min($offsets))->toDateString(),
                    'recurring_end_date' => $this->baseDate->addDays(max($offsets))->toDateString(),
                    'recurrence_type' => 'weekly',
                    'recurrence_interval' => $code === 'RGCASH01' ? 1 : 2,
                    'recurrence_days_of_week' => json_encode([2, 3, 4]),
                    'status' => $bookingStatus,
                    'status_reason' => $reason,
                    'cancelled_by' => $bookingStatus === 'cancelled' ? $staffId : null,
                    'cancelled_at' => $bookingStatus === 'cancelled' ? $this->baseDate->addDay() : null,
                    'created_by' => $staffId,
                    'created_at' => $this->baseDate->subDays(1)->addMinutes($g * 10 + $j),
                    'updated_at' => $this->baseDate->subDays(1)->addMinutes($g * 10 + $j),
                ], [[
                    'court_id' => $court->id,
                    'start' => $start,
                    'end' => $end,
                    'price' => $price,
                    'status' => $itemStatus,
                    'status_reason' => $reason,
                ]]);

                if ($paymentOption !== 'no_prepay') {
                    $paymentStatus = $status === 'pending_payment' ? 'pending' : 'paid';
                    $payment = $this->payment($booking, $systemBankId, $price, 'full', 'sepay', $paymentStatus, 200 + $g * 10 + $j);

                    if ($paymentStatus === 'paid') {
                        $this->ownerCredit($ownerWalletId, $ownerId, $cluster->id, $booking['id'], $payment['id'], $price);
                    }

                    if ($itemStatus === 'cancelled_by_maintenance') {
                        $this->refund(
                            payment: $payment,
                            booking: $booking,
                            customerId: $customer->id,
                            ownerId: $ownerId,
                            staffId: $staffId,
                            ownerWalletId: $ownerWalletId,
                            amount: $price,
                            destination: 'user_wallet',
                            status: 'pending_owner_confirmation',
                            reason: $reason,
                            index: 200 + $j,
                        );
                    }
                }
            }
        }
    }

    private function seedManualLocks(object $cluster, array $courts, string $ownerId): void
    {
        $locks = [
            [1, 0, '06:00:00', '08:00:00', 'Bảo trì mặt sân buổi sáng.'],
            [1, 1, '14:00:00', '18:00:00', 'Sơn lại vạch sân.'],
            [2, 2, '18:00:00', '22:00:00', 'Sự kiện nội bộ của cụm sân.'],
            [3, 3, '06:00:00', '22:00:00', 'Khóa cả ngày để kiểm tra hệ thống đèn.'],
            [7, 0, '09:00:00', '11:00:00', 'Khóa test nhiều ngày 1.'],
            [8, 0, '09:00:00', '11:00:00', 'Khóa test nhiều ngày 2.'],
            [9, 0, '09:00:00', '11:00:00', 'Khóa test nhiều ngày 3.'],
        ];

        foreach ($locks as $i => [$dayOffset, $courtIndex, $start, $end, $reason]) {
            $this->slotLock(
                clusterId: $cluster->id,
                courtId: $courts[$courtIndex % count($courts)]->id,
                bookingDate: $this->baseDate->addDays($dayOffset)->toDateString(),
                start: $start,
                end: $end,
                lockedBy: $ownerId,
                bookingId: null,
                bookingItemId: null,
                lockType: 'manual',
                reason: $reason,
                index: 500 + $i,
            );
        }
    }

    private function seedOwnerWithdrawals(string $ownerId, string $ownerWalletId, string $ownerBankId, string $adminId): void
    {
        $statuses = [
            ...array_fill(0, 12, 'pending'),
            'completed',
            'completed',
            'cancelled',
            'rejected',
        ];

        foreach ($statuses as $i => $status) {
            $amount = 1000 + ($i % 4) * 500;
            $id = $this->uuid();
            $now = $this->baseDate->subHours($i + 1);
            DB::table('owner_withdrawal_requests')->insert([
                'id' => $id,
                'request_code' => 'WRTEST'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'source' => 'manual',
                'auto_created' => false,
                'owner_id' => $ownerId,
                'owner_wallet_id' => $ownerWalletId,
                'owner_bank_account_id' => $ownerBankId,
                'amount' => $amount,
                'status' => $status,
                'owner_note' => $status === 'pending' ? 'Yêu cầu rút tiền test đang chờ admin chuyển khoản.' : 'Yêu cầu rút tiền test lịch sử.',
                'reviewed_by' => in_array($status, ['completed', 'rejected'], true) ? $adminId : null,
                'reviewed_at' => in_array($status, ['completed', 'rejected'], true) ? $now : null,
                'review_note' => $status === 'rejected' ? 'Tài khoản nhận tiền chưa hợp lệ.' : null,
                'status_reason' => $status === 'cancelled' ? 'Chủ sân tự hủy yêu cầu rút tiền.' : null,
                'completed_by' => $status === 'completed' ? $adminId : null,
                'completed_at' => $status === 'completed' ? $now->addMinutes(15) : null,
                'transfer_reference' => $status === 'completed' ? 'MB-TEST-'.$i : null,
                'metadata' => json_encode(['seed' => true, 'scenario' => 'owner_withdrawal']),
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($status === 'pending') {
                $this->ownerLedger($ownerWalletId, $ownerId, null, null, null, 'hold', 'debit', $amount, 'withdrawal', $id, 'Giữ tiền cho yêu cầu rút test '.$id.'.');
            }

            if ($status === 'completed') {
                $this->ownerWalletWithdrawn[$ownerWalletId] = ($this->ownerWalletWithdrawn[$ownerWalletId] ?? 0) + $amount;
                $this->receipt('withdrawal', 'owner_withdrawal_requests', $id, $ownerId, $adminId, 'Phiếu chi rút tiền chủ sân', $amount, $i);
            }
        }
    }

    private function seedUserWithdrawals(array $customers, string $adminId): void
    {
        $statuses = [
            ...array_fill(0, 10, 'pending'),
            'approved',
            'paid',
            'paid',
            'rejected',
            'cancelled',
        ];

        foreach ($statuses as $i => $status) {
            $user = $customers[$i % count($customers)];
            $walletId = DB::table('user_wallets')->where('user_id', $user->id)->value('id');
            $payoutId = DB::table('user_payout_accounts')->where('user_id', $user->id)->value('id');

            if (! $walletId || ! $payoutId) {
                continue;
            }

            $amount = 1000 + ($i % 5) * 500;
            $now = $this->baseDate->subHours($i + 2);
            DB::table('user_withdrawal_requests')->insert([
                'id' => $this->uuid(),
                'user_wallet_id' => $walletId,
                'user_id' => $user->id,
                'payout_account_id' => $payoutId,
                'amount' => $amount,
                'status' => $status,
                'rejected_reason' => $status === 'rejected' ? 'Thông tin tài khoản nhận tiền chưa chính xác.' : null,
                'approved_by' => in_array($status, ['approved', 'paid'], true) ? $adminId : null,
                'paid_by' => $status === 'paid' ? $adminId : null,
                'requested_at' => $now,
                'approved_at' => in_array($status, ['approved', 'paid'], true) ? $now->addMinutes(20) : null,
                'paid_at' => $status === 'paid' ? $now->addHour() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function booking(array $attrs, array $items): array
    {
        $id = $this->uuid();
        $now = $attrs['created_at'] ?? $this->baseDate;
        $row = array_merge([
            'id' => $id,
            'discount_amount' => 0,
            'system_discount_amount' => 0,
            'venue_discount_amount' => 0,
            'voucher_id' => null,
            'voucher_code_snapshot' => null,
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

        DB::table('bookings')->insert($row);

        foreach ($items as $sort => $item) {
            $itemId = $this->uuid();
            DB::table('booking_items')->insert([
                'id' => $itemId,
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
                'cancelled_by' => ($item['status'] ?? 'active') === 'cancelled' ? ($row['cancelled_by'] ?? null) : null,
                'cancelled_at' => ($item['status'] ?? 'active') === 'cancelled' ? ($row['cancelled_at'] ?? null) : null,
                'maintenance_lock_id' => null,
                'court_changed_by' => null,
                'court_changed_at' => null,
                'court_changed_reason' => null,
                'sort_order' => $sort + 1,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);

            if (! in_array($row['status'], ['cancelled', 'expired', 'rejected'], true) && ($item['status'] ?? 'active') === 'active') {
                $this->slotLock($row['venue_cluster_id'], $item['court_id'], $row['booking_date'], $item['start'], $item['end'], 'booking-'.$row['booking_code'], $id, $itemId, 'auto', 'Giữ chỗ cho booking '.$row['booking_code'], $sort);
            }
        }

        return $row;
    }

    private function payment(array $booking, ?string $systemBankId, float $amount, string $kind, string $method, string $status, int $index): array
    {
        $id = $this->uuid();
        $txn = $status === 'paid' ? 'SEPAYTEST'.str_pad((string) $index, 8, '0', STR_PAD_LEFT) : null;
        $row = [
            'id' => $id,
            'payment_code' => 'PMTEST'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
            'booking_id' => $booking['id'],
            'system_bank_account_id' => $systemBankId,
            'user_wallet_id' => null,
            'user_wallet_ledger_id' => null,
            'amount' => $amount,
            'wallet_amount' => $method === 'wallet' ? $amount : 0,
            'gateway_amount' => $method === 'sepay' ? $amount : 0,
            'payment_kind' => $kind,
            'method' => $method,
            'gateway_txn_id' => $txn,
            'gateway_response' => json_encode(['seed' => true, 'status' => $status, 'method' => $method]),
            'status' => $status,
            'paid_at' => $status === 'paid' ? $this->baseDate->subHours($index % 18) : null,
            'created_at' => $booking['created_at'],
            'updated_at' => $booking['created_at'],
        ];

        DB::table('payments')->insert($row);

        return $row;
    }

    private function refund(array $payment, array $booking, string $customerId, string $ownerId, string $staffId, string $ownerWalletId, float $amount, string $destination, string $status, string $reason, int $index): void
    {
        $id = $this->uuid();
        $userWalletId = DB::table('user_wallets')->where('user_id', $customerId)->value('id');
        $userLedgerId = null;
        $ownerDebitLedgerId = null;
        $completedAt = null;

        if ($status === 'completed' && $amount > 0 && $userWalletId) {
            $userLedgerId = $this->userLedger($userWalletId, 'refund', 'credit', $amount, 'refund', $id, 'Hoàn tiền booking '.$booking['booking_code'].' vào ví SportGo.');
            $ownerDebitLedgerId = $this->ownerLedger($ownerWalletId, $ownerId, $booking['venue_cluster_id'], $booking['id'], $payment['id'], 'debit', 'debit', $amount, 'refund', $id, 'Giảm doanh thu do hoàn tiền booking '.$booking['booking_code'].'.');
            $completedAt = $this->baseDate->subMinutes($index + 10);
            $this->receipt('refund', 'refunds', $id, $customerId, $staffId, 'Phiếu hoàn ví khách hàng', $amount, $index);
        }

        if ($status === 'completed_cash' && $amount > 0) {
            $ownerDebitLedgerId = $this->ownerLedger($ownerWalletId, $ownerId, $booking['venue_cluster_id'], $booking['id'], $payment['id'], 'debit', 'debit', $amount, 'refund', $id, 'Chủ sân hoàn tiền mặt booking '.$booking['booking_code'].'.');
            $completedAt = $this->baseDate->subMinutes($index + 10);
        }

        DB::table('refunds')->insert([
            'id' => $id,
            'payment_id' => $payment['id'],
            'booking_id' => $booking['id'],
            'customer_id' => $customerId,
            'amount' => $amount,
            'refund_destination' => $destination,
            'user_wallet_id' => $destination === 'user_wallet' ? $userWalletId : null,
            'user_wallet_ledger_id' => $userLedgerId,
            'user_payout_account_id' => null,
            'owner_wallet_ledger_id' => $ownerDebitLedgerId,
            'policy_id' => null,
            'policy_rule_id' => null,
            'policy_evaluation_log_id' => null,
            'reason' => $reason,
            'status' => $status,
            'status_reason' => $status === 'cancelled' ? 'Không phát sinh số tiền hoàn theo chính sách hiện tại.' : ($status === 'owner_rejected' ? 'Chủ sân từ chối yêu cầu hoàn tiền.' : null),
            'owner_confirmed_by' => in_array($status, ['completed', 'completed_cash', 'owner_rejected', 'cancelled'], true) ? $staffId : null,
            'owner_confirmed_at' => in_array($status, ['completed', 'completed_cash', 'owner_rejected', 'cancelled'], true) ? $completedAt ?? $this->baseDate->subHour() : null,
            'owner_confirm_note' => in_array($status, ['completed', 'completed_cash'], true) ? 'Chủ sân xác nhận xử lý theo luồng mới.' : null,
            'processed_by' => null,
            'processed_at' => null,
            'admin_confirmed_by' => null,
            'admin_confirmed_at' => null,
            'completed_at' => $completedAt,
            'cash_refunded_by' => $status === 'completed_cash' ? $staffId : null,
            'cash_refunded_at' => $status === 'completed_cash' ? $completedAt : null,
            'cash_refund_note' => $status === 'completed_cash' ? 'Đã hoàn tiền mặt trực tiếp tại sân.' : null,
            'gateway_refund_txn_id' => null,
            'payout_transfer_code' => null,
            'payout_qr_created_at' => null,
            'created_at' => $this->baseDate->subDays(1)->addMinutes($index),
            'updated_at' => $this->baseDate->subDays(1)->addMinutes($index),
        ]);

        $this->refundHistory($id, null, $status, $staffId, $reason, $index);
    }

    private function ownerCredit(string $walletId, string $ownerId, string $clusterId, string $bookingId, string $paymentId, float $amount): void
    {
        $this->ownerLedger($walletId, $ownerId, $clusterId, $bookingId, $paymentId, 'credit', 'credit', $amount, 'payment', $paymentId, 'Hệ thống thu hộ booking và cộng vào ví chủ sân.');
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

        $id = $this->uuid();
        DB::table('owner_wallet_ledgers')->insert([
            'id' => $id,
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
            'reference_code' => strtoupper(substr($referenceType, 0, 3)).'-'.substr(hash('sha256', $referenceId.$id), 0, 12),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_code' => 'OWL-'.substr(hash('sha256', $id.$referenceId), 0, 32),
            'description' => $description,
            'note' => $description,
            'metadata' => json_encode(['seed' => true]),
            'created_at' => $this->baseDate->subMinutes(rand(1, 300)),
            'updated_at' => $this->baseDate->subMinutes(rand(1, 300)),
        ]);

        return $id;
    }

    private function userLedger(string $walletId, string $type, string $direction, float $amount, string $referenceType, string $referenceId, string $note): string
    {
        $before = $this->userWalletBalances[$walletId] ?? 0;
        $after = $direction === 'credit' ? $before + $amount : $before - $amount;
        $this->userWalletBalances[$walletId] = $after;

        $id = $this->uuid();
        DB::table('user_wallet_ledgers')->insert([
            'id' => $id,
            'user_wallet_id' => $walletId,
            'transaction_code' => 'UWL-'.substr(hash('sha256', $id.$referenceId), 0, 32),
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
            'created_at' => $this->baseDate->subMinutes(rand(1, 300)),
            'updated_at' => $this->baseDate->subMinutes(rand(1, 300)),
        ]);

        return $id;
    }

    private function walletPaymentDebit(string $userId, string $bookingId, string $paymentId, float $amount): void
    {
        $walletId = DB::table('user_wallets')->where('user_id', $userId)->value('id');

        if (! $walletId) {
            return;
        }

        $this->userLedger($walletId, 'payment', 'debit', $amount, 'payment', $paymentId, 'Thanh toán booking '.$bookingId.' bằng ví SportGo.');
    }

    private function paymentLog(string $paymentId, string $event, ?string $before, string $after, ?string $gatewayTxnId): void
    {
        DB::table('payment_logs')->insert([
            'id' => $this->uuid(),
            'payment_id' => $paymentId,
            'event_type' => $event,
            'request_payload' => json_encode(['seed' => true]),
            'response_payload' => json_encode(['status' => $after]),
            'status_before' => $before,
            'status_after' => $after,
            'gateway_txn_id' => $gatewayTxnId,
            'error_code' => $after === 'failed' ? 'SEPAY_TEST_FAILED' : null,
            'error_message' => $after === 'failed' ? 'Giao dịch test thất bại.' : null,
            'created_at' => $this->baseDate->subMinutes(rand(1, 300)),
        ]);
    }

    private function refundHistory(string $refundId, ?string $oldStatus, string $newStatus, string $actorId, string $reason, int $index): void
    {
        if (! Schema::hasTable('refund_status_histories')) {
            return;
        }

        DB::table('refund_status_histories')->insert([
            'refund_id' => $refundId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actorId,
            'actor_type' => 'owner',
            'reason' => $reason,
            'metadata' => json_encode(['seed' => true]),
            'created_at' => $this->baseDate->subMinutes($index + 1),
        ]);
    }

    private function slotLock(string $clusterId, ?string $courtId, string $bookingDate, string $start, string $end, string $lockedBy, ?string $bookingId, ?string $bookingItemId, string $lockType, string $reason, int $index): string
    {
        $id = $this->uuid();
        DB::table('slot_locks')->insert([
            'id' => $id,
            'venue_cluster_id' => $clusterId,
            'venue_court_id' => $courtId,
            'lock_scope' => $courtId ? 'court' : 'cluster',
            'booking_date' => $bookingDate,
            'start_time' => $start,
            'end_time' => $end,
            'locked_by' => $lockedBy,
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'lock_type' => $lockType,
            'reason' => $reason,
            'expires_at' => $lockType === 'auto'
                ? CarbonImmutable::parse($bookingDate.' '.$end, config('app.timezone'))->addHours(2)
                : CarbonImmutable::parse($bookingDate.' '.$end, config('app.timezone'))->addDays(30),
            'created_at' => $this->baseDate->subMinutes($index + 1),
        ]);

        return $id;
    }

    private function user(string $username): object
    {
        return DB::table('users')->where('username', $username)->first()
            ?: DB::table('users')->orderBy('created_at')->first();
    }

    private function customerUsers(): array
    {
        $users = DB::table('users')
            ->whereIn('username', ['user', 'user1', 'user2', 'user3', 'user4'])
            ->orderBy('username')
            ->get()
            ->values()
            ->all();

        while (count($users) < 5) {
            $users[] = $this->createScenarioUser('customer_seed_'.(count($users) + 1), 'Khách online '.(count($users) + 1), '09120000'.count($users));
        }

        return $users;
    }

    private function walkInUsers(): array
    {
        $names = [
            ['walkin_nguyen_van_a', 'Nguyễn Văn A', '0901234567'],
            ['walkin_tran_thi_b', 'Trần Thị B', '0901234568'],
            ['walkin_le_van_c', 'Lê Văn C', '0901234569'],
            ['walkin_pham_thi_d', 'Phạm Thị D', '0901234570'],
            ['walkin_hoang_van_e', 'Hoàng Văn E', '0901234571'],
        ];

        return array_map(fn (array $user) => $this->createScenarioUser($user[0], $user[1], $user[2]), $names);
    }

    private function createScenarioUser(string $username, string $name, string $phone): object
    {
        $existing = DB::table('users')->where('username', $username)->first();

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update([
                'full_name' => $name,
                'phone' => $phone,
                'status' => 'active',
                'updated_at' => $this->baseDate,
            ]);

            return DB::table('users')->where('id', $existing->id)->first();
        }

        $id = $this->uuid();
        DB::table('users')->insert([
            'id' => $id,
            'username' => $username,
            'full_name' => $name,
            'email' => $username.'@example.test',
            'phone' => $phone,
            'password' => Hash::make('12345678'),
            'status' => 'active',
            'verification_channel' => 'sms',
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        return DB::table('users')->where('id', $id)->first();
    }

    private function systemBankAccount(): ?string
    {
        $id = DB::table('system_bank_accounts')->where('is_default', true)->where('status', 'active')->value('id');

        if ($id) {
            return $id;
        }

        if (! Schema::hasTable('system_bank_accounts')) {
            return null;
        }

        $id = $this->uuid();
        DB::table('system_bank_accounts')->insert([
            'id' => $id,
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

        $id = $this->uuid();
        DB::table('owner_bank_accounts')->insert([
            'id' => $id,
            'owner_id' => $ownerId,
            'partner_application_id' => null,
            'bank_name' => 'Techcombank',
            'bank_code' => 'TCB',
            'account_number' => '29206999999999',
            'account_holder_name' => 'CHU SAN SPORTGO',
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
        $id = $this->uuid();
        DB::table('owner_wallets')->insert([
            'id' => $id,
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
        $id = $this->uuid();
        DB::table('user_wallets')->insert([
            'id' => $id,
            'user_id' => $userId,
            'balance' => $openingBalance,
            'locked_balance' => 0,
            'status' => 'active',
            'created_at' => $this->baseDate,
            'updated_at' => $this->baseDate,
        ]);

        $this->userWalletBalances[$id] = $openingBalance;
        $this->userLedger($id, 'deposit', 'credit', $openingBalance, 'seed', $userId, 'Số dư ví test ban đầu.');

        return $id;
    }

    private function userPayoutAccount(object $user, string $walletId): void
    {
        DB::table('user_payout_accounts')->insert([
            'id' => $this->uuid(),
            'user_id' => $user->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => strtoupper(Str::ascii($user->full_name ?? 'NGUYEN VAN A')),
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
            'id' => $this->uuid(),
            'receipt_code' => strtoupper(substr($type, 0, 2)).'-TEST-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
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
            'metadata' => json_encode(['seed' => true]),
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

    private function uuid(): string
    {
        return Str::uuid()->toString();
    }
}
