<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\User;
use App\Models\VenueBasePrice;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private VenueCluster $cluster;
    private CourtType $courtType;
    private VenueCourt $court;
    private string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingDate = now()->addWeek()->toDateString();

        $this->player = User::create([
            'username' => 'booking_player',
            'full_name' => 'Booking Player',
            'email' => 'booking-player@sportgo.vn',
            'phone' => '0999000001',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $owner = User::create([
            'username' => 'booking_owner',
            'full_name' => 'Booking Owner',
            'email' => 'booking-owner@sportgo.vn',
            'phone' => '0999000002',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->courtType = CourtType::create([
            'name' => 'Cầu lông',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::create([
            'owner_id' => $owner->id,
            'name' => 'SportGo Booking Cluster',
            'slug' => 'sportgo-booking-cluster',
            'address' => 'Ha Noi',
            'latitude' => 21.0278000,
            'longitude' => 105.8342000,
            'status' => 'active',
            'rating_avg' => 0,
            'rating_count' => 0,
        ]);

        BookingConfig::create([
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 180,
            'slot_hold_minutes' => 20,
            'allow_full_payment' => true,
            'allow_deposit' => true,
            'allow_no_prepay' => true,
            'deposit_percent' => 30,
        ]);

        $this->court = VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân cầu lông A1',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân đang bảo trì',
            'status' => 'maintenance',
            'sort_order' => 2,
        ]);

        PriceSlot::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'booking_type' => 'all',
            'start_time' => '06:00:00',
            'end_time' => '22:00:00',
            'price' => 120000,
            'apply_to_days' => [1, 2, 3, 4, 5, 6, 7],
            'is_active' => true,
        ]);
    }

    public function test_schedule_returns_30_minute_availability_and_prices(): void
    {
        Booking::create([
            'booking_code' => 'BKBUSY001',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->bookingDate,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'total_price' => 120000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/bookings/schedule?' . http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'booking_date' => $this->bookingDate,
            ]));

        $response->assertOk()
            ->assertJsonCount(48, 'time_slots')
            ->assertJsonCount(1, 'courts')
            ->assertJsonFragment([
                'venue_court_id' => $this->court->id,
                'start_time' => '08:00:00',
                'end_time' => '08:30:00',
                'is_available' => false,
                'hourly_rate' => 120000,
                'price' => 60000,
                'price_source' => 'price_slot',
            ])
            ->assertJsonFragment([
                'venue_court_id' => $this->court->id,
                'start_time' => '09:00:00',
                'end_time' => '09:30:00',
                'is_available' => true,
            ]);
    }

    public function test_price_falls_back_from_holiday_to_weekly_then_base_price(): void
    {
        PriceSlot::query()->delete();

        PriceSlot::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'booking_type' => 'all',
            'start_time' => '00:00:00',
            'end_time' => '17:00:00',
            'price' => 80000,
            'apply_to_days' => [1, 2, 3, 4, 5, 6, 7],
            'is_active' => true,
        ]);
        VenueBasePrice::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'price' => 60000,
        ]);
        HolidayPrice::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'date_type' => 'holiday',
            'booking_type' => 'all',
            'holiday_date' => $this->bookingDate,
            'start_time' => '12:00:00',
            'end_time' => '17:00:00',
            'price' => 50000,
            'note' => 'Giá ngày lễ buổi chiều',
            'is_active' => true,
        ]);

        $service = app(\App\Services\BookingService::class);

        $this->assertSame('price_slot', $service->resolveHourlyRate(
            $this->cluster->id,
            $this->courtType->id,
            $this->bookingDate,
            '11:00:00',
            '11:30:00',
        )['source']);
        $this->assertSame(80000.0, $service->resolveHourlyRate(
            $this->cluster->id,
            $this->courtType->id,
            $this->bookingDate,
            '11:00:00',
            '11:30:00',
        )['hourly_rate']);

        $this->assertSame(50000.0, $service->resolveHourlyRate(
            $this->cluster->id,
            $this->courtType->id,
            $this->bookingDate,
            '12:00:00',
            '12:30:00',
        )['hourly_rate']);
        $this->assertSame(130000.0, $service->calculateTotalPrice(
            $this->court,
            $this->bookingDate,
            '11:00:00',
            '13:00:00',
        ));

        $fallback = $service->resolveHourlyRate(
            $this->cluster->id,
            $this->courtType->id,
            $this->bookingDate,
            '17:00:00',
            '17:30:00',
        );
        $this->assertSame('base_price', $fallback['source']);
        $this->assertSame(60000.0, $fallback['hourly_rate']);
    }

    public function test_create_booking_rejects_overlapping_existing_booking(): void
    {
        Booking::create([
            'booking_code' => 'BKOVERLAP',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->bookingDate,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 120000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'payment_option' => 'no_prepay',
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.',
            ]);

        $this->assertSame(1, Booking::count());
    }
}
