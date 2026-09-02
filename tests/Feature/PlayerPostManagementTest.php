<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CourtType;
use App\Models\PlayerPost;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerPostManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $member;
    private Booking $booking;
    private PlayerPost $post;
    private VenueCourt $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::create([
            'username' => 'matchmaking_author', 'full_name' => 'Matchmaking Author',
            'email' => 'matchmaking-author@sportgo.test', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $this->member = User::create([
            'username' => 'matchmaking_member', 'full_name' => 'Matchmaking Member',
            'email' => 'matchmaking-member@sportgo.test', 'password' => bcrypt('password'), 'status' => 'active',
        ]);
        $type = CourtType::create(['name' => 'Bóng đá', 'player_count' => 10, 'is_active' => true]);
        $cluster = VenueCluster::create([
            'owner_id' => $this->author->id, 'name' => 'Test Cluster', 'slug' => 'test-matchmaking-cluster',
            'address' => 'Ha Noi', 'latitude' => 21.0, 'longitude' => 105.8, 'status' => 'active',
            'rating_avg' => 0, 'rating_count' => 0,
        ]);
        $this->court = VenueCourt::create([
            'venue_cluster_id' => $cluster->id, 'court_type_id' => $type->id,
            'name' => 'Sân A', 'status' => 'active', 'sort_order' => 1,
        ]);
        $this->booking = Booking::create([
            'booking_code' => 'BK-MATCH-001', 'customer_id' => $this->author->id,
            'venue_court_id' => $this->court->id, 'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $cluster->id, 'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '18:00:00', 'end_time' => '20:00:00', 'duration_minutes' => 120,
            'total_price' => 900000, 'payment_option' => 'full_payment', 'required_payment_amount' => 900000,
            'source' => 'online', 'booking_type' => 'single', 'status' => 'confirmed',
        ]);
        $this->post = PlayerPost::create([
            'booking_id' => $this->booking->id, 'author_id' => $this->author->id,
            'title' => 'Tìm người giao lưu', 'description' => 'Nội dung bài giao lưu ban đầu',
            'target_players' => 3, 'needed_players' => 3, 'lock_lead_minutes' => 30,
            'skill_level' => 'all', 'cost_type' => 'split', 'cost_per_player' => 225000, 'status' => 'open',
        ]);
    }

    public function test_author_can_update_configuration_without_changing_booking(): void
    {
        $response = $this->actingAs($this->author, 'sanctum')->patchJson('/api/matchmaking-posts/' . $this->post->id, [
            'target_players' => 4, 'skill_level' => 'advanced', 'cost_type' => 'custom',
            'cost_per_player' => 120000, 'lock_lead_minutes' => 45,
            'content' => 'Nội dung bài giao lưu đã được cập nhật', 'booking_id' => 999999,
        ]);

        $response->assertOk();
        $this->post->refresh();
        $this->assertSame($this->booking->id, $this->post->booking_id);
        $this->assertSame(4, $this->post->target_players);
        $this->assertSame(4, $this->post->needed_players);
        $this->assertSame('advanced', $this->post->skill_level);
        $this->assertSame('custom', $this->post->cost_type);
        $this->assertSame(120000.0, (float) $this->post->cost_per_player);
    }

    public function test_another_user_cannot_update_the_post(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/matchmaking-posts/' . $this->post->id, [
                'target_players' => 4, 'content' => 'Một nội dung hợp lệ dài hơn mười ký tự',
            ])->assertForbidden();
    }

    public function test_target_players_cannot_be_below_approved_and_equal_becomes_full(): void
    {
        DB::table('player_post_participants')->insert([
            'post_id' => $this->post->id, 'user_id' => $this->member->id, 'status' => 'approved',
            'responded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->author, 'sanctum')->patchJson('/api/matchmaking-posts/' . $this->post->id, [
            'target_players' => 0, 'content' => 'Nội dung bài giao lưu đã được cập nhật',
        ])->assertUnprocessable();

        $this->actingAs($this->author, 'sanctum')->patchJson('/api/matchmaking-posts/' . $this->post->id, [
            'target_players' => 1, 'content' => 'Nội dung bài giao lưu đã được cập nhật',
        ])->assertOk();
        $this->post->refresh();
        $this->assertSame('full', $this->post->status);
        $this->assertSame(0, $this->post->needed_players);
    }

    public function test_kick_preserves_participant_and_restores_slot(): void
    {
        DB::table('player_post_participants')->insert([
            'post_id' => $this->post->id, 'user_id' => $this->member->id, 'status' => 'approved',
            'responded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->author, 'sanctum')
            ->postJson('/api/matchmaking-posts/' . $this->post->id . '/participants/' . $this->member->id . '/remove', [
                'reason' => 'Không còn phù hợp với nhóm',
            ]);

        $response->assertOk();
        $participant = DB::table('player_post_participants')->where('post_id', $this->post->id)->where('user_id', $this->member->id)->first();
        $this->assertSame('removed_by_author', $participant->status);
        $this->assertSame('Không còn phù hợp với nhóm', $participant->removal_reason);
        $this->post->refresh();
        $this->assertSame(3, $this->post->needed_players);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->member->id, 'type' => 'matchmaking_member_removed']);
    }

    public function test_pending_participant_cannot_be_kicked(): void
    {
        DB::table('player_post_participants')->insert([
            'post_id' => $this->post->id, 'user_id' => $this->member->id, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->author, 'sanctum')
            ->postJson('/api/matchmaking-posts/' . $this->post->id . '/participants/' . $this->member->id . '/remove')
            ->assertStatus(409);
    }

    public function test_multi_court_booking_is_returned_as_one_post_with_all_courts(): void
    {
        $secondCourt = VenueCourt::create([
            'venue_cluster_id' => $this->court->venue_cluster_id, 'court_type_id' => $this->court->court_type_id,
            'name' => 'Sân B', 'status' => 'active', 'sort_order' => 2,
        ]);
        BookingItem::create([
            'booking_id' => $this->booking->id, 'venue_court_id' => $this->court->id,
            'start_time' => '18:00:00', 'end_time' => '19:00:00', 'duration_minutes' => 60,
            'unit_price' => 450000, 'subtotal' => 450000, 'status' => 'confirmed', 'sort_order' => 1,
        ]);
        BookingItem::create([
            'booking_id' => $this->booking->id, 'venue_court_id' => $secondCourt->id,
            'start_time' => '19:00:00', 'end_time' => '20:00:00', 'duration_minutes' => 60,
            'unit_price' => 450000, 'subtotal' => 450000, 'status' => 'confirmed', 'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->author, 'sanctum')
            ->getJson('/api/matchmaking-posts/' . $this->post->id . '/participants');

        $response->assertOk()
            ->assertJsonPath('post.booking_details.court_names.0', 'Sân A')
            ->assertJsonPath('post.booking_details.court_names.1', 'Sân B');
    }
}
