<?php

namespace Tests\Feature;

use App\Models\MembershipPackage;
use App\Models\Complaint;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Memberships\SystemVipService;
use App\Services\Payments\SepayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemVipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_prices_are_calculated_from_monthly_price_using_package_rules(): void
    {
        $service = app(SystemVipService::class);

        $this->assertSame([
            'monthly_price' => 49000,
            'quarterly_price' => 125000,
            'yearly_price' => 441000,
        ], $service->pricesFromMonthly($this->createPackage('saving'), 49000));

        $this->assertSame([
            'monthly_price' => 99000,
            'quarterly_price' => 252000,
            'yearly_price' => 891000,
        ], $service->pricesFromMonthly($this->createPackage('pro'), 99000));
    }

    public function test_new_complaint_is_automatically_marked_as_vip_priority(): void
    {
        $user = $this->createUser();
        $pro = $this->createPackage('pro');

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'package_id' => $pro->id,
            'billing_cycle' => 'monthly',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
            'paid_amount' => 99000,
            'payment_ref' => 'VIP-PRIORITY-TEST',
            'month_post_count' => 0,
            'month_post_reset_at' => now()->startOfMonth(),
        ]);

        $complaint = Complaint::query()->create([
            'complaint_type' => 'system',
            'customer_id' => $user->id,
            'content' => 'Khiếu nại từ thành viên VIP.',
            'status' => 'open',
        ]);

        $this->assertTrue($complaint->is_vip_priority);
    }

    public function test_user_cannot_buy_another_vip_package_while_current_package_is_active(): void
    {
        $user = $this->createUser();
        $saving = $this->createPackage('saving');
        $pro = $this->createPackage('pro');

        $active = UserSubscription::query()->create([
            'user_id' => $user->id,
            'package_id' => $pro->id,
            'billing_cycle' => 'yearly',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
            'paid_amount' => 1000000,
            'payment_ref' => 'VIP-TEST-ACTIVE',
            'month_post_count' => 0,
            'month_post_reset_at' => now()->startOfMonth(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(SystemVipService::class)->subscribe($user, $saving, 'monthly');
        } finally {
            $this->assertDatabaseHas('user_subscriptions', [
                'id' => $active->id,
                'status' => 'active',
            ]);
            $this->assertSame(1, UserSubscription::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_user_can_buy_vip_when_old_active_record_is_already_expired(): void
    {
        $this->createSystemBankAccount();
        $user = $this->createUser();
        $saving = $this->createPackage('saving');
        $pro = $this->createPackage('pro');

        $old = UserSubscription::query()->create([
            'user_id' => $user->id,
            'package_id' => $saving->id,
            'billing_cycle' => 'monthly',
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->subMonth(),
            'status' => 'active',
            'paid_amount' => 50000,
            'payment_ref' => 'VIP-TEST-OLD',
            'month_post_count' => 0,
            'month_post_reset_at' => now()->subMonths(2)->startOfMonth(),
        ]);

        $result = app(SystemVipService::class)->subscribe($user, $pro, 'monthly');
        $subscription = $result['subscription'];
        $payment = $result['payment'];

        $this->assertSame('pending_payment', $subscription->status);
        $this->assertSame($pro->id, $subscription->package_id);
        $this->assertSame('vip_subscription', $payment->payment_context);
        $this->assertSame($subscription->id, $payment->subscription_id);
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $old->id,
            'status' => 'expired',
        ]);
    }

    public function test_sepay_ipn_activates_vip_subscription_and_issues_first_month_vouchers(): void
    {
        $account = $this->createSystemBankAccount();
        $user = $this->createUser();
        $pro = $this->createPackage('pro');

        $result = app(SystemVipService::class)->subscribe($user, $pro, 'monthly');
        $payment = $result['payment'];

        $ipnResult = app(SepayPaymentService::class)->handleIpn([
            'payment_code' => $payment->payment_code,
            'account_number' => $account->account_number,
            'transfer_type' => 'in',
            'amount' => $payment->amount,
            'transaction_id' => 'VIP-IPN-'.uniqid(),
            'content' => $payment->payment_code,
        ]);

        $this->assertTrue($ipnResult['success']);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_context' => 'vip_subscription',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $result['subscription']->id,
            'status' => 'active',
            'paid_amount' => '100000.00',
            'payment_ref' => $payment->payment_code,
        ]);
        $this->assertSame(2, \DB::table('vouchers')
            ->where('source', 'vip_subscription')
            ->where('subscription_id', $result['subscription']->id)
            ->where('assigned_user_id', $user->id)
            ->count());
    }

    private function createUser(): User
    {
        return User::query()->create([
            'username' => 'vip_user_'.uniqid(),
            'full_name' => 'VIP User',
            'email' => uniqid('vip_user').'@sportgo.test',
            'phone' => '09'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function createPackage(string $type): MembershipPackage
    {
        return MembershipPackage::query()->create([
            'name' => $type === 'pro' ? 'Pro' : 'Tiet kiem',
            'type' => $type,
            'monthly_price' => $type === 'pro' ? 100000 : 50000,
            'quarterly_price' => $type === 'pro' ? 270000 : 120000,
            'yearly_price' => $type === 'pro' ? 1000000 : 450000,
            'voucher_count_per_month' => $type === 'pro' ? 2 : 1,
            'voucher_discount_percent' => $type === 'pro' ? 10 : 5,
            'voucher_min_order_amount' => 100000,
            'voucher_max_discount_amount' => $type === 'pro' ? 70000 : 30000,
            'cashback_percent' => $type === 'pro' ? 5 : 2,
            'match_post_limit_per_month' => $type === 'pro' ? -1 : 15,
            'priority_complaint' => $type === 'pro',
            'badge_name' => $type === 'pro' ? 'SportGo Pro' : 'SportGo Saving',
            'is_active' => true,
            'sort_order' => $type === 'pro' ? 20 : 10,
        ]);
    }

    private function createSystemBankAccount(): SystemBankAccount
    {
        return SystemBankAccount::query()->create([
            'name' => 'Test SePay',
            'bank_name' => 'MBBank',
            'bank_code' => 'MB',
            'account_number' => '970422'.random_int(100000, 999999),
            'account_holder_name' => 'SPORTGO TEST',
            'status' => 'active',
            'is_default' => true,
        ]);
    }
}
