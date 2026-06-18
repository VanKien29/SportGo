<?php

namespace Tests\Feature;

use App\Models\PlatformFeeTier;
use App\Models\Role;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertJsonPath('data.0.payment_reference', 'SPORTGO-PF-'.strtoupper(substr(str_replace('-', '', $this->ledger->id), 0, 12)))
            ->assertJsonPath('summary.overdue', 1)
            ->assertJsonPath('summary.outstanding_amount', 200000)
            ->assertJsonPath('payment_account.account_number', '123456789');
    }

    public function test_owner_can_submit_proof_without_marking_fee_as_paid(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/owner/platform-fees/'.$this->ledger->id.'/payment-proof', [
                'proof' => UploadedFile::fake()->image('chuyen-khoan.jpg'),
                'note' => 'Đã chuyển khoản phí tháng này.',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.payment_proof.status', 'submitted')
            ->assertJsonPath('data.effective_status', 'overdue');

        $this->assertDatabaseHas('venue_platform_fee_ledgers', [
            'id' => $this->ledger->id,
            'status' => 'pending',
            'amount_paid' => 0,
            'payment_proof_status' => 'submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'platform_fee.proof_submitted',
            'entity_id' => $this->ledger->id,
        ]);

        $path = $this->ledger->fresh()->paymentProofMedia->file_path;
        Storage::disk('public')->assertExists($path);
    }

    public function test_owner_cannot_view_or_submit_proof_for_another_owner_cluster(): void
    {
        Storage::fake('public');

        $this->actingAs($this->otherOwner, 'sanctum')
            ->getJson('/api/owner/platform-fees?venue_cluster_id='.$this->cluster->id)
            ->assertForbidden();

        $this->actingAs($this->otherOwner, 'sanctum')
            ->post('/api/owner/platform-fees/'.$this->ledger->id.'/payment-proof', [
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_paid_fee_rejects_new_owner_proof(): void
    {
        Storage::fake('public');
        $this->ledger->update([
            'status' => 'paid',
            'amount_paid' => 200000,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/owner/platform-fees/'.$this->ledger->id.'/payment-proof', [
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kỳ phí này đã hoàn tất hoặc đã hủy, không thể gửi thêm minh chứng.');
    }

    public function test_owner_cannot_confirm_fee_without_remaining_balance(): void
    {
        Storage::fake('public');
        $this->ledger->update([
            'amount_paid' => 200000,
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/owner/platform-fees/'.$this->ledger->id.'/payment-proof', [
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kỳ phí này không còn số tiền cần thanh toán.');
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
