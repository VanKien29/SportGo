<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoucherValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private VenueCluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_system' => true,
        ]);
        $ownerRole = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Owner',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('voucher_admin', 'voucher.admin@sportgo.test');
        $this->owner = $this->createUser('voucher_owner', 'voucher.owner@sportgo.test');

        $this->assignRole($this->admin, $adminRole);
        $this->assignRole($this->owner, $ownerRole);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Voucher Cluster',
            'slug' => 'voucher-cluster',
            'address' => 'Ha Noi',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);
    }

    public function test_admin_rejects_fixed_voucher_with_decimal_vnd_amount(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                ...$this->adminVoucherPayload(),
                'discount_type' => 'fixed',
                'discount_value' => 10000.50,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_admin_voucher_required_code_message_is_in_vietnamese(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                ...$this->adminVoucherPayload(),
                'code' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Vui lòng nhập mã voucher.');
    }

    public function test_admin_rejects_invalid_voucher_code_and_date_range(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                ...$this->adminVoucherPayload(),
                'code' => 'VIP CÓ DẤU',
                'valid_from' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'valid_to' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Mã voucher chỉ gồm chữ không dấu, số, dấu gạch ngang hoặc gạch dưới.')
            ->assertJsonPath('errors.valid_to.0', 'Thời gian kết thúc phải sau thời gian bắt đầu.');
    }

    public function test_admin_rejects_percent_voucher_over_one_hundred(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [
                ...$this->adminVoucherPayload(),
                'discount_type' => 'percent',
                'discount_value' => 100.01,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_value']);
    }

    public function test_owner_fixed_voucher_is_saved_as_integer_vnd_without_max_cap(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/vouchers', [
                ...$this->ownerVoucherPayload(),
                'discount_type' => 'fixed',
                'discount_value' => 25000,
                'max_discount_amount' => 10000,
                'min_order_amount' => 50000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.discount_value', 25000)
            ->assertJsonPath('data.max_discount_amount', null)
            ->assertJsonPath('data.min_order_amount', 50000)
            ->assertJsonPath('data.discount_label', '25.000 đ');

        $this->assertDatabaseHas('vouchers', [
            'code' => 'OWNERFIXED',
            'discount_type' => 'fixed',
            'discount_value' => 25000,
            'max_discount_amount' => null,
        ]);
    }

    private function adminVoucherPayload(): array
    {
        return [
            'code' => 'ADMIN' . Str::upper(Str::random(6)),
            'name' => 'Admin voucher',
            'description' => null,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_discount_amount' => 50000,
            'min_order_amount' => 0,
            'total_quantity' => 100,
            'per_user_limit' => 1,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_to' => now()->addWeek()->format('Y-m-d H:i:s'),
            'status' => 'draft',
            'scopes' => [
                ['scope_type' => 'all', 'scope_id' => null],
            ],
        ];
    }

    private function ownerVoucherPayload(): array
    {
        return [
            'venue_cluster_id' => $this->cluster->id,
            'code' => 'OWNERFIXED',
            'name' => 'Owner fixed voucher',
            'description' => null,
            'discount_type' => 'fixed',
            'discount_value' => 25000,
            'max_discount_amount' => null,
            'min_order_amount' => 0,
            'total_quantity' => 100,
            'per_user_limit' => 1,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_to' => now()->addWeek()->format('Y-m-d H:i:s'),
            'status' => 'draft',
            'scopes' => [
                ['scope_type' => 'venue_cluster', 'scope_id' => $this->cluster->id],
            ],
        ];
    }

    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
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
