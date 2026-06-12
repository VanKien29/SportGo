<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\OwnerWallet;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SystemBankAccount;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerCounterPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private VenueCluster $cluster;

    private VenueCourt $court;

    private VenueCourt $secondCourt;

    private SystemBankAccount $systemBankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sepay.webhook_api_key', null);

        $ownerRole = Role::create(['name' => 'venue_owner', 'display_name' => 'Owner', 'is_system' => true]);

        $this->owner = User::create([
            'username' => 'counter_owner',
            'full_name' => 'Counter Owner',
            'email' => 'counter.owner@sportgo.vn',
            'phone' => '0900000101',
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
            'name' => 'Counter Cluster',
            'slug' => 'counter-cluster',
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
            'name' => 'Sân tại quầy',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $this->secondCourt = VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $courtType->id,
            'name' => 'Sân tại quầy 2',
            'status' => 'active',
            'sort_order' => 2,
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

    public function test_owner_can_collect_cash_after_counter_booking_is_played(): void
    {
        $booking = $this->createPayLaterCounterBooking();

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/bookings/{$booking->id}/payments/collect", [
                'payment_method' => 'cash',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'amount' => 10000.00,
            'method' => 'cash',
            'payment_kind' => 'full',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payment_logs', [
            'event_type' => 'counter_payment_collected',
            'status_after' => 'paid',
        ]);

        $this->assertDatabaseMissing('owner_wallets', [
            'owner_id' => $this->owner->id,
        ]);
    }

    public function test_owner_can_collect_pay_later_counter_booking_by_sepay_qr(): void
    {
        $booking = $this->createPayLaterCounterBooking();

        $qr = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/bookings/{$booking->id}/payments/collect", [
                'payment_method' => 'sepay',
            ]);

        $qr->assertOk()
            ->assertJsonPath('payment_qr.payment.method', 'sepay')
            ->assertJsonPath('payment_qr.payment.amount', '10000.00')
            ->assertJsonPath('payment_qr.payment_account.account_number', '1234567890')
            ->assertJsonPath('payment_qr.transfer_content', $qr->json('payment_qr.payment.payment_code'));

        $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->postJson('/api/sepay/ipn', [
            'id' => 192837,
            'gateway' => 'MBBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '1234567890',
            'code' => $payment->payment_code,
            'content' => $payment->payment_code.' thu tien tai quay',
            'transferType' => 'in',
            'transferAmount' => 10000,
            'referenceCode' => 'COUNTERQR1',
        ])
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'gateway_txn_id' => '192837',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('owner_wallets', [
            'owner_id' => $this->owner->id,
            'available_balance' => 10000.00,
            'total_earned' => 10000.00,
        ]);

        $wallet = OwnerWallet::query()->where('owner_id', $this->owner->id)->firstOrFail();

        $this->assertDatabaseHas('owner_wallet_ledgers', [
            'owner_wallet_id' => $wallet->id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'type' => 'credit',
            'direction' => 'credit',
            'amount' => 10000.00,
        ]);
    }

    public function test_owner_cannot_manually_confirm_booking_waiting_for_transfer(): void
    {
        $booking = $this->createPayLaterCounterBooking();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/bookings/{$booking->id}/payments/collect", [
                'payment_method' => 'sepay',
            ])
            ->assertOk();

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/bookings/{$booking->id}/status", [
                'action' => 'confirm',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    public function test_counter_booking_does_not_accept_deposit_option(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
                'payment_option' => 'deposit',
                'walk_in_name' => 'Khách tại quầy',
                'walk_in_phone' => '0901234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_option']);
    }

    public function test_owner_can_create_counter_booking_with_split_time_ranges(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'time_ranges' => [
                    ['start_time' => '08:00:00', 'end_time' => '09:00:00'],
                    ['start_time' => '11:00:00', 'end_time' => '12:00:00'],
                ],
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách đặt cách quãng',
                'walk_in_phone' => '0901234567',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.start_time', '08:00:00')
            ->assertJsonPath('data.end_time', '12:00:00')
            ->assertJsonPath('data.duration_minutes', 120)
            ->assertJsonPath('data.total_price', '20000.00');

        $booking = Booking::query()->with('items')->findOrFail($response->json('data.id'));

        $this->assertCount(2, $booking->items);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'subtotal' => 10000.00,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'subtotal' => 10000.00,
            'sort_order' => 2,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách giữa giờ',
                'walk_in_phone' => '0901234568',
            ])
            ->assertCreated();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:30:00',
                'end_time' => '09:30:00',
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách trùng giờ',
                'walk_in_phone' => '0901234569',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_owner_can_create_counter_booking_with_multiple_courts(): void
    {
        $bookingDate = now()->addDay()->toDateString();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $bookingDate,
                'time_ranges' => [
                    ['venue_court_id' => $this->court->id, 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
                    ['venue_court_id' => $this->secondCourt->id, 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
                    ['venue_court_id' => $this->secondCourt->id, 'start_time' => '11:00:00', 'end_time' => '12:00:00'],
                ],
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách nhiều sân',
                'walk_in_phone' => '0901234567',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.start_time', '08:00:00')
            ->assertJsonPath('data.end_time', '12:00:00')
            ->assertJsonPath('data.duration_minutes', 180)
            ->assertJsonPath('data.total_price', '30000.00');

        $booking = Booking::query()->with('items')->findOrFail($response->json('data.id'));

        $this->assertCount(3, $booking->items);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->court->id,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->secondCourt->id,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'venue_court_id' => $this->secondCourt->id,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $bookingDate,
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách khoảng trống',
                'walk_in_phone' => '0901234568',
            ])
            ->assertCreated();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->secondCourt->id,
                'booking_date' => $bookingDate,
                'start_time' => '11:30:00',
                'end_time' => '12:30:00',
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách trùng sân hai',
                'walk_in_phone' => '0901234569',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    private function createPayLaterCounterBooking(): Booking
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/bookings/counter', [
                'venue_court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'payment_option' => 'no_prepay',
                'walk_in_name' => 'Khách tại quầy',
                'walk_in_phone' => '0901234567',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.required_payment_amount', '0.00');

        return Booking::query()->findOrFail($response->json('data.id'));
    }
}
