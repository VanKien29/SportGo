<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\CourtType;
use App\Models\PlatformFeeTier;
use App\Models\VenuePlatformFeeLedger;
use App\Models\InternalReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AdminPlatformFeeLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private Role $adminRole;
    private VenueCluster $cluster;
    private PlatformFeeTier $tier;
    private CourtType $courtType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin
        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('admin_user', 'admin@sportgo.vn');
        $this->assignRole($this->admin, $this->adminRole);

        // Create Owner
        $this->owner = $this->createUser('owner_user', 'owner@sportgo.vn');

        // Create Venue Cluster
        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân Thể Thao GP',
            'slug' => 'cum-san-the-thao-gp',
            'phone_contact' => '0912345678',
            'address' => 'Hà Nội',
            'latitude' => 21.028511,
            'longitude' => 105.804817,
            'status' => 'active',
        ]);

        // Create Court Type
        $this->courtType = CourtType::query()->create([
            'name' => 'Sân 5 người',
            'player_count' => 10,
            'is_active' => true,
        ]);

        // Create Venue Courts (2 courts)
        VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân A',
            'status' => 'active',
        ]);
        VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân B',
            'status' => 'active',
        ]);

        // Create Platform Fee Tier (Min 1, Max 5, Price 100,000, Annual discount 10%)
        $this->tier = PlatformFeeTier::query()->create([
            'name' => 'Bậc Đồng',
            'min_courts' => 1,
            'max_courts' => 5,
            'price_per_court_month' => 100000.00,
            'annual_discount_percent' => 10.00,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_ledgers_and_metrics(): void
    {
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => Carbon::today(),
            'period_end' => Carbon::today()->addMonth()->subDay(),
            'due_date' => Carbon::today()->addMonth(),
            'price_per_court_month' => 100000.00,
            'discount_percent' => 0.00,
            'amount_due' => 200000.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
        ]);

        // View list
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/platform-fee-ledgers');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.venue_cluster_id', $this->cluster->id)
            ->assertJsonPath('data.0.status', 'pending');

        // View metrics
        $responseMetrics = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/platform-fee-ledgers/metrics');

        $responseMetrics->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.pending_amount', 200000);

        // View detail
        $responseDetail = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/platform-fee-ledgers/{$ledger->id}");

        $responseDetail->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $ledger->id);
    }

    public function test_admin_can_preview_platform_fee_calculation(): void
    {
        $payload = [
            'venue_cluster_id' => $this->cluster->id,
            'period_months' => 12, // Annual
            'period_start' => Carbon::today()->toDateString(),
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers/preview', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.isValid', true)
            ->assertJsonPath('data.court_count', 2)
            ->assertJsonPath('data.tier.id', $this->tier->id)
            // 2 courts * 100,000 * 12 months = 2,400,000 base. 10% discount = 240,000. Total = 2,160,000
            ->assertJsonPath('data.fee.base_amount', 2400000)
            ->assertJsonPath('data.fee.discount_percent', 10)
            ->assertJsonPath('data.fee.discount_amount', 240000)
            ->assertJsonPath('data.fee.amount_due', 2160000);
    }

    public function test_admin_can_store_platform_fee_ledger_and_prevent_overlap(): void
    {
        $payload = [
            'venue_cluster_id' => $this->cluster->id,
            'period_months' => 1,
            'period_start' => Carbon::today()->toDateString(),
        ];

        // First creation should succeed
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'venue_cluster_id' => $this->cluster->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'amount_due' => 200000.00,
            'status' => 'pending',
        ]);

        // Creating second ledger overlapping the same time period should fail
        $responseOverlap = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers', $payload);

        $responseOverlap->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Đã có kỳ phí trùng thời gian cho cụm sân này.');
    }

    public function test_admin_can_confirm_payment_and_unlock_venue(): void
    {
        // Pre-lock the venue cluster due to platform fee
        $this->cluster->update([
            'status' => 'locked',
            'status_reason' => 'Khóa do nợ phí duy trì hệ thống',
            'locked_at' => now(),
        ]);

        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => Carbon::today(),
            'period_end' => Carbon::today()->addMonth()->subDay(),
            'due_date' => Carbon::today()->addMonth(),
            'price_per_court_month' => 100000.00,
            'discount_percent' => 0.00,
            'amount_due' => 200000.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
            'payment_proof_status' => 'submitted',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/platform-fee-ledgers/{$ledger->id}/confirm-payment");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_proof_status', 'approved');

        // Verify ledger status
        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $ledger->id,
            'status' => 'paid',
            'amount_paid' => 200000.00,
            'payment_proof_status' => 'approved',
        ]);

        // Verify receipt created
        $ledgerFresh = $ledger->fresh();
        $this->assertNotNull($ledgerFresh->internal_receipt_id);
        $this->assertDatabaseHas('internal_receipts', [
            'id' => $ledgerFresh->internal_receipt_id,
            'receipt_type' => 'platform_fee',
            'amount' => 200000.00,
        ]);

        // Verify cluster unlocked
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster->id,
            'status' => 'active',
            'status_reason' => null,
        ]);
    }

    public function test_admin_can_reject_payment_proof_with_reason(): void
    {
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => Carbon::today(),
            'period_end' => Carbon::today()->addMonth()->subDay(),
            'due_date' => Carbon::today()->addMonth(),
            'price_per_court_month' => 100000.00,
            'discount_percent' => 0.00,
            'amount_due' => 200000.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
            'payment_proof_status' => 'submitted',
        ]);

        // Missing reason
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/platform-fee-ledgers/{$ledger->id}/reject-payment")
            ->assertStatus(422);

        // Valid rejection
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/platform-fee-ledgers/{$ledger->id}/reject-payment", [
                'reason' => 'Ảnh bằng chứng mờ, không rõ giao dịch.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.payment_proof_status', 'rejected')
            ->assertJsonPath('data.payment_reject_reason', 'Ảnh bằng chứng mờ, không rõ giao dịch.');

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $ledger->id,
            'status' => 'pending', // Status remains pending
            'payment_proof_status' => 'rejected',
            'payment_reject_reason' => 'Ảnh bằng chứng mờ, không rõ giao dịch.',
        ]);
    }

    public function test_admin_can_mark_overdue_and_lock_venue(): void
    {
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => Carbon::today(),
            'period_end' => Carbon::today()->addMonth()->subDay(),
            'due_date' => Carbon::today()->addMonth(),
            'price_per_court_month' => 100000.00,
            'discount_percent' => 0.00,
            'amount_due' => 200000.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/platform-fee-ledgers/{$ledger->id}/mark-overdue", [
                'reason' => 'Khóa tự động do nợ phí.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'overdue');

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $ledger->id,
            'status' => 'overdue',
        ]);

        // Verify cluster locked
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster->id,
            'status' => 'locked',
            'status_reason' => 'Khóa tự động do nợ phí.',
        ]);
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
