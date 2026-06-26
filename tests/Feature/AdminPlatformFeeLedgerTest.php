<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\PlatformFeeTier;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPlatformFeeLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private VenueCluster $cluster;
    private PlatformFeeTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'finance_operator',
            'display_name' => 'Tài chính',
            'is_system' => true,
        ]);

        $ownerRole = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->admin = User::factory()->create([
            'username' => 'ledger_admin',
            'email' => 'ledger.admin@sportgo.test',
        ]);
        $this->owner = User::factory()->create([
            'username' => 'ledger_owner',
            'email' => 'ledger.owner@sportgo.test',
        ]);

        UserRole::query()->create(['user_id' => $this->admin->id, 'role_id' => $adminRole->id]);
        UserRole::query()->create(['user_id' => $this->owner->id, 'role_id' => $ownerRole->id]);

        $courtType = CourtType::query()->create([
            'name' => 'Cầu lông',
            'description' => 'Sân test',
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân phí nền tảng',
            'slug' => 'cum-san-phi-nen-tang',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $courtType->id,
            'name' => 'Sân A1',
            'status' => 'active',
        ]);
        VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $courtType->id,
            'name' => 'Sân A2',
            'status' => 'active',
        ]);

        $this->tier = PlatformFeeTier::query()->create([
            'name' => 'Bậc 1',
            'min_courts' => 1,
            'max_courts' => 5,
            'price_per_court_month' => 100000,
            'annual_discount_percent' => 10,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);
    }

    public function test_admin_can_preview_create_list_and_pay_platform_fee_ledger_from_database(): void
    {
        $preview = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers/preview', [
                'venue_cluster_id' => $this->cluster->id,
                'period_months' => 1,
                'period_start' => '2026-06-01',
            ]);

        $preview->assertOk()
            ->assertJsonPath('isValid', true)
            ->assertJsonPath('court_count', 2)
            ->assertJsonPath('fee.amount_due', 200000);

        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers', [
                'venue_cluster_id' => $this->cluster->id,
                'period_months' => 1,
                'period_start' => '2026-06-01',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.venue.id', $this->cluster->id)
            ->assertJsonPath('data.amount_due', 200000)
            ->assertJsonPath('data.status', 'pending');

        $ledgerId = $create->json('data.id');

        $list = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/platform-fee-ledgers?keyword=Cụm sân phí');

        $list->assertOk()
            ->assertJsonPath('0.id', $ledgerId)
            ->assertJsonPath('0.owner.id', $this->owner->id);

        $pay = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/platform-fee-ledgers/{$ledgerId}/pay", [
                'amount' => 200000,
            ]);

        $pay->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount_paid', 200000);

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $ledgerId,
            'status' => 'paid',
            'amount_paid' => 200000,
        ]);
    }

    public function test_admin_cannot_create_overlapping_platform_fee_ledger(): void
    {
        VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'due_date' => '2026-06-30',
            'price_per_court_month' => 100000,
            'discount_percent' => 0,
            'amount_due' => 200000,
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers', [
                'venue_cluster_id' => $this->cluster->id,
                'period_months' => 1,
                'period_start' => '2026-06-15',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Đã có kỳ phí trùng thời gian cho cụm sân này.');
    }

    public function test_admin_can_send_platform_fee_reminder_and_store_email_log(): void
    {
        Mail::fake();

        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'due_date' => '2026-06-30',
            'price_per_court_month' => 100000,
            'discount_percent' => 0,
            'amount_due' => 200000,
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/platform-fee-ledgers/{$ledger->id}/reminders", [
                'type' => 'manual',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ledger_id', $ledger->id)
            ->assertJsonPath('data.email', $this->owner->email)
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('platform_fee_email_logs', [
            'ledger_id' => $ledger->id,
            'type' => 'manual',
            'email' => $this->owner->email,
            'status' => 'sent',
        ]);
    }
}
