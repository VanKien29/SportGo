<?php

namespace Tests\Feature;

use App\Mail\PlatformFeeReminderMail;
use App\Models\CourtType;
use App\Models\PlatformFeeTier;
use App\Models\Role;
use App\Models\SystemPolicy;
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
            ->assertJsonPath('data.tier_name', $this->tier->name)
            ->assertJsonPath('data.tier_min_courts_snapshot', 1)
            ->assertJsonPath('data.tier_max_courts_snapshot', 5)
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
            'tier_name_snapshot' => $this->tier->name,
            'price_per_court_month' => 100000,
        ]);
    }

    public function test_range_based_tier_names_follow_rebalanced_court_ranges(): void
    {
        $this->tier->update([
            'name' => '1-3 sân',
            'max_courts' => 3,
        ]);

        $middleTier = PlatformFeeTier::query()->create([
            'name' => '4-7 sân',
            'min_courts' => 4,
            'max_courts' => 7,
            'price_per_court_month' => 90000,
            'annual_discount_percent' => 10,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);

        $lastTier = PlatformFeeTier::query()->create([
            'name' => 'Doanh nghiệp',
            'min_courts' => 8,
            'max_courts' => null,
            'price_per_court_month' => 80000,
            'annual_discount_percent' => 10,
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/platform-fee-tiers/'.$middleTier->id, [
                'name' => '4-7 sân',
                'min_courts' => 6,
                'price_per_court_month' => 90000,
                'annual_discount_percent' => 10,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '6-7 sân')
            ->assertJsonPath('data.min_courts', 6)
            ->assertJsonPath('data.max_courts', 7);

        $this->assertDatabaseHas('platform_fee_tiers', [
            'id' => $this->tier->id,
            'name' => '1-5 sân',
            'max_courts' => 5,
        ]);
        $this->assertDatabaseHas('platform_fee_tiers', [
            'id' => $middleTier->id,
            'name' => '6-7 sân',
            'min_courts' => 6,
            'max_courts' => 7,
        ]);
        $this->assertDatabaseHas('platform_fee_tiers', [
            'id' => $lastTier->id,
            'name' => 'Doanh nghiệp',
        ]);
    }

    public function test_platform_fee_tier_rejects_a_duplicate_minimum_court_count(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-tiers', [
                'name' => 'Bậc trùng mốc sân',
                'min_courts' => 1,
                'price_per_court_month' => 90000,
                'annual_discount_percent' => 10,
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_courts')
            ->assertJsonPath('errors.min_courts.0', 'Số sân tối thiểu đang trùng với một bậc phí khác.');
    }

    public function test_active_platform_fee_tiers_must_keep_coverage_starting_at_one_court(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/platform-fee-tiers/'.$this->tier->id, [
                'name' => $this->tier->name,
                'min_courts' => 2,
                'price_per_court_month' => 100000,
                'annual_discount_percent' => 10,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_courts')
            ->assertJsonPath('errors.min_courts.0', 'Bậc phí đang dùng đầu tiên phải bắt đầu từ 1 sân.');

        $this->assertDatabaseHas('platform_fee_tiers', [
            'id' => $this->tier->id,
            'min_courts' => 1,
            'is_active' => true,
        ]);
    }

    public function test_active_platform_fee_tier_price_must_decrease_as_court_count_increases(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-tiers', [
                'name' => 'Bậc giá không hợp lệ',
                'min_courts' => 4,
                'price_per_court_month' => 110000,
                'annual_discount_percent' => 10,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_per_court_month')
            ->assertJsonPath(
                'errors.price_per_court_month.0',
                'Giá bậc này phải thấp hơn giá của bậc ít sân hơn (100.000 đ).',
            );
    }

    public function test_platform_fee_tier_price_must_be_a_whole_vnd_amount(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-tiers', [
                'name' => 'Bậc giá thập phân',
                'min_courts' => 4,
                'price_per_court_month' => 90000.5,
                'annual_discount_percent' => 10,
                'is_active' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_per_court_month')
            ->assertJsonPath(
                'errors.price_per_court_month.0',
                'Giá theo sân mỗi tháng phải là số nguyên VND.',
            );
    }

    public function test_admin_can_cancel_only_an_unpaid_platform_fee_ledger(): void
    {
        $ledger = VenuePlatformFeeLedger::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier_id' => $this->tier->id,
            'court_count' => 2,
            'billing_cycle' => 'monthly',
            'period_months' => 1,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'due_date' => '2026-07-07',
            'price_per_court_month' => 100000,
            'discount_percent' => 0,
            'amount_due' => 200000,
            'amount_paid' => 0,
            'payment_proof_status' => 'none',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/platform-fee-ledgers/{$ledger->id}/cancel", [
                'reason' => 'Tạo nhầm kỳ phí',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Tạo nhầm kỳ phí');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'platform_fee.ledger_cancelled',
            'entity_id' => $ledger->id,
        ]);

        $ledger->refresh()->update([
            'status' => 'pending',
            'amount_paid' => 1000,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/platform-fee-ledgers/{$ledger->id}/cancel", [
                'reason' => 'Không được hủy khoản đã thu một phần',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kỳ phí đã ghi nhận thanh toán nên không được hủy.');
    }

    public function test_paid_ledger_keeps_pricing_snapshot_after_admin_updates_tier(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/platform-fee-ledgers', [
                'venue_cluster_id' => $this->cluster->id,
                'period_months' => 12,
                'period_start' => '2026-07-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount_due', 2160000)
            ->assertJsonPath('data.discount_percent', 10);

        $ledgerId = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/platform-fee-ledgers/{$ledgerId}/pay", [
                'amount' => 2160000,
            ])
            ->assertOk();

        $this->tier->update([
            'name' => 'Bậc mới',
            'price_per_court_month' => 250000,
            'annual_discount_percent' => 20,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/platform-fee-ledgers/{$ledgerId}")
            ->assertOk()
            ->assertJsonPath('tier_name', 'Bậc 1')
            ->assertJsonPath('price_per_court_month', 100000)
            ->assertJsonPath('discount_percent', 10)
            ->assertJsonPath('amount_due', 2160000)
            ->assertJsonPath('status', 'paid');
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
                'type' => 'due_soon_5_days',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ledger_id', $ledger->id)
            ->assertJsonPath('data.email', $this->owner->email)
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('platform_fee_email_logs', [
            'ledger_id' => $ledger->id,
            'type' => 'due_soon_5_days',
            'email' => $this->owner->email,
            'status' => 'sent',
        ]);

        Mail::assertSent(PlatformFeeReminderMail::class, function (PlatformFeeReminderMail $mail): bool {
            return $mail->hasTo($this->owner->email)
                && $mail->mailSubject === 'Phí duy trì sắp đến hạn'
                && str_contains($mail->mailContent, 'sẽ đến hạn sau 5 ngày');
        });
    }

    public function test_admin_can_save_valid_platform_fee_reminder_settings(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/platform-fee-settings', [
                'default_due_days' => 5,
                'lock_reason' => 'Quá hạn phí nền tảng',
            ])
            ->assertOk()
            ->assertJsonPath('data.default_due_days', 5)
            ->assertJsonPath('data.lock_reason', 'Quá hạn phí nền tảng');

        $policy = SystemPolicy::query()
            ->where('key', 'platform_fee_settings')
            ->where('version', 1)
            ->firstOrFail();

        $this->assertSame('active', $policy->status);
        $this->assertTrue($policy->is_active);
        $this->assertSame(5, json_decode($policy->content, true)['default_due_days']);
    }

    public function test_platform_fee_reminder_settings_reject_invalid_values(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/platform-fee-settings', [
                'default_due_days' => 31,
                'lock_reason' => 'x',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'default_due_days',
                'lock_reason',
            ]);
    }
}
