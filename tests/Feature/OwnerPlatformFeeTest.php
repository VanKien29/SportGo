<?php

namespace Tests\Feature;

use App\Models\PlatformFeeTier;
use App\Models\Role;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueAccessRestriction;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPlatformFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private VenueCluster $cluster;

    private VenuePlatformFeeLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->owner = $this->createOwner('fee_owner');
        $this->otherOwner = $this->createOwner('other_fee_owner');
        $this->assignRole($this->owner, $role);
        $this->assignRole($this->otherOwner, $role);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân phí nền tảng',
            'slug' => 'cum-san-phi-nen-tang',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $tier = PlatformFeeTier::query()->create([
            'name' => 'Bậc thử nghiệm',
            'min_courts' => 1,
            'max_courts' => 5,
            'price_per_court_month' => 100000,
            'annual_discount_percent' => 0,
            'is_active' => true,
        ]);

        $this->ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => today()->subMonth()->startOfMonth(),
            'period_end' => today()->subMonth()->endOfMonth(),
            'due_date' => today()->subDay(),
            'price_per_court_month' => 100000,
            'discount_percent' => 0,
            'amount_due' => 200000,
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'pending',
        ]);

        SystemBankAccount::query()->create([
            'name' => 'SportGo',
            'bank_name' => 'TPBank',
            'bank_code' => 'TPB',
            'account_number' => '123456789',
            'account_holder_name' => 'SPORTGO',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_owner_can_view_fees_and_server_calculates_overdue_status(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/platform-fees?venue_cluster_id='.$this->cluster->id)
            ->assertOk()
            ->assertJsonPath('data.0.effective_status', 'overdue')
            ->assertJsonPath('data.0.amount_remaining', 200000)
            ->assertJsonPath('data.0.payment.auto_confirm', true)
            ->assertJsonPath('summary.overdue', 1)
            ->assertJsonPath('summary.outstanding_amount', 200000)
            ->assertJsonPath('payment_account.account_number', '123456789');
    }

    public function test_owner_can_create_bank_qr_for_platform_fee(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/platform-fees/'.$this->ledger->id.'/payment')
            ->assertOk()
            ->assertJsonPath('amount', 200000)
            ->assertJsonPath('payment_account.account_number', '123456789')
            ->assertJsonPath('data.payment.auto_confirm', true);

        $ledger = $this->ledger->fresh();
        $this->assertNotNull($ledger->payment_code);
        $this->assertStringStartsWith('PF', $ledger->payment_code);
        $this->assertStringContainsString('acc=123456789', $response->json('qr_url'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'platform_fee.sepay_qr_created',
            'entity_id' => $this->ledger->id,
        ]);
    }

    public function test_sepay_webhook_auto_confirms_platform_fee_and_releases_fee_restriction(): void
    {
        config()->set('services.sepay.webhook_api_key', null);
        $this->cluster->update([
            'status' => 'locked',
            'status_reason' => 'Khóa cụm sân do nợ phí nền tảng quá hạn.',
            'locked_at' => now(),
        ]);
        $this->ledger->update(['locked_venue_at' => now()]);
        VenueAccessRestriction::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'restriction_type' => 'platform_fee_overdue',
            'access_mode' => 'limited',
            'reason' => 'Nợ phí nền tảng',
            'starts_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/platform-fees/'.$this->ledger->id.'/payment')
            ->assertOk();

        $ledger = $this->ledger->fresh();
        $this->postJson('/api/sepay/ipn', [
            'id' => 998877,
            'accountNumber' => '123456789',
            'code' => $ledger->payment_code,
            'content' => $ledger->payment_code.' PHI NEN TANG',
            'transferType' => 'in',
            'transferAmount' => 200000,
        ])
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $ledger->id,
            'status' => 'paid',
            'amount_paid' => 200000,
            'gateway_txn_id' => '998877',
        ]);
        $this->assertDatabaseHas('venue_access_restrictions', [
            'venue_cluster_id' => $this->cluster->id,
            'restriction_type' => 'platform_fee_overdue',
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_cannot_create_payment_for_another_owner_cluster(): void
    {
        $this->actingAs($this->otherOwner, 'sanctum')
            ->getJson('/api/owner/platform-fees?venue_cluster_id='.$this->cluster->id)
            ->assertForbidden();

        $this->actingAs($this->otherOwner, 'sanctum')
            ->postJson('/api/owner/platform-fees/'.$this->ledger->id.'/payment')
            ->assertForbidden();
    }

    public function test_paid_fee_rejects_new_payment_qr(): void
    {
        $this->ledger->update([
            'amount_paid' => 200000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/platform-fees/'.$this->ledger->id.'/payment')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kỳ phí này đã hoàn tất hoặc đã hủy.');
    }

    private function createOwner(string $username): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $username.'@sportgo.test',
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
