<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SlotLock;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SepayPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private User $owner;
    private VenueCluster $cluster;
    private VenueCourt $court;
    private SystemBankAccount $systemBankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sepay.webhook_api_key', null);

        $userRole = Role::create(['name' => 'user', 'display_name' => 'User', 'is_system' => true]);
        $ownerRole = Role::create(['name' => 'venue_owner', 'display_name' => 'Owner', 'is_system' => true]);

        $this->player = User::create([
            'username' => 'sepay_player',
            'full_name' => 'Sepay Player',
            'email' => 'sepay.player@sportgo.vn',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::create([
            'user_id' => $this->player->id,
            'role_id' => $userRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->owner = User::create([
            'username' => 'sepay_owner',
            'full_name' => 'Sepay Owner',
            'email' => 'sepay.owner@sportgo.vn',
            'phone' => '0900000002',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $courtType = CourtType::create([
            'name' => 'Badminton',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::create([
            'owner_id' => $this->owner->id,
            'name' => 'Sepay Cluster',
            'slug' => 'sepay-cluster',
            'address' => 'Ha Noi',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        BookingConfig::create([
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 180,
            'slot_hold_minutes' => 20,
            'allow_full_payment' => true,
            'allow_deposit' => true,
            'allow_no_prepay' => true,
            'deposit_percent' => 30.00,
        ]);

        $this->court = VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $courtType->id,
            'name' => 'Sân SePay',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->systemBankAccount = SystemBankAccount::create([
            'name' => 'SportGo Default',
            'bank_name' => 'MBBank',
            'bank_code' => 'MBBank',
            'account_number' => '1234567890',
            'account_holder_name' => 'CONG TY SPORTGO',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_player_can_create_sepay_payment_to_system_bank_account(): void
    {
        $booking = $this->createPendingPaymentBooking();

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/sepay");

        $response->assertStatus(200)
            ->assertJsonPath('payment.method', 'sepay')
            ->assertJsonPath('payment.system_bank_account_id', $this->systemBankAccount->id)
            ->assertJsonPath('payment_account.account_number', '1234567890')
            ->assertJsonPath('transfer_content', $response->json('payment.payment_code'));

        $this->assertStringContainsString('https://qr.sepay.vn/img?', $response->json('qr_url'));
        $this->assertStringContainsString('acc=1234567890', $response->json('qr_url'));
    }

    public function test_sepay_webhook_confirms_booking_and_credits_owner_wallet(): void
    {
        $booking = $this->createPendingPaymentBooking();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/sepay")
            ->assertStatus(200);

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

        $ipn = $this->postJson('/api/sepay/ipn', [
            'id' => 987654,
            'gateway' => 'MBBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '1234567890',
            'code' => $payment->payment_code,
            'content' => $payment->payment_code.' thanh toan dat san',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'FT123456',
        ]);

        $ipn->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('processed', true);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'gateway_txn_id' => '987654',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('owner_wallets', [
            'owner_id' => $this->owner->id,
            'available_balance' => 100000.00,
            'total_earned' => 100000.00,
        ]);

        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();

        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'owner_wallet_id' => $wallet->id,
            'owner_id' => $this->owner->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'type' => 'credit',
            'amount' => 100000.00,
        ]);

        $this->assertDatabaseMissing('slot_locks', [
            'booking_id' => $booking->id,
        ]);
    }

    public function test_duplicate_sepay_webhook_does_not_credit_owner_wallet_twice(): void
    {
        $booking = $this->createPendingPaymentBooking();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/sepay")
            ->assertStatus(200);

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();
        $payload = [
            'id' => 987654,
            'gateway' => 'MBBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '1234567890',
            'code' => $payment->payment_code,
            'content' => $payment->payment_code.' thanh toan dat san',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'FT123456',
        ];

        $this->postJson('/api/sepay/ipn', $payload)->assertJsonPath('processed', true);
        $this->postJson('/api/sepay/ipn', $payload)->assertJsonPath('processed', true);

        $this->assertDatabaseHas('owner_wallets', [
            'owner_id' => $this->owner->id,
            'available_balance' => 100000.00,
            'total_earned' => 100000.00,
        ]);

        $this->assertEquals(
            1,
            OwnerWalletLedger::query()
                ->where('payment_id', $payment->id)
                ->where('type', 'credit')
                ->count(),
        );
    }

    public function test_direct_payment_booking_does_not_create_system_payment_or_owner_balance(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BKDIRECT',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/payments/sepay")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Đơn đặt sân này không ở trạng thái chờ thanh toán.');

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
        ]);

        $this->assertDatabaseMissing('owner_wallets', [
            'owner_id' => $this->owner->id,
        ]);
    }

    private function createPendingPaymentBooking(): Booking
    {
        $booking = Booking::create([
            'booking_code' => 'BKSEPAY',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_payment',
        ]);

        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->player->id,
            'booking_id' => $booking->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->addMinutes(20),
        ]);

        return $booking;
    }
}
