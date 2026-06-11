<?php

namespace Tests\Feature;

use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWalletTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private OwnerWallet $wallet;
    private OwnerBankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true
        ]);

        $this->owner = User::query()->create([
            'username' => 'owner_test',
            'full_name' => 'Owner Test',
            'email' => 'owner.test@sportgo.vn',
            'phone' => '0987654321',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::query()->create([
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->wallet = OwnerWallet::query()->create([
            'owner_id' => $this->owner->id,
            'available_balance' => 200000.0,
            'pending_withdrawal_balance' => 0.0,
            'total_earned' => 200000.0,
            'total_withdrawn' => 0.0,
        ]);

        $this->bankAccount = OwnerBankAccount::query()->create([
            'owner_id' => $this->owner->id,
            'bank_name' => 'TPBank',
            'bank_code' => 'TPB',
            'account_number' => '1234567890',
            'account_holder_name' => 'OWNER TEST',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_can_get_wallet_and_bank_accounts(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/wallet');

        $response->assertOk()
            ->assertJsonStructure([
                'wallet' => [
                    'id',
                    'owner_id',
                    'available_balance',
                    'pending_withdrawal_balance',
                    'total_earned',
                    'total_withdrawn',
                ],
                'bank_accounts' => [
                    '*' => [
                        'id',
                        'bank_name',
                        'account_number',
                        'account_holder_name',
                    ]
                ]
            ]);
    }

    public function test_can_request_withdrawal(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/wallet/withdraw', [
                'amount' => 100000,
                'owner_bank_account_id' => $this->bankAccount->id,
                'owner_note' => 'Test withdrawal request',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Tạo yêu cầu rút tiền thành công. Vui lòng chờ admin phê duyệt.');

        $this->assertDatabaseHas('owner_withdrawal_requests', [
            'owner_id' => $this->owner->id,
            'owner_wallet_id' => $this->wallet->id,
            'owner_bank_account_id' => $this->bankAccount->id,
            'amount' => 100000,
            'source' => 'manual',
            'status' => 'pending',
            'owner_note' => 'Test withdrawal request',
        ]);
    }

    public function test_cannot_withdraw_more_than_effective_balance(): void
    {
        // First request: withdraw 150,000 (valid, balance is 200,000)
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/wallet/withdraw', [
                'amount' => 150000,
                'owner_bank_account_id' => $this->bankAccount->id,
            ])
            ->assertOk();

        // Second request: withdraw 100,000 (invalid, effective balance remaining is 50,000)
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/wallet/withdraw', [
                'amount' => 100000,
                'owner_bank_account_id' => $this->bankAccount->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Số dư khả dụng không đủ (sau khi trừ các yêu cầu rút tiền đang chờ duyệt khác).');
    }

    public function test_can_get_withdrawal_history(): void
    {
        // Create a couple of mock requests
        OwnerWithdrawalRequest::query()->create([
            'request_code' => 'WRTEST01',
            'source' => 'manual',
            'owner_id' => $this->owner->id,
            'owner_wallet_id' => $this->wallet->id,
            'owner_bank_account_id' => $this->bankAccount->id,
            'amount' => 50000,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/wallet/withdrawals');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'request_code',
                        'amount',
                        'status',
                        'requested_at',
                    ]
                ]
            ]);
    }
}
