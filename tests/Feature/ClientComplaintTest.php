<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use App\Models\VenueCluster;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClientComplaintTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private VenueCluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = $this->createUser('complaint_owner');
        $this->player = $this->createUser('complaint_player');
        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Complaint Test Venue',
            'slug' => 'complaint-test-venue-' . $owner->id,
            'address' => 'Ha Noi',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);
    }

    public function test_client_can_submit_only_during_an_active_booking(): void
    {
        $booking = $this->createBooking('confirmed', -10, 50);

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/complaints', [
                'complaint_type' => 'venue',
                'booking_id' => $booking->id,
                'venue_cluster_id' => $this->cluster->id,
                'content' => 'Mặt sân đang có vấn đề cần được hỗ trợ ngay.',
            ], ['Idempotency-Key' => 'complaint-active-1']);

        $response->assertCreated()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.complaint_type', 'venue');

        $this->assertDatabaseHas('complaints', [
            'booking_id' => $booking->id,
            'customer_id' => $this->player->id,
            'policy_version' => 'venue-booking-v1',
        ]);
    }

    public function test_client_cannot_submit_when_booking_is_not_active(): void
    {
        $booking = $this->createBooking('confirmed', 120, 180);

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/complaints', [
                'complaint_type' => 'venue',
                'booking_id' => $booking->id,
                'venue_cluster_id' => $this->cluster->id,
                'content' => 'Booking này chưa đến giờ sử dụng sân.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'BOOKING_NOT_ACTIVE');
    }

    public function test_duplicate_booking_complaint_returns_existing_complaint_for_evidence_follow_up(): void
    {
        $booking = $this->createBooking('checked_in', -5, 55);
        $payload = [
            'complaint_type' => 'venue',
            'booking_id' => $booking->id,
            'venue_cluster_id' => $this->cluster->id,
            'content' => 'Nhân viên chưa hỗ trợ đúng khu vực đã đặt.',
        ];

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/complaints', $payload, ['Idempotency-Key' => 'complaint-duplicate-1'])
            ->assertCreated();

        $duplicate = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/complaints', [...$payload, 'content' => 'Gửi lại cùng booking để kiểm tra.'], ['Idempotency-Key' => 'complaint-duplicate-2']);

        $duplicate->assertStatus(409)
            ->assertJsonPath('code', 'DUPLICATE_COMPLAINT')
            ->assertJsonPath('existing_complaint_id', Complaint::query()->firstOrFail()->id);

        $reply = $this->actingAs($this->player, 'sanctum')
            ->post('/api/complaints/' . Complaint::query()->firstOrFail()->id . '/reply', [
                'content' => 'Tôi bổ sung ảnh hiện trường để bộ phận xử lý đối chiếu.',
                'evidence_images' => [UploadedFile::fake()->image('venue-proof.jpg')],
            ]);

        $reply->assertCreated();
        $this->assertSame(1, ComplaintReply::query()->firstOrFail()->evidence()->count());
    }

    public function test_eligible_booking_endpoint_returns_only_current_booking_window(): void
    {
        $active = $this->createBooking('confirmed', -10, 50, 'ELIGIBLE-ACTIVE');
        $this->createBooking('confirmed', 120, 180, 'ELIGIBLE-FUTURE');
        $this->createBooking('cancelled', -10, 50, 'ELIGIBLE-CANCELLED');

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/complaints/eligible-bookings');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    private function createBooking(string $status, int $startOffset, int $endOffset, ?string $code = null): Booking
    {
        $now = Carbon::now(config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $start = $now->copy()->addMinutes($startOffset);
        $end = $now->copy()->addMinutes($endOffset);

        return Booking::query()->create([
            'booking_code' => $code ?: 'COMPLAINT-' . uniqid(),
            'customer_id' => $this->player->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $start->toDateString(),
            'start_time' => $start->format('H:i:00'),
            'end_time' => $end->format('H:i:00'),
            'duration_minutes' => max(30, $start->diffInMinutes($end)),
            'total_price' => 100000,
            'required_payment_amount' => 0,
            'payment_option' => 'no_prepay',
            'source' => 'online',
            'booking_type' => 'single',
            'status' => $status,
            'created_by' => $this->player->id,
        ]);
    }

    private function createUser(string $prefix): User
    {
        return User::query()->create([
            'username' => $prefix . '_' . uniqid(),
            'full_name' => $prefix,
            'email' => $prefix . '_' . uniqid() . '@sportgo.test',
            'phone' => '09' . random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
