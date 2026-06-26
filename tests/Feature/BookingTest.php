<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\CourtMembershipTier;
use App\Models\CourtType;
use App\Models\Role;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private VenueCourt $court;
    private VenueCluster $cluster;
    private CourtType $courtType;
    private string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingDate = now()->addWeek()->toDateString();

        // 1. Tạo vai trò người chơi
        $role = Role::create([
            'name' => 'user',
            'display_name' => 'User',
            'is_system' => true,
        ]);

        // 2. Tạo người chơi (Player)
        $this->player = User::create([
            'username' => 'player_test',
            'full_name' => 'Player Test',
            'email' => 'player@sportgo.vn',
            'phone' => '0999999999',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::query()->firstOrCreate([
            'user_id' => $this->player->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // 3. Tạo loại sân
        $this->courtType = CourtType::create([
            'name' => 'Badminton',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        // 4. Tạo cụm sân và cấu hình
        $owner = User::create([
            'username' => 'owner_test',
            'full_name' => 'Owner Test',
            'email' => 'owner@sportgo.vn',
            'phone' => '0888888888',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->cluster = VenueCluster::create([
            'owner_id' => $owner->id,
            'name' => 'SportGo Test Cluster',
            'slug' => 'sportgo-test-cluster',
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
            'deposit_percent' => 30.00,
        ]);

        // 5. Tạo sân con
        $this->court = VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân Số 1',
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    /**
     * Test API kiểm tra trống sân khi không có bất kỳ đơn nào.
     */
    public function test_check_availability_when_no_bookings(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
            ]);
    }

    public function test_check_availability_returns_membership_discount_preview(): void
    {
        CourtMembershipTier::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'tier' => 'silver',
            'discount_percent' => 10,
            'min_bookings' => 1,
            'min_spent_amount' => 1000,
        ]);

        Booking::query()->create([
            'booking_code' => 'BKMEMBERPREVIEW',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => now()->subDay()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000.00,
            'original_amount' => 100000.00,
            'final_amount' => 100000.00,
            'payment_option' => 'full_payment',
            'required_payment_amount' => 100000.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'completed',
            'created_by' => $this->player->id,
        ]);

        app(\App\Services\Memberships\VenueMembershipService::class)
            ->syncUserVenue($this->player->id, $this->cluster->id, 'booking_completed');

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
            ]));

        $response->assertOk()
            ->assertJsonPath('membership_discount.tier', 'silver')
            ->assertJsonPath('membership_discount.discount_percent', 10)
            ->assertJsonPath('price_preview.membership_discount_amount', 1000)
            ->assertJsonPath('price_preview.final_amount', 9000);
    }

    /**
     * Test API kiểm tra trống sân khi bị trùng với đơn đặt hiện hữu.
     */
    public function test_check_availability_when_overlapping_booking_exists(): void
    {
        // Tạo booking trùng giờ
        Booking::create([
            'booking_code' => 'BK123456',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->bookingDate,
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
            'duration_minutes' => 90,
            'total_price' => 150000.00,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0.00,
            'source' => 'online',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Kiểm tra khung giờ overlap hoàn toàn
        $response1 = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '08:30:00',
                'end_time' => '09:00:00',
            ]));

        $response1->assertStatus(200)->assertJson(['available' => false]);
    }

    /**
     * Test API kiểm tra trống sân khi bị trùng với Slot Lock tạm thời.
     */
    public function test_check_availability_when_slot_lock_exists(): void
    {
        // Tạo Slot Lock
        SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->bookingDate,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'locked_by' => $this->player->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/bookings/check-availability?" . http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '14:30:00',
                'end_time' => '15:30:00',
            ]));

        $response->assertStatus(200)->assertJson(['available' => false]);
    }

    public function test_manual_schedule_lock_blocks_player_booking_even_without_active_expiry(): void
    {
        $lock = SlotLock::create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->bookingDate,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'locked_by' => $this->cluster->owner_id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì sân.',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/bookings/schedule?'.http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'booking_date' => $this->bookingDate,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'venue_court_id' => $this->court->id,
                'start_time' => '08:00:00',
                'end_time' => '08:30:00',
                'is_available' => false,
                'busy_source' => 'slot_lock',
                'busy_status' => 'manual',
                'schedule_lock_id' => $lock->id,
                'lock_reason' => 'Bảo trì sân.',
            ]);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/bookings/check-availability?'.http_build_query([
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
            ]))
            ->assertOk()
            ->assertJson(['available' => false]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'payment_option' => 'no_prepay',
            ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.',
            ]);
    }

    /**
     * Test tạo đơn không trả trước (no_prepay) thành công.
     */
    public function test_create_booking_no_prepay_success(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'no_prepay',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'payment_option' => 'no_prepay',
                'required_payment_amount' => 0.00,
                'status' => 'pending_approval',
            ]);

        $this->assertDatabaseHas('bookings', [
            'venue_court_id' => $this->court->id,
            'payment_option' => 'no_prepay',
            'status' => 'pending_approval',
        ]);

        // Đơn no_prepay không được tạo slot_locks giữ chỗ
        $this->assertDatabaseMissing('slot_locks', [
            'venue_court_id' => $this->court->id,
        ]);
    }

    /**
     * Test đặt sân yêu cầu đặt cọc (deposit) thành công.
     */
    public function test_create_booking_deposit_success(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'deposit',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'payment_option' => 'deposit',
                'status' => 'pending_payment',
            ]);

        // Cần đảm bảo Required Payment Amount = 30% của tổng tiền
        $booking = Booking::first();
        $expectedAmount = $booking->total_price * 0.30;
        $this->assertEquals($expectedAmount, $booking->required_payment_amount);

        // Đơn trả trước/cọc phải tự động sinh slot lock giữ sân 20 phút
        $this->assertDatabaseHas('slot_locks', [
            'booking_id' => $booking->id,
            'lock_scope' => 'court',
        ]);
    }

    /**
     * Test đặt sân bị lỗi do trùng lịch.
     */
    public function test_create_booking_overlap_fails(): void
    {
        // Đặt trước 1 đơn
        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'payment_option' => 'no_prepay',
            ]);

        // Đặt đơn thứ 2 trùng khung giờ
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->bookingDate,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'payment_option' => 'no_prepay',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Sân đã bị đặt hoặc đang được giữ chỗ trong khung giờ này.',
            ]);
    }

    /**
     * Test command giải phóng slot lock quá hạn 20 phút.
     */
    public function test_release_expired_slot_locks_command(): void
    {
        // 1. Tạo đơn chờ thanh toán và khoá slot hết hạn
        $booking = Booking::create([
            'booking_code' => 'BKEXPIRED',
            'customer_id' => $this->player->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->bookingDate,
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
            'booking_date' => $this->bookingDate,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->player->id,
            'booking_id' => $booking->id,
            'lock_type' => 'auto',
            'expires_at' => Carbon::now()->subMinutes(1), // Đã hết hạn cách đây 1 phút
        ]);

        // 2. Chạy lệnh giải phóng slot
        $exitCode = Artisan::call('app:release-expired-slot-locks');
        $this->assertEquals(0, $exitCode);

        // 3. Kiểm tra DB: Slot lock bị xoá, booking chuyển sang expired
        $this->assertDatabaseMissing('slot_locks', [
            'booking_id' => $booking->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'expired',
            'status_reason' => 'Thanh toán quá hạn 20 phút.',
        ]);
    }

    /**
     * Test API lấy dữ liệu khởi tạo cụm sân.
     */
    public function test_booking_init_data(): void
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/bookings/init');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'clusters' => [
                    '*' => [
                        'id',
                        'name',
                        'venue_courts',
                    ]
                ]
            ]);
    }

}
