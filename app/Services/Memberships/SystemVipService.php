<?php

namespace App\Services\Memberships;

use App\Models\Booking;
use App\Models\MembershipPackage;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SystemVipService
{
    private const BILLING_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'yearly' => 12,
    ];

    public function packagesPayload(): array
    {
        return MembershipPackage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn(MembershipPackage $package): array => $this->packagePayload($package))
            ->values()
            ->all();
    }

    public function packagePayload(MembershipPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'label' => $this->packageLabel($package->type, $package->name),
            'monthly_price' => $package->monthly_price !== null ? (float) $package->monthly_price : null,
            'quarterly_price' => $package->quarterly_price !== null ? (float) $package->quarterly_price : null,
            'yearly_price' => $package->yearly_price !== null ? (float) $package->yearly_price : null,
            'voucher_count_per_month' => (int) $package->voucher_count_per_month,
            'voucher_discount_percent' => (float) $package->voucher_discount_percent,
            'voucher_min_order_amount' => (float) $package->voucher_min_order_amount,
            'voucher_max_discount_amount' => $package->voucher_max_discount_amount !== null ? (float) $package->voucher_max_discount_amount : null,
            'cashback_percent' => (float) $package->cashback_percent,
            'match_post_limit_per_month' => (int) $package->match_post_limit_per_month,
            'priority_complaint' => (bool) $package->priority_complaint,
            'badge_name' => $package->badge_name ?: null,
            'badge' => $this->badgePayload($package),
            'is_active' => (bool) $package->is_active,
            'sort_order' => (int) $package->sort_order,
            'available_cycles' => $this->availableCycles($package),
        ];
    }

    public function currentSubscriptionPayload(User $user): ?array
    {
        $subscription = $this->activeSubscriptionForUser($user->id);

        return $subscription ? $this->subscriptionPayload($subscription) : null;
    }

    public function activeSubscriptionForUser(string $userId): ?UserSubscription
    {
        return UserSubscription::query()
            ->with('membershipPackage')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    public function subscribe(User $user, MembershipPackage $package, string $billingCycle): array
    {
        if ($package->type === 'free' || !$package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói VIP này không thể mua.',
            ]);
        }

        if (!array_key_exists($billingCycle, self::BILLING_MONTHS)) {
            throw ValidationException::withMessages([
                'billing_cycle' => 'Chu kỳ mua gói VIP không hợp lệ.',
            ]);
        }

        $price = $this->priceForCycle($package, $billingCycle);
        if ($price === null) {
            throw ValidationException::withMessages([
                'billing_cycle' => 'Gói nay chưa mở bán theo chu kỳ đã chọn.',
            ]);
        }

        return DB::transaction(function () use ($user, $package, $billingCycle, $price): array {
            $activeSubscriptions = UserSubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending_payment', 'active'])
                ->lockForUpdate()
                ->get();

            $currentSubscription = $activeSubscriptions
                ->first(fn (UserSubscription $subscription): bool => $subscription->status === 'active' && $subscription->expires_at->isFuture());

            if ($currentSubscription) {
                throw ValidationException::withMessages([
                    'package_id' => 'Bạn đang có gói VIP còn hiệu lực. Không thể mua thêm hoặc đổi sang gói khác cho đến khi gói hiện tại hết hạn.',
                ]);
            }

            $activeSubscriptions
                ->each(function (UserSubscription $subscription): void {
                    $subscription->update([
                        'status' => $subscription->status === 'pending_payment' ? 'cancelled' : 'expired',
                    ]);
                    $this->failPendingSubscriptionPayments($subscription->id);
                    $this->invalidateSubscriptionVouchers($subscription->id);
                });

            $account = $this->resolveSystemBankAccount();

            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'billing_cycle' => $billingCycle,
                'started_at' => now(),
                'expires_at' => now()->copy()->addMonthsNoOverflow(self::BILLING_MONTHS[$billingCycle]),
                'status' => 'pending_payment',
                'paid_amount' => 0,
                'payment_ref' => null,
                'month_post_count' => 0,
                'month_post_reset_at' => now()->copy()->startOfMonth(),
            ]);

            $payment = Payment::query()->create([
                'payment_code' => $this->uniquePaymentCode(),
                'payment_context' => 'vip_subscription',
                'booking_id' => null,
                'subscription_id' => $subscription->id,
                'system_bank_account_id' => $account->id,
                'amount' => $price,
                'wallet_amount' => 0,
                'gateway_amount' => $price,
                'payment_kind' => 'full',
                'method' => 'sepay',
                'status' => 'pending',
            ]);

            PaymentLog::query()->create([
                'payment_id' => $payment->id,
                'event_type' => 'vip_subscription_payment_created',
                'request_payload' => [
                    'subscription_id' => $subscription->id,
                    'package_id' => $package->id,
                    'billing_cycle' => $billingCycle,
                    'system_bank_account_id' => $account->id,
                    'transfer_content' => $payment->payment_code,
                    'qr_url' => $this->qrUrl($payment, $account),
                ],
                'status_before' => $payment->status,
                'status_after' => $payment->status,
            ]);

            return [
                'subscription' => $subscription->fresh('membershipPackage'),
                'payment' => $payment->fresh(['subscription.membershipPackage', 'systemBankAccount']),
                'payment_account' => $account,
                'system_bank_account' => $account,
                'transfer_content' => $payment->payment_code,
                'qr_url' => $this->qrUrl($payment, $account),
            ];
        });
    }

    public function activateSubscriptionFromPayment(Payment $payment): ?UserSubscription
    {
        if (($payment->payment_context ?? 'booking') !== 'vip_subscription' || ! $payment->subscription_id) {
            return null;
        }

        return DB::transaction(function () use ($payment): ?UserSubscription {
            $subscription = UserSubscription::query()
                ->with('membershipPackage')
                ->whereKey($payment->subscription_id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return null;
            }

            if ($subscription->status === 'active' && $subscription->expires_at->isFuture()) {
                return $subscription;
            }

            if ($subscription->status !== 'pending_payment') {
                return null;
            }

            $subscription->update([
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->copy()->addMonthsNoOverflow(self::BILLING_MONTHS[$subscription->billing_cycle] ?? 1),
                'paid_amount' => $payment->amount,
                'payment_ref' => $payment->payment_code,
                'month_post_count' => 0,
                'month_post_reset_at' => now()->copy()->startOfMonth(),
            ]);

            $subscription = $subscription->fresh('membershipPackage');
            $this->issueMonthlyVouchersForSubscription($subscription);

            return $subscription;
        });
    }

    public function issueMonthlyVouchers(?Carbon $month = null): int
    {
        $month = ($month ?: now())->copy()->startOfMonth();
        $issued = 0;

        UserSubscription::query()
            ->with('membershipPackage')
            ->where('status', 'active')
            ->where('expires_at', '>', $month)
            ->chunkById(100, function ($subscriptions) use ($month, &$issued): void {
                foreach ($subscriptions as $subscription) {
                    $issued += $this->issueMonthlyVouchersForSubscription($subscription, $month);
                }
            });

        return $issued;
    }

    public function expireSubscriptions(): int
    {
        $expired = 0;

        UserSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use (&$expired): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, &$expired): void {
                        $locked = UserSubscription::query()
                            ->whereKey($subscription->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$locked || $locked->status !== 'active' || $locked->expires_at->isFuture()) {
                            return;
                        }

                        $locked->update(['status' => 'expired']);
                        $this->invalidateSubscriptionVouchers($locked->id);
                        $expired++;
                    });
                }
            });

        return $expired;
    }

    public function creditCashbackForCompletedBooking(Booking $booking): float
    {
        if ($booking->cashback_amount !== null && (float) $booking->cashback_amount > 0) {
            return (float) $booking->cashback_amount;
        }

        $customerId = $booking->customer_id;
        if (!$customerId) {
            return 0.0;
        }

        $subscription = $this->activeSubscriptionForUser($customerId);
        $package = $subscription?->membershipPackage;
        $cashbackPercent = (float) ($package?->cashback_percent ?? 0);
        if (!$package || $cashbackPercent <= 0) {
            return 0.0;
        }

        $baseAmount = (float) ($booking->final_amount ?? $booking->total_price ?? 0);
        $cashbackAmount = round($baseAmount * $cashbackPercent / 100, 2);
        if ($cashbackAmount <= 0) {
            return 0.0;
        }

        return DB::transaction(function () use ($booking, $customerId, $cashbackAmount, $package): float {
            if (
                DB::table('user_wallet_ledgers')
                    ->where('reference_type', 'vip_cashback')
                    ->where('reference_id', $booking->id)
                    ->exists()
            ) {
                return (float) ($booking->fresh()->cashback_amount ?? 0);
            }

            $wallet = DB::table('user_wallets')
                ->where('user_id', $customerId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $walletId = (string) Str::uuid();
                DB::table('user_wallets')->insert([
                    'id' => $walletId,
                    'user_id' => $customerId,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $wallet = DB::table('user_wallets')
                    ->where('id', $walletId)
                    ->lockForUpdate()
                    ->first();
            }

            $before = (float) $wallet->balance;
            $after = round($before + $cashbackAmount, 2);

            DB::table('user_wallets')->where('id', $wallet->id)->update([
                'balance' => $after,
                'updated_at' => now(),
            ]);

            DB::table('user_wallet_ledgers')->insert([
                'id' => (string) Str::uuid(),
                'user_wallet_id' => $wallet->id,
                'transaction_code' => $this->uniqueWalletTransactionCode(),
                'type' => 'adjustment',
                'direction' => 'credit',
                'amount' => $cashbackAmount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'vip_cashback',
                'reference_id' => $booking->id,
                'status' => 'completed',
                'note' => 'Cashback goi VIP ' . $this->packageLabel($package->type, $package->name),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $booking->update(['cashback_amount' => $cashbackAmount]);

            return $cashbackAmount;
        });
    }

    public function userHasVipPackage(string $userId, string $packageType): bool
    {
        $subscription = $this->activeSubscriptionForUser($userId);

        return $subscription?->membershipPackage?->type === $packageType;
    }

    public function hasPriorityComplaint(string $userId): bool
    {
        $subscription = $this->activeSubscriptionForUser($userId);

        return (bool) ($subscription?->membershipPackage?->priority_complaint ?? false);
    }

    public function subscriptionPayload(UserSubscription $subscription): array
    {
        $package = $subscription->membershipPackage;

        return [
            'id' => $subscription->id,
            'billing_cycle' => $subscription->billing_cycle,
            'started_at' => $subscription->started_at,
            'expires_at' => $subscription->expires_at,
            'status' => $subscription->status,
            'paid_amount' => (float) $subscription->paid_amount,
            'month_post_count' => (int) $subscription->month_post_count,
            'month_post_reset_at' => $subscription->month_post_reset_at,
            'package' => $package ? $this->packagePayload($package) : null,
            'badge' => $package ? $this->badgePayload($package) : null,
        ];
    }

    private function issueMonthlyVouchersForSubscription(UserSubscription $subscription, ?Carbon $month = null): int
    {
        $subscription->loadMissing('membershipPackage');
        $package = $subscription->membershipPackage;
        $month = ($month ?: now())->copy()->startOfMonth();

        if (!$package || $package->type === 'free' || !$package->is_active || $package->voucher_count_per_month <= 0) {
            return 0;
        }

        if ($subscription->expires_at && $subscription->expires_at->lte($month)) {
            return 0;
        }

        $alreadyIssued = DB::table('vouchers')
            ->where('source', 'vip_subscription')
            ->where('subscription_id', $subscription->id)
            ->whereDate('valid_from', $month->toDateString())
            ->count();

        $remaining = max((int) $package->voucher_count_per_month - $alreadyIssued, 0);
        if ($remaining <= 0) {
            return 0;
        }

        $validTo = $month->copy()->endOfMonth()->endOfDay();
        if ($subscription->expires_at && $subscription->expires_at->lt($validTo)) {
            $validTo = $subscription->expires_at->copy();
        }

        for ($i = 0; $i < $remaining; $i++) {
            $voucherId = (string) Str::uuid();
            DB::table('vouchers')->insert([
                'id' => $voucherId,
                'code' => $this->uniqueVipVoucherCode($package),
                'name' => 'Voucher VIP ' . $this->packageLabel($package->type, $package->name),
                'description' => 'Voucher phat tu goi VIP he thong, chi dung cho dung tai khoan nhan voucher.',
                'owner_type' => 'system',
                'owner_id' => null,
                'funded_by' => 'system',
                'stacking_rule' => 'allow_with_venue',
                'discount_type' => 'percent',
                'discount_value' => $package->voucher_discount_percent,
                'max_discount_amount' => $package->voucher_max_discount_amount,
                'min_order_amount' => $package->voucher_min_order_amount,
                'total_quantity' => 1,
                'used_quantity' => 0,
                'per_user_limit' => 1,
                'valid_from' => $month->copy()->startOfDay(),
                'valid_to' => $validTo,
                'status' => 'active',
                'source' => 'vip_subscription',
                'subscription_id' => $subscription->id,
                'assigned_user_id' => $subscription->user_id,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('voucher_scopes')->insert([
                'id' => (string) Str::uuid(),
                'voucher_id' => $voucherId,
                'scope_type' => 'all',
                'scope_id' => null,
                'scope_key' => 'all:all',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $remaining;
    }

    private function invalidateSubscriptionVouchers(string $subscriptionId): void
    {
        DB::table('vouchers')
            ->where('source', 'vip_subscription')
            ->where('subscription_id', $subscriptionId)
            ->where('status', 'active')
            ->whereColumn('used_quantity', '<', 'total_quantity')
            ->update([
                'status' => 'expired',
                'valid_to' => now(),
                'updated_at' => now(),
            ]);
    }

    private function failPendingSubscriptionPayments(string $subscriptionId): void
    {
        Payment::query()
            ->where('payment_context', 'vip_subscription')
            ->where('subscription_id', $subscriptionId)
            ->where('status', 'pending')
            ->get()
            ->each(function (Payment $payment): void {
                $statusBefore = $payment->status;
                $payment->update(['status' => 'failed']);

                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => 'vip_subscription_payment_cancelled',
                    'status_before' => $statusBefore,
                    'status_after' => $payment->status,
                    'error_code' => 'subscription_replaced',
                    'error_message' => 'Yeu cau mua VIP moi da thay the payment pending cu.',
                ]);
            });
    }

    private function resolveSystemBankAccount(): SystemBankAccount
    {
        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();

        if ($account) {
            return $account;
        }

        $account = SystemBankAccount::query()
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($account) {
            return $account;
        }

        throw new RuntimeException('Chua co tai khoan ngan hang he thong dang hoat dong de nhan thanh toan VIP.');
    }

    private function qrUrl(Payment $payment, SystemBankAccount $account): string
    {
        return rtrim((string) config('services.sepay.qr_base_url', 'https://qr.sepay.vn/img'), '?') . '?' . http_build_query([
            'acc' => $account->account_number,
            'bank' => $account->bank_code ?: $account->bank_name,
            'amount' => (int) round((float) $payment->amount),
            'des' => $payment->payment_code,
            'template' => 'compact',
        ]);
    }

    private function availableCycles(MembershipPackage $package): array
    {
        return collect(self::BILLING_MONTHS)
            ->keys()
            ->filter(fn(string $cycle): bool => $this->priceForCycle($package, $cycle) !== null)
            ->map(fn(string $cycle): array => [
                'key' => $cycle,
                'months' => self::BILLING_MONTHS[$cycle],
                'price' => $this->priceForCycle($package, $cycle),
                'label' => match ($cycle) {
                    'monthly' => 'Hang thang',
                    'quarterly' => 'Hang quy',
                    default => 'Hang nam',
                },
            ])
            ->values()
            ->all();
    }

    private function priceForCycle(MembershipPackage $package, string $cycle): ?float
    {
        $value = match ($cycle) {
            'monthly' => $package->monthly_price,
            'quarterly' => $package->quarterly_price,
            'yearly' => $package->yearly_price,
            default => null,
        };

        return $value !== null ? (float) $value : null;
    }

    private function badgePayload(MembershipPackage $package): ?array
    {
        if ($package->type === 'free') {
            return null;
        }

        return [
            'label' => $package->badge_name ?: $this->packageLabel($package->type, $package->name),
            'type' => $package->type,
            'class' => 'vip-' . $package->type,
        ];
    }

    private function packageLabel(?string $type, ?string $name): string
    {
        return match ($type) {
            'free' => 'Thường',
            'saving' => 'Tiết kiệm',
            'pro' => 'Pro',
            default => $name ?: 'VIP',
        };
    }

    private function uniqueVipVoucherCode(MembershipPackage $package): string
    {
        do {
            $code = 'VIP' . Str::upper(substr($package->type, 0, 3)) . now()->format('ym') . Str::upper(Str::random(6));
        } while (DB::table('vouchers')->where('code', $code)->exists());

        return $code;
    }

    private function uniquePaymentCode(): string
    {
        do {
            $code = 'PM' . Str::upper(Str::random(10));
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }

    private function uniqueWalletTransactionCode(): string
    {
        do {
            $code = 'CB' . now()->format('ymdHis') . Str::upper(Str::random(6));
        } while (DB::table('user_wallet_ledgers')->where('transaction_code', $code)->exists());

        return $code;
    }
}
