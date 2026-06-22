<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRefundWithdrawalRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private User $customer;

    private User $admin;

    private VenueCluster $cluster;

    private Booking $booking;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);
        $userRole = Role::query()->create([
            'name' => 'user',
            'display_name' => 'Khách hàng',
            'is_system' => true,
        ]);
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Quản trị viên',
            'is_system' => true,
        ]);

        $this->owner = $this->createUser('owner_refund', 'owner.refund@sportgo.vn');
        $this->otherOwner = $this->createUser('owner_other', 'owner.other@sportgo.vn');
        $this->customer = $this->createUser('refund_customer', 'refund.customer@sportgo.vn');
        $this->admin = $this->createUser('refund_admin', 'refund.admin@sportgo.vn');

        $this->assignRole($this->owner, $ownerRole);
        $this->assignRole($this->otherOwner, $ownerRole);
        $this->assignRole($this->customer, $userRole);
        $this->assignRole($this->admin, $adminRole);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'SportGo Refund Test',
            'slug' => 'sportgo-refund-test',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $startAt = now()->addHours(10)->startOfHour();
        $this->booking = Booking::query()->create([
            'booking_code' => 'BK-OWNER-REFUND-01',
            'customer_id' => $this->customer->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addHour()->format('H:i:s'),
            'total_price' => 100000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'cancelled',
            'status_reason' => 'Khách thay đổi kế hoạch.',
            'cancelled_by' => $this->customer->id,
            'cancelled_at' => now(),
        ]);

        $this->payment = Payment::query()->create([
            'payment_code' => 'PM-OWNER-REFUND-01',
            'booking_id' => $this->booking->id,
            'amount' => 100000,
            'wallet_amount' => 0,
            'gateway_amount' => 100000,
            'payment_kind' => 'full',
            'method' => 'sepay',
            'gateway_txn_id' => 'SEPAY-OWNER-REFUND-01',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        BookingConfig::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'cancel_before_hours' => 6,
            'refund_percent' => 50,
        ]);
    }

    public function test_owner_only_sees_refunds_from_owned_venue_clusters(): void
    {
        $refund = $this->createRefund();

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/refunds')
            ->assertOk()
            ->assertJsonPath('data.0.id', $refund->id)
            ->assertJsonPath('data.0.booking.booking_code', 'BK-OWNER-REFUND-01')
            ->assertJsonPath('data.0.policy_evaluation.suggested_amount', 50000);

        $this->actingAs($this->otherOwner, 'sanctum')
            ->getJson('/api/owner/refunds')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_approval_obeys_policy_and_records_audit_history_and_notifications(): void
    {
        $refund = $this->createRefund();

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/refunds/{$refund->id}/decision", [
                'decision' => 'approve',
                'amount' => 60000,
                'note' => 'Đồng ý hoàn 50% theo chính sách.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'owner_confirmed')
            ->assertJsonPath('data.amount', '50000.00');

        $this->assertDatabaseHas('refund_status_histories', [
            'refund_id' => $refund->id,
            'old_status' => 'pending_owner_confirmation',
            'new_status' => 'owner_confirmed',
            'changed_by' => $this->owner->id,
            'actor_type' => 'owner',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'refund.owner_approved',
            'entity_id' => $refund->id,
            'context' => 'owner',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'type' => 'refund_owner_approved',
            'reference_id' => $refund->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'type' => 'refund_ready_for_admin',
            'reference_id' => $refund->id,
        ]);
    }

    public function test_other_owner_cannot_decide_refund(): void
    {
        $refund = $this->createRefund();

        $this->actingAs($this->otherOwner, 'sanctum')
            ->patchJson("/api/owner/refunds/{$refund->id}/decision", [
                'decision' => 'reject',
                'note' => 'Không thuộc sân này.',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('refunds', [
            'id' => $refund->id,
            'status' => 'pending_owner_confirmation',
        ]);
    }

    public function test_withdrawal_requires_active_owned_account_and_holds_online_balance(): void
    {
        $wallet = OwnerWallet::query()->create([
            'owner_id' => $this->owner->id,
            'venue_cluster_id' => $this->cluster->id,
            'available_balance' => 200000,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 200000,
            'total_withdrawn' => 0,
        ]);
        $account = OwnerBankAccount::query()->create([
            'owner_id' => $this->owner->id,
            'bank_name' => 'TPBank',
            'bank_code' => 'TPB',
            'account_number' => '729069999999',
            'account_holder_name' => 'OWNER REFUND',
            'status' => 'active',
            'is_default' => true,
        ]);
        $otherAccount = OwnerBankAccount::query()->create([
            'owner_id' => $this->otherOwner->id,
            'bank_name' => 'MB Bank',
            'bank_code' => 'MB',
            'account_number' => '123456789',
            'account_holder_name' => 'OTHER OWNER',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/finance/withdrawals', [
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $otherAccount->id,
                'amount' => 100000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('owner_bank_account_id');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/finance/withdrawals', [
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $account->id,
                'amount' => 120000,
                'owner_note' => 'Rút doanh thu online.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.owner_bank_account_id', $account->id);

        $withdrawalId = $response->json('data.id');
        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 80000,
            'pending_withdrawal_balance' => 120000,
        ]);
        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'reference_type' => 'withdrawal',
            'reference_id' => $withdrawalId,
            'type' => 'hold',
            'amount' => 120000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'withdrawal.owner_requested',
            'entity_id' => $withdrawalId,
        ]);

        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 80000,
            'pending_withdrawal_balance' => 120000,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/finance/withdrawals', [
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $account->id,
                'amount' => 90000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_owner_can_cancel_pending_withdrawal_and_releases_held_balance(): void
    {
        $wallet = OwnerWallet::query()->create([
            'owner_id' => $this->owner->id,
            'venue_cluster_id' => $this->cluster->id,
            'available_balance' => 200000,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 200000,
            'total_withdrawn' => 0,
        ]);
        $account = OwnerBankAccount::query()->create([
            'owner_id' => $this->owner->id,
            'bank_name' => 'TPBank',
            'bank_code' => 'TPB',
            'account_number' => '729069999999',
            'account_holder_name' => 'OWNER REFUND',
            'status' => 'active',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/finance/withdrawals', [
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $account->id,
                'amount' => 120000,
                'owner_note' => 'Rút doanh thu online.',
            ])
            ->assertCreated();

        $withdrawalId = $response->json('data.id');

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/finance/withdrawals/{$withdrawalId}/cancel", [
                'reason' => 'Chưa cần rút nữa.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('owner_withdrawal_requests', [
            'id' => $withdrawalId,
            'status' => 'cancelled',
            'status_reason' => 'Chưa cần rút nữa.',
        ]);
        $this->assertDatabaseHas('owner_wallets', [
            'id' => $wallet->id,
            'available_balance' => 200000,
            'pending_withdrawal_balance' => 0,
        ]);
        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'reference_type' => 'withdrawal',
            'reference_id' => $withdrawalId,
            'type' => 'release',
            'amount' => 120000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'withdrawal.owner_cancelled',
            'entity_id' => $withdrawalId,
        ]);
    }

    private function createRefund(): Refund
    {
        return Refund::query()->create([
            'payment_id' => $this->payment->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'amount' => 50000,
            'reason' => 'Khách thay đổi kế hoạch.',
            'refund_destination' => 'original_payment',
            'status' => 'pending_owner_confirmation',
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
