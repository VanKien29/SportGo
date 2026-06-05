<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Refund;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Models\UserRole;
use App\Models\VenueCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $payout = UserPayoutAccount::query()->create([
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
            'user_payout_account_id' => $payout->id,
            'owner_confirmed_by' => $this->owner->id,
            'owner_confirmed_at' => now(),
            'status' => 'pending_confirmation',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/finance/refunds')
            ->assertOk()
            ->assertJsonPath('data.0.booking.booking_code', 'BKFINANCE01')
            ->assertJsonPath('data.0.refund_destination.account_number', '123456789')
            ->assertJsonPath('data.0.owner_confirmation.confirmed', true);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'status' => 'paid']);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/refunds/{$refund->id}/status", [
                'status' => 'completed',
                'reason' => 'Đã chuyển hoàn tiền cho khách.',
                'gateway_refund_txn_id' => 'MB-REFUND-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.receipt.amount', '40000.00');

        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'status' => 'refunded']);
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

    public function test_reject_reason_is_required_for_refund(): void
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
            ->assertJsonValidationErrors('reason');
    }

    public function test_processing_bank_refunds_can_be_exported_for_bulk_transfer(): void
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
            'status' => 'processing',
        ]);

        $export = $this->actingAs($this->finance, 'sanctum')
            ->postJson('/api/admin/finance/refunds/export', ['ids' => [$refund->id]])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $xlsx = $export->getContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertStringContainsString($refund->fresh()->payout_transfer_code, $xlsx);
        $this->assertStringContainsString('29206999999999', $xlsx);
        $this->assertStringContainsString('Kỹ Thương (TCB)', $xlsx);
    }

    public function test_refund_qr_and_sepay_outbound_webhook_complete_request(): void
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
            'status' => 'processing',
        ]);

        $qr = $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/finance/refunds/{$refund->id}/payout-qr")
            ->assertOk()
            ->assertJsonPath('data.recipient.account_number', '29206999999999')
            ->assertJsonPath('data.amount', 40000);

        $transferCode = $qr->json('data.transfer_code');
        $this->assertStringStartsWith('RF', $transferCode);
        $this->assertStringContainsString($transferCode, $qr->json('data.qr_url'));

        $this->postJson('/api/sepay/ipn', [
            'id' => 'SEPAY-RF-OUT-01',
            'gateway' => 'TPBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '729069999999',
            'code' => $transferCode,
            'content' => $transferCode.' HOAN TIEN SPORTGO',
            'transferType' => 'out',
            'transferAmount' => 40000,
            'referenceCode' => 'FT-RF-OUT-01',
        ])->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => 'completed',
            'gateway_refund_txn_id' => 'SEPAY-RF-OUT-01',
        ]);
        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('internal_receipts', [
            'receipt_type' => 'refund',
            'receiptable_id' => $refund->id,
            'amount' => 40000,
        ]);
    }

    public function test_withdrawal_approval_holds_balance_export_and_manual_complete_are_idempotent(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $account = $this->createOwnerBankAccount();
        $withdrawal = $this->createWithdrawal($wallet, $account, 70000, 'WRFINANCE01');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'approved',
                'reason' => 'Đủ điều kiện rút.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 30000,
            'pending_withdrawal_balance' => 70000,
        ]);

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

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'approved',
                'reason' => 'Duyệt để chuyển QR.',
            ])->assertOk();

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

    public function test_withdrawal_reject_requires_reason_and_releases_held_balance(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $withdrawal = $this->createWithdrawal($wallet, $this->createOwnerBankAccount(), 60000, 'WRFINANCE02');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'approved',
                'reason' => 'Duyệt để kiểm tra.',
            ])->assertOk();

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", ['status' => 'rejected'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'rejected',
                'reason' => 'Thông tin chuyển khoản chưa đúng.',
            ])->assertOk();

        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 100000,
            'pending_withdrawal_balance' => 0,
        ]);
        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'reference_id' => $withdrawal->id,
            'type' => 'release',
            'amount' => 60000,
        ]);
    }

    public function test_withdrawal_cannot_exceed_remaining_online_revenue(): void
    {
        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();
        $withdrawal = $this->createWithdrawal($wallet, $this->createOwnerBankAccount(), 120000, 'WRFINANCE03');

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/finance/withdrawals/{$withdrawal->id}/status", [
                'status' => 'approved',
                'reason' => 'Kiểm tra vượt số dư.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số tiền rút vượt quá doanh thu online còn lại.');

        $this->assertDatabaseHas('owner_wallets', ['id' => $wallet->id, 'available_balance' => 100000]);
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
        UserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);
    }
}
