<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PolicyRule;
use App\Models\Refund;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Models\UserRole;
use App\Models\UserWallet;
use App\Models\UserWithdrawalRequest;
use App\Models\VenueCluster;
use App\Models\VenuePolicyRule;
use App\Services\Policies\RefundCancellationPolicyService;
use App\Services\Policies\RefundPolicyEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFinanceOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private User $owner;

    private User $customer;

    private VenueCluster $cluster;

    private Booking $booking;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sepay.webhook_api_key', null);

        $financeRole = Role::query()->create(['name' => 'finance_operator', 'display_name' => 'Tài chính', 'is_system' => true]);
        $ownerRole = Role::query()->create(['name' => 'venue_owner', 'display_name' => 'Chủ sân', 'is_system' => true]);
        $userRole = Role::query()->create(['name' => 'user', 'display_name' => 'Khách hàng', 'is_system' => true]);

        foreach (['refund.view', 'refund.approve', 'withdrawal.manage'] as $code) {
            $permission = Permission::query()->create(['code' => $code, 'name' => $code, 'group_name' => 'Tài chính']);
            RolePermission::query()->create(['role_id' => $financeRole->id, 'permission_id' => $permission->id]);
        }

        $this->finance = $this->createUser('finance_ops', 'finance.ops@sportgo.vn');
        $this->owner = $this->createUser('finance_owner', 'finance.owner@sportgo.vn');
        $this->customer = $this->createUser('finance_customer', 'finance.customer@sportgo.vn');

        $this->assignRole($this->finance, $financeRole);
        $this->assignRole($this->owner, $ownerRole);
        $this->assignRole($this->customer, $userRole);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'SportGo Finance',
            'slug' => 'sportgo-finance',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $this->booking = Booking::query()->create([
            'booking_code' => 'BKFINANCE01',
            'customer_id' => $this->customer->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-10',
            'total_price' => 100000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $this->payment = Payment::query()->create([
            'payment_code' => 'PMFINANCE01',
            'booking_id' => $this->booking->id,
            'amount' => 100000,
            'wallet_amount' => 0,
            'gateway_amount' => 100000,
            'payment_kind' => 'full',
            'method' => 'sepay',
            'gateway_txn_id' => 'SEPAY-FINANCE-01',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $wallet = OwnerWallet::query()->create([
            'owner_id' => $this->owner->id,
            'venue_cluster_id' => $this->cluster->id,
            'available_balance' => 100000,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 100000,
            'total_withdrawn' => 0,
        ]);

        OwnerWalletLedger::query()->create([
            'owner_wallet_id' => $wallet->id,
            'owner_id' => $this->owner->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_id' => $this->booking->id,
            'payment_id' => $this->payment->id,
            'type' => 'credit',
            'direction' => 'credit',
            'amount' => 100000,
            'balance_before' => 0,
            'balance_after' => 100000,
            'status' => 'completed',
            'reference_type' => 'payment',
            'reference_id' => $this->payment->id,
            'transaction_code' => 'OWC-FINANCE-01',
        ]);
    }

    public function test_refund_only_marks_payment_refunded_when_refund_is_completed(): void
    {
        UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'MB Bank',
            'bank_account_number' => '123456789',
            'bank_account_holder' => 'NGUYEN VAN A',
            'is_default' => true,
            'status' => 'active',
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 40000,
            'reason' => 'Khách hủy đúng chính sách.',
            'refund_destination' => 'bank_account',
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'pending_confirmation',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/finance/refunds')
            ->assertOk()
            ->assertJsonPath('data.0.booking.booking_code', 'BKFINANCE01')
            ->assertJsonPath('data.0.refund_destination.type', 'user_wallet')
            ->assertJsonPath('data.0.refund_destination.label', 'Ví SportGo')
            ->assertJsonPath('data.0.can_pay_by_qr', false)
            ->assertJsonPath('data.0.can_complete_wallet_refund', true)
            ->assertJsonPath('data.0.owner_confirmation.confirmed', true);

        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'status' => 'paid']);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'completed',
                'reason' => 'Đã hoàn tiền vào ví SportGo của khách.',
                'source' => 'mock',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.receipt.amount', '40000.00');

        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'refund_destination' => 'user_wallet',
            'gateway_refund_txn_id' => 'USER-WALLET-'.$refund->id,
        ]);
        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $this->customer->id,
            'balance' => 40000,
        ]);
        $this->assertDatabaseHas('user_wallet_ledgers', [
            'type' => 'refund',
            'direction' => 'credit',
            'amount' => 40000,
            'reference_type' => 'refund',
            'reference_id' => $refund->id,
        ]);
        $this->assertDatabaseHas('owner_wallets', ['owner_id' => $this->owner->id, 'available_balance' => 60000]);
        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'payment_id' => $this->payment->id,
            'type' => 'debit',
            'amount' => 40000,
            'reference_type' => 'refund',
            'reference_id' => $refund->id,
        ]);
        $this->assertDatabaseHas('internal_receipts', [
            'receipt_type' => 'refund',
            'receiptable_id' => $refund->id,
            'amount' => 40000,
        ]);
    }

    public function test_admin_cannot_reject_refund_requests(): void
    {
        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 100000,
            'reason' => 'Yêu cầu hoàn tiền.',
            'status' => 'pending_confirmation',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", ['status' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => 'pending_confirmation',
        ]);
    }

    public function test_admin_can_complete_walk_in_refund_by_creating_wallet_customer_from_phone(): void
    {
        $walkInBooking = Booking::query()->create([
            'booking_code' => 'BKWALKINREF01',
            'customer_id' => null,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-12',
            'total_price' => 30000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 30000,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
            'walk_in_name' => 'Nguyễn Văn Khách',
            'walk_in_phone' => '0912345678',
        ]);

        $walkInPayment = Payment::query()->create([
            'payment_code' => 'PMWALKINREF01',
            'booking_id' => $walkInBooking->id,
            'amount' => 30000,
            'wallet_amount' => 0,
            'gateway_amount' => 30000,
            'payment_kind' => 'full',
            'method' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $walkInPayment->id,
            'booking_id' => $walkInBooking->id,
            'customer_id' => null,
            'amount' => 30000,
            'reason' => 'Chủ sân khóa lịch, hoàn cho khách tại quầy.',
            'refund_destination' => 'user_wallet',
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'owner_confirmed',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/finance/refunds')
            ->assertOk()
            ->assertJsonFragment([
                'booking_code' => 'BKWALKINREF01',
                'full_name' => 'Nguyễn Văn Khách',
                'phone' => '0912345678',
                'is_walk_in' => true,
                'can_complete_wallet_refund' => true,
            ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'completed',
                'reason' => 'Admin hoàn tiền vào ví SportGo của khách tại quầy.',
                'source' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $createdCustomer = User::query()->where('phone', '0912345678')->firstOrFail();

        $this->assertDatabaseHas('bookings', [
            'id' => $walkInBooking->id,
            'customer_id' => $createdCustomer->id,
        ]);
        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'customer_id' => $createdCustomer->id,
            'refund_destination' => 'user_wallet',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('user_wallets', [
            'user_id' => $createdCustomer->id,
            'balance' => 30000,
        ]);
    }

    public function test_admin_cannot_set_refund_to_processing_manually(): void
    {
        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 50000,
            'reason' => 'Khách yêu cầu hoàn tiền.',
            'refund_destination' => 'original_payment',
            'status' => 'pending_owner_confirmation',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'processing',
                'reason' => 'Admin thử xử lý trước.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => 'pending_owner_confirmation',
        ]);
    }

    public function test_refund_completion_must_follow_active_refund_policy(): void
    {
        $this->createRefundPolicyRules();

        $startAt = now()->addHours(10)->startOfHour();
        $this->booking->update([
            'booking_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'cancelled_at' => now(),
            'status' => 'cancelled',
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 60000,
            'reason' => 'Khách hủy trước giờ chơi 10 tiếng.',
            'refund_destination' => 'bank_account',
            'status' => 'pending_confirmation',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/finance/refunds')
            ->assertOk()
            ->assertJsonPath('data.0.can_complete_wallet_refund', false)
            ->assertJsonPath('data.0.policy_evaluation.compliant', false);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'completed',
                'reason' => 'Đã hoàn tiền vào ví SportGo của khách.',
                'source' => 'mock',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số tiền hoàn vượt quá chính sách hiện tại. Tối đa có thể hoàn là 50.000đ.');

        $refund->update(['amount' => 50000]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'completed',
                'reason' => 'Đã hoàn tiền vào ví SportGo của khách.',
                'source' => 'mock',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.policy_evaluation.refund_percent', 50)
            ->assertJsonPath('data.policy_evaluation.compliant', true);
    }

    public function test_refund_evaluation_uses_owner_policy_before_system_default(): void
    {
        $service = app(RefundCancellationPolicyService::class);
        $this->createCancelRefundPolicyWithOptionalVenueOverride($service, true);
        $this->moveBookingToCancelledAtHoursBeforeStart(10);

        $refund = $this->createPolicyRefund(90000);

        $result = app(RefundPolicyEvaluator::class)->evaluate($refund);

        $this->assertSame('venue_policy_rule', $result['source']);
        $this->assertSame(90.0, $result['refund_percent']);
        $this->assertSame(90000.0, $result['suggested_amount']);
        $this->assertTrue($result['compliant']);
        $this->assertStringContainsString('Chính sách riêng của cụm sân', $result['summary']);
        $this->assertNotNull($result['rule']['venue_rule_id'] ?? null);
    }

    public function test_refund_evaluation_falls_back_to_system_policy_when_owner_policy_missing(): void
    {
        $service = app(RefundCancellationPolicyService::class);
        $this->createCancelRefundPolicyWithOptionalVenueOverride($service, false);
        $this->moveBookingToCancelledAtHoursBeforeStart(10);

        $refund = $this->createPolicyRefund(50000);

        $result = app(RefundPolicyEvaluator::class)->evaluate($refund);

        $this->assertSame('system_policy_rule', $result['source']);
        $this->assertSame(50.0, $result['refund_percent']);
        $this->assertSame(50000.0, $result['suggested_amount']);
        $this->assertTrue($result['compliant']);
        $this->assertStringContainsString('Chính sách mặc định hệ thống', $result['summary']);
        $this->assertArrayNotHasKey('venue_rule_id', $result['rule']);
    }

    public function test_refunds_are_not_exported_because_they_credit_user_wallet(): void
    {
        $payout = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 2000,
            'reason' => 'Hoàn tiền test chuyển khoản theo lô.',
            'refund_destination' => 'bank_account',
            'user_payout_account_id' => $payout->id,
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'owner_confirmed',
        ]);

        $export = $this->actingAs($this->finance, 'sanctum')
            ->postJson('/api/admin/finance/refunds/export', ['ids' => [$refund->id]])
            ->assertStatus(422);

        $this->assertSame(
            'Hoàn tiền được xử lý vào ví SportGo của khách nên không export chuyển khoản ngân hàng.',
            $export->json('message')
        );
    }

    public function test_refund_qr_is_disabled_because_refunds_credit_user_wallet(): void
    {
        $payout = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 40000,
            'reason' => 'Hoàn tiền bằng QR.',
            'refund_destination' => 'bank_account',
            'user_payout_account_id' => $payout->id,
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'owner_confirmed',
        ]);

        $qr = $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/refunds/{$refund->id}/payout-qr")
            ->assertStatus(422);

        $this->assertSame(
            'Hoàn tiền hiện được cộng trực tiếp vào ví SportGo của khách, không tạo QR chuyển khoản.',
            $qr->json('message')
        );
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'owner_confirmed']);
    }

    public function test_original_payment_refund_is_presented_as_user_wallet_refund(): void
    {
        $payout = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);

        $refund = Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => null,
            'amount' => 2000,
            'reason' => 'Hoàn tiền dữ liệu cũ.',
            'refund_destination' => 'original_payment',
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'owner_confirmed',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/finance/refunds')
            ->assertOk()
            ->assertJsonPath('data.0.can_pay_by_qr', false)
            ->assertJsonPath('data.0.can_complete_wallet_refund', true)
            ->assertJsonPath('data.0.refund_destination.type', 'user_wallet')
            ->assertJsonPath('data.0.refund_destination.label', 'Ví SportGo');

        $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/refunds/{$refund->id}/payout-qr")
            ->assertStatus(422);

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'refund_destination' => 'original_payment',
            'user_payout_account_id' => null,
        ]);
    }

    public function test_pending_withdrawal_can_be_exported_and_completed_directly(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $account = $this->createOwnerBankAccount();
        $withdrawal = $this->createWithdrawal($wallet, $account, 70000, 'WRFINANCE01');

        $export = $this->actingAs($this->finance, 'sanctum')
            ->postJson('/api/admin/finance/withdrawals/export', ['ids' => [$withdrawal->id]])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $xlsx = $export->getContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertStringContainsString('xl/worksheets/sheet1.xml', $xlsx);
        $this->assertStringContainsString('eMB_BulkPayment', $xlsx);
        $this->assertStringContainsString($withdrawal->fresh()->payout_transfer_code, $xlsx);
        $this->assertStringContainsString('729069999999', $xlsx);
        $this->assertStringContainsString('Tiên Phong (TPB)', $xlsx);

        $payload = [
            'status' => 'completed',
            'reason' => 'Đã chuyển tiền theo file export.',
            'transfer_reference' => 'MB-BULK-DETAIL-01',
            'source' => 'mock',
        ];

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.transfer_reference', 'MB-BULK-DETAIL-01');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 30000,
            'pending_withdrawal_balance' => 0,
            'total_withdrawn' => 70000,
        ]);
        $this->assertDatabaseHas('internal_receipts', [
            'receipt_type' => 'withdrawal',
            'receiptable_id' => $withdrawal->id,
            'amount' => 70000,
        ]);
        $this->assertSame(1, OwnerWalletLedger::query()
            ->where('reference_type', 'withdrawal')
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'debit')
            ->count());
    }

    public function test_withdrawal_qr_can_be_checked_against_sepay_transactions_api(): void
    {
        config()->set('services.sepay.api_token', 'test-sepay-token');
        config()->set('services.sepay.api_base_url', 'https://userapi.sepay.vn/v2');

        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $withdrawal = $this->createWithdrawal($wallet, $this->createOwnerBankAccount(), 50000, 'WRFINANCE04');

        $qr = $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/withdrawals/{$withdrawal->id}/payout-qr")
            ->assertOk()
            ->assertJsonPath('data.recipient.account_number', '729069999999')
            ->assertJsonPath('data.amount', 50000);

        $transferCode = $qr->json('data.transfer_code');

        Http::fake([
            'https://userapi.sepay.vn/v2/transactions*' => Http::response([
                'status' => 'success',
                'data' => [[
                    'id' => 'SEPAY-WD-OUT-01',
                    'transaction_date' => now()->format('Y-m-d H:i:s'),
                    'account_number' => '729069999999',
                    'transfer_type' => 'out',
                    'amount_out' => 50000,
                    'transaction_content' => $transferCode.' RUT TIEN SPORTGO',
                    'reference_number' => 'FT-WD-OUT-01',
                    'code' => $transferCode,
                ]],
            ]),
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/withdrawals/{$withdrawal->id}/payout-check")
            ->assertOk()
            ->assertJsonPath('completed', true);

        $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/withdrawals/{$withdrawal->id}/payout-check")
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('message', 'Yêu cầu rút tiền đã hoàn tất trước đó.');

        $this->assertDatabaseHas('owner_withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'completed',
            'transfer_reference' => 'SEPAY-WD-OUT-01',
        ]);
        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 50000,
            'pending_withdrawal_balance' => 0,
            'total_withdrawn' => 50000,
        ]);
    }

    public function test_admin_cannot_reject_withdrawal_requests(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $withdrawal = $this->createWithdrawal($wallet, $this->createOwnerBankAccount(), 60000, 'WRFINANCE02');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", ['status' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 100000,
            'pending_withdrawal_balance' => 0,
        ]);
        $this->assertDatabaseHas('owner_withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'pending',
        ]);
    }

    public function test_withdrawal_cannot_exceed_remaining_online_revenue(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $withdrawal = $this->createWithdrawal($wallet, $this->createOwnerBankAccount(), 120000, 'WRFINANCE03');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'completed',
                'reason' => 'Đã chuyển tiền theo QR.',
                'transfer_reference' => 'MB-WD-OVER-BALANCE',
                'source' => 'mock',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số tiền rút vượt quá doanh thu online còn lại.');

        $this->assertDatabaseHas('owner_wallets', ['id' => $wallet->id, 'available_balance' => 100000]);
    }

    public function test_admin_can_pay_user_withdrawal_from_locked_balance_by_cash(): void
    {
        $wallet = UserWallet::query()->create([
            'user_id' => $this->customer->id,
            'balance' => 70000,
            'locked_balance' => 30000,
            'status' => 'active',
        ]);
        $account = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);
        $withdrawal = UserWithdrawalRequest::query()->create([
            'user_wallet_id' => $wallet->id,
            'user_id' => $this->customer->id,
            'payout_account_id' => $account->id,
            'amount' => 30000,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/pay", [
                'payment_method' => 'cash',
                'note' => 'Đã trả tiền mặt tại quầy.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_method', 'cash');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $wallet->refresh();
        $this->assertSame(70000.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->locked_balance);
        $this->assertSame(1, DB::table('user_wallet_ledgers')
            ->where('reference_type', 'user_withdrawal')
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'withdrawal')
            ->where('status', 'completed')
            ->count());
        $this->assertDatabaseHas('user_wallet_ledgers', [
            'user_wallet_id' => $wallet->id,
            'reference_type' => 'user_withdrawal',
            'reference_id' => $withdrawal->id,
            'type' => 'withdrawal',
            'direction' => 'debit',
            'amount' => 30000,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('internal_receipts', [
            'receiptable_type' => UserWithdrawalRequest::class,
            'receiptable_id' => $withdrawal->id,
            'receipt_type' => 'withdrawal',
            'amount' => 30000,
        ]);
    }

    public function test_admin_can_pay_legacy_user_withdrawal_from_available_balance_by_bank_transfer(): void
    {
        $wallet = UserWallet::query()->create([
            'user_id' => $this->customer->id,
            'balance' => 50000,
            'locked_balance' => 0,
            'status' => 'active',
        ]);
        $account = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);
        $withdrawal = UserWithdrawalRequest::query()->create([
            'user_wallet_id' => $wallet->id,
            'user_id' => $this->customer->id,
            'payout_account_id' => $account->id,
            'amount' => 20000,
            'status' => 'approved',
            'requested_at' => now(),
            'approved_at' => now(),
            'approved_by' => $this->finance->id,
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/pay", [
                'payment_method' => 'bank_transfer',
                'transfer_reference' => 'FT-USER-WD-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.transfer_reference', 'FT-USER-WD-001');

        $wallet->refresh();
        $this->assertSame(30000.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->locked_balance);
    }

    public function test_user_withdrawal_qr_is_completed_from_sepay_outbound_transaction(): void
    {
        config()->set('services.sepay.api_token', 'test-sepay-token');
        config()->set('services.sepay.api_base_url', 'https://userapi.sepay.vn/v2');

        $wallet = UserWallet::query()->create([
            'user_id' => $this->customer->id,
            'balance' => 50000,
            'locked_balance' => 20000,
            'status' => 'active',
        ]);
        $account = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);
        $withdrawal = UserWithdrawalRequest::query()->create([
            'user_wallet_id' => $wallet->id,
            'user_id' => $this->customer->id,
            'payout_account_id' => $account->id,
            'amount' => 20000,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $qr = $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/payout-qr")
            ->assertOk()
            ->assertJsonPath('data.recipient.account_number', '29206999999999')
            ->assertJsonPath('data.amount', 20000);

        $transferCode = $qr->json('data.transfer_code');
        $this->assertStringStartsWith('UW', $transferCode);
        $this->assertStringContainsString('qr.sepay.vn/img', $qr->json('data.qr_url'));

        Http::fake([
            'https://userapi.sepay.vn/v2/transactions*' => Http::response([
                'status' => 'success',
                'data' => [[
                    'id' => 'SEPAY-UW-OUT-01',
                    'transaction_date' => now()->format('Y-m-d H:i:s'),
                    'account_number' => '29206999999999',
                    'transfer_type' => 'out',
                    'amount_out' => 20000,
                    'transaction_content' => $transferCode.' RUT VI SPORTGO',
                    'reference_number' => 'FT-UW-OUT-01',
                    'code' => $transferCode,
                ]],
            ]),
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/payout-check")
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('data.status', 'paid');

        $wallet->refresh();
        $this->assertSame(50000.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->locked_balance);
        $this->assertDatabaseHas('user_withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'transfer_reference' => 'SEPAY-UW-OUT-01',
        ]);
    }

    public function test_admin_cannot_pay_user_withdrawal_when_wallet_balance_is_insufficient(): void
    {
        $wallet = UserWallet::query()->create([
            'user_id' => $this->customer->id,
            'balance' => 10000,
            'locked_balance' => 0,
            'status' => 'active',
        ]);
        $account = UserPayoutAccount::query()->create([
            'user_id' => $this->customer->id,
            'bank_name' => 'Techcombank',
            'bank_account_number' => '29206999999999',
            'bank_account_holder' => 'NGUYEN VAN KIEN',
            'is_default' => true,
            'status' => 'active',
        ]);
        $withdrawal = UserWithdrawalRequest::query()->create([
            'user_wallet_id' => $wallet->id,
            'user_id' => $this->customer->id,
            'payout_account_id' => $account->id,
            'amount' => 20000,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/user-withdrawals/{$withdrawal->id}/pay", [
                'payment_method' => 'cash',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ví người dùng không đủ số dư để chi trả yêu cầu rút tiền.');

        $wallet->refresh();
        $this->assertSame(10000.0, (float) $wallet->balance);
        $this->assertSame('pending', $withdrawal->fresh()->status);
    }

    private function createOwnerBankAccount(): OwnerBankAccount
    {
        return OwnerBankAccount::query()->create([
            'owner_id' => $this->owner->id,
            'bank_name' => 'TPBank',
            'bank_code' => 'TPB',
            'account_number' => '729069999999',
            'account_holder_name' => 'NGUYEN VAN KIEN',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    private function createRefundPolicyRules(): void
    {
        $policy = SystemPolicy::query()->create([
            'key' => 'refund_policy_test',
            'version' => 1,
            'title' => 'Chính sách hoàn tiền test',
            'content' => 'Test policy',
            'type' => 'refund',
            'policy_type' => 'refund',
            'status' => 'active',
            'is_active' => true,
            'is_overridable' => false,
            'priority' => 100,
            'effective_from' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        $policy->actionBindings()->create([
            'module' => 'refund',
            'action_code' => 'booking.cancel',
            'description' => 'Test booking cancel refund policy',
            'is_active' => true,
        ]);

        $policy->rules()->create([
            'action_code' => 'booking.cancel',
            'rule_code' => 'cancel_before_24h_refund_100_test',
            'rule_name' => 'Hủy trước 24 giờ hoàn 100%',
            'rule_type' => 'refund_time_window',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'booking_cancel_refund',
            'condition_json' => ['hours_before_start' => ['gte' => 24]],
            'result_json' => ['refund_percent' => 100],
            'priority' => 300,
            'is_active' => true,
        ]);

        $policy->rules()->create([
            'action_code' => 'booking.cancel',
            'rule_code' => 'cancel_before_6h_refund_50_test',
            'rule_name' => 'Hủy trước 6 giờ hoàn 50%',
            'rule_type' => 'refund_time_window',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'booking_cancel_refund',
            'condition_json' => ['hours_before_start' => ['gte' => 6, 'lt' => 24]],
            'result_json' => ['refund_percent' => 50],
            'priority' => 200,
            'is_active' => true,
        ]);
    }

    private function createCancelRefundPolicyWithOptionalVenueOverride(RefundCancellationPolicyService $service, bool $withVenueOverride): PolicyRule
    {
        $systemTiers = $service->defaultCancelRefundTiers();
        foreach ($systemTiers as &$tier) {
            if ($tier['key'] === 'from_6_to_24') {
                $tier['refund_percent'] = 50;
            }
        }
        unset($tier);

        $policy = SystemPolicy::query()->create([
            'key' => 'booking_cancellation_cross_source_test',
            'version' => 1,
            'title' => 'Chính sách hủy/hoàn mặc định',
            'content' => 'Test policy',
            'type' => 'booking',
            'policy_type' => 'booking_cancellation',
            'status' => 'active',
            'is_active' => true,
            'is_overridable' => true,
            'priority' => 100,
            'effective_from' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        $policy->actionBindings()->create([
            'module' => 'booking',
            'action_code' => 'booking.cancel_by_customer',
            'description' => 'Khách hủy booking',
            'is_active' => true,
        ]);

        $rule = $policy->rules()->create([
            'action_code' => 'booking.cancel_by_customer',
            'rule_code' => 'cancel_before_hours_cross_source_test',
            'rule_name' => 'Bảng mốc hủy & hoàn mặc định',
            'rule_type' => RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
            'decision_key' => 'cancel_allowed',
            'conflict_group' => 'booking_cancel_window',
            'condition_json' => ['uses_cancel_refund_tier_table' => true],
            'result_json' => $service->cancelRefundResultJson($systemTiers, ['refund_basis' => 'paid_amount']),
            'priority' => 100,
            'is_active' => true,
        ]);

        if ($withVenueOverride) {
            $venueTiers = $systemTiers;
            foreach ($venueTiers as &$tier) {
                if ($tier['key'] === 'from_6_to_24') {
                    $tier['refund_percent'] = 90;
                }
            }
            unset($tier);

            VenuePolicyRule::query()->create([
                'venue_cluster_id' => $this->cluster->id,
                'base_policy_rule_id' => $rule->id,
                'action_code' => 'booking.cancel_by_customer',
                'rule_code' => 'cancel_before_hours_cross_source_override',
                'rule_name' => 'Bảng mốc hủy & hoàn riêng của cụm sân',
                'rule_type' => RefundCancellationPolicyService::CANCELLATION_RULE_TYPE,
                'condition_json' => ['uses_cancel_refund_tier_table' => true],
                'result_json' => $service->cancelRefundResultJson($venueTiers, ['refund_basis' => 'paid_amount']),
                'status' => 'active',
                'approved_by' => $this->finance->id,
                'approved_at' => now()->subHour(),
                'created_by' => $this->owner->id,
                'updated_by' => $this->owner->id,
                'submitted_by' => $this->owner->id,
                'submitted_at' => now()->subHours(2),
                'reviewed_by' => $this->finance->id,
                'reviewed_at' => now()->subHour(),
                'effective_from' => now()->subHour(),
            ]);
        }

        return $rule;
    }

    private function moveBookingToCancelledAtHoursBeforeStart(int $hoursBeforeStart): void
    {
        $startAt = now()->addHours($hoursBeforeStart)->startOfHour();

        $this->booking->update([
            'booking_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'cancelled_at' => now(),
            'status' => 'cancelled',
        ]);
    }

    private function createPolicyRefund(float $amount): Refund
    {
        return Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => $amount,
            'reason' => 'Khách hủy trước giờ chơi 10 tiếng.',
            'refund_destination' => 'user_wallet',
            'status' => 'pending_owner_confirmation',
        ]);
    }

    private function createWithdrawal(OwnerWallet $wallet, OwnerBankAccount $account, float $amount, string $code): OwnerWithdrawalRequest
    {
        return OwnerWithdrawalRequest::query()->create([
            'request_code' => $code,
            'owner_id' => $this->owner->id,
            'owner_wallet_id' => $wallet->id,
            'owner_bank_account_id' => $account->id,
            'amount' => $amount,
            'status' => 'pending',
            'owner_note' => 'Rút doanh thu online.',
            'requested_at' => now(),
        ]);
    }

    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $email,
            'phone' => '09'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    private function assignRole(User $user, Role $role): void
    {
        UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);
    }
}
