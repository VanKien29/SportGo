<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
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

        $financeRole = Role::query()->create([
            'name' => 'finance_operator',
            'display_name' => 'Tài chính',
            'is_system' => true,
        ]);
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

        foreach (['payment.view', 'payment.manage'] as $code) {
            $permission = Permission::query()->create([
                'code' => $code,
                'name' => $code,
                'group_name' => 'Tài chính',
            ]);

            RolePermission::query()->create([
                'role_id' => $financeRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        $this->finance = $this->createUser('finance_admin', 'finance@sportgo.vn');
        $this->owner = $this->createUser('payment_owner', 'owner.payment@sportgo.vn');
        $this->customer = $this->createUser('payment_customer', 'customer.payment@sportgo.vn');

        $this->assignRole($this->finance, $financeRole);
        $this->assignRole($this->owner, $ownerRole);
        $this->assignRole($this->customer, $userRole);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân thanh toán',
            'slug' => 'cum-san-thanh-toan',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $this->booking = Booking::query()->create([
            'booking_code' => 'BKADMINPAY',
            'customer_id' => $this->customer->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-10',
            'total_price' => 100000,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
        ]);

        $this->payment = Payment::query()->create([
            'payment_code' => 'PMADMINPAY01',
            'booking_id' => $this->booking->id,
            'amount' => 100000,
            'wallet_amount' => 0,
            'gateway_amount' => 100000,
            'payment_kind' => 'full',
            'method' => 'sepay',
            'status' => 'pending',
        ]);

        PaymentLog::query()->create([
            'payment_id' => $this->payment->id,
            'event_type' => 'test_payment_created',
            'request_payload' => ['source' => 'test'],
            'status_before' => null,
            'status_after' => 'pending',
        ]);
    }

    public function test_finance_operator_can_list_payment_attempts_and_view_logs(): void
    {
        $list = $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/payments?keyword=BKADMINPAY');

        $list->assertOk()
            ->assertJsonPath('data.0.payment_code', 'PMADMINPAY01')
            ->assertJsonPath('data.0.booking.booking_code', 'BKADMINPAY')
            ->assertJsonPath('data.0.customer.username', 'payment_customer')
            ->assertJsonPath('data.0.venue_cluster.name', 'Cụm sân thanh toán')
            ->assertJsonPath('data.0.payment_kind', 'full')
            ->assertJsonPath('data.0.method', 'sepay')
            ->assertJsonPath('data.0.logs_count', 1)
            ->assertJsonPath('summary.total', 1);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson("/api/admin/payments/{$this->payment->id}")
            ->assertOk()
            ->assertJsonPath('data.logs.0.event_type', 'test_payment_created');
    }

    public function test_payment_list_supports_detailed_filters(): void
    {
        $this->payment->update([
            'amount' => 75000,
            'status' => 'paid',
            'paid_at' => '2026-06-05 10:00:00',
        ]);
        $this->booking->update(['status' => 'confirmed']);

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/payments?booking_status=confirmed&venue_cluster_id=' . $this->cluster->id . '&customer_id=' . $this->customer->id . '&amount_min=70000&amount_max=80000&paid_from=2026-06-05&paid_to=2026-06-05')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.0.payment_code', 'PMADMINPAY01');

        $this->actingAs($this->finance, 'sanctum')
            ->getJson('/api/admin/payments?amount_min=80001')
            ->assertOk()
            ->assertJsonPath('summary.total', 0);
    }

    public function test_finance_operator_can_login_to_admin_area(): void
    {
        $this->postJson('/api/admin/auth/login', [
            'login' => $this->finance->username,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('role_group', 'admin')
            ->assertJsonPath('redirect_to', '/admin/dashboard');
    }

    public function test_retry_creates_a_new_attempt_and_marks_pending_attempt_failed(): void
    {
        $this->payment->update([
            'method' => 'mixed',
            'wallet_amount' => 25000,
            'gateway_amount' => 75000,
        ]);

        $response = $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/retry", [
                'source' => 'admin',
                'reason' => 'Khởi tạo lại giao dịch sau lỗi gateway.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.booking_id', $this->booking->id);

        $newPaymentId = $response->json('data.id');

        $this->assertNotSame($this->payment->id, $newPaymentId);
        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $newPaymentId,
            'status' => 'pending',
            'method' => 'mixed',
            'wallet_amount' => 25000,
            'gateway_amount' => 75000,
        ]);
        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $this->payment->id,
            'event_type' => 'admin_payment_retry_replaced',
        ]);
        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $newPaymentId,
            'event_type' => 'admin_payment_retry_created',
        ]);
    }

    public function test_admin_paid_status_confirms_booking_and_credits_owner_wallet(): void
    {
        $response = $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/payments/{$this->payment->id}/status", [
                'status' => 'paid',
                'source' => 'mock',
                'reason' => 'Mock giao dịch thành công để đối soát.',
                'gateway_txn_id' => 'MOCK-ADMIN-PAY-01',
                'gateway_response' => ['result' => 'success'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.gateway_txn_id', 'MOCK-ADMIN-PAY-01');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('owner_wallets', [
            'owner_id' => $this->owner->id,
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);
        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'payment_id' => $this->payment->id,
            'type' => 'credit',
            'direction' => 'credit',
            'amount' => 100000,
        ]);
        $this->assertDatabaseHas('payment_logs', [
            'payment_id' => $this->payment->id,
            'event_type' => 'admin_payment_status_updated',
            'status_before' => 'pending',
            'status_after' => 'paid',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->finance->id,
            'action' => 'payment.status_updated',
            'entity_id' => $this->payment->id,
        ]);
    }

    public function test_admin_can_record_pay_later_payment_without_reverting_completed_booking(): void
    {
        $this->booking->update([
            'source' => 'counter',
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'status' => 'completed',
        ]);

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/payments/{$this->payment->id}/status", [
                'status' => 'paid',
                'source' => 'admin',
                'reason' => 'Đối soát khoản thu sau khi khách đã chơi.',
                'gateway_txn_id' => 'POSTPLAY-ADMIN-PAY-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => 'completed',
        ]);
    }

    public function test_admin_cannot_mark_payment_refunded_directly(): void
    {
        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/payments/{$this->payment->id}/status", [
                'status' => 'paid',
                'source' => 'mock',
                'reason' => 'Thanh toán thành công.',
                'gateway_txn_id' => 'MOCK-ADMIN-PAY-02',
            ])
            ->assertOk();

        $this->actingAs($this->finance, 'sanctum')
            ->patchJson("/api/admin/payments/{$this->payment->id}/status", [
                'status' => 'refunded',
                'source' => 'admin',
                'reason' => 'Đã hoàn tiền cho khách.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('owner_wallets', [
            'owner_id' => $this->owner->id,
            'available_balance' => 100000,
        ]);
        $this->assertDatabaseMissing('refunds', ['payment_id' => $this->payment->id]);
    }

    public function test_retry_is_rejected_when_booking_is_no_longer_waiting_for_payment(): void
    {
        $this->booking->update(['status' => 'cancelled']);

        $this->actingAs($this->finance, 'sanctum')
            ->postJson("/api/admin/payments/{$this->payment->id}/retry", [
                'source' => 'admin',
                'reason' => 'Không được tạo attempt cho booking đã hủy.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Chỉ được retry khi booking vẫn đang chờ thanh toán.');

        $this->assertSame(1, Payment::query()->where('booking_id', $this->booking->id)->count());
    }

    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $email,
            'phone' => '09' . random_int(10000000, 99999999),
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
