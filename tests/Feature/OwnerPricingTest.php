<?php

namespace Tests\Feature;

use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\PriceSlot;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private VenueCluster $cluster;
    private CourtType $courtType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'venue_owner',
            'display_name' => 'Venue Owner',
            'is_system' => true,
        ]);

        $this->owner = User::create([
            'username' => 'owner_pricing',
            'full_name' => 'Owner Pricing',
            'email' => 'owner-pricing@sportgo.vn',
            'phone' => '0888123456',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->courtType = CourtType::create([
            'name' => 'Cầu lông',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::create([
            'owner_id' => $this->owner->id,
            'name' => 'SportGo Owner Cluster',
            'slug' => 'sportgo-owner-cluster',
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
            'max_duration_minutes' => null,
        ]);

        VenueCourt::create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân cầu lông A1',
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    public function test_owner_can_update_booking_duration_config(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/booking-configs/{$this->cluster->id}/duration", [
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
            ]);

        $response->assertOk()
            ->assertJson([
                'venue_cluster_id' => $this->cluster->id,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
            ]);

        $this->assertDatabaseHas('booking_configs', [
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
        ]);
    }

    public function test_owner_can_create_price_slot_and_overlap_is_rejected(): void
    {
        $payload = [
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'apply_to_days' => [1, 2, 3, 4, 5],
            'start_time' => '06:00',
            'end_time' => '17:00',
            'booking_type' => 'all',
            'price' => 80000,
            'is_active' => true,
        ];

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/price-slots', $payload)
            ->assertCreated()
            ->assertJsonPath('price', '80000.00');

        $this->assertDatabaseHas('price_slots', [
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'start_time' => '06:00:00',
            'end_time' => '17:00:00',
            'price' => 80000,
        ]);

        $overlapResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/price-slots', [
                ...$payload,
                'start_time' => '16:00',
                'end_time' => '18:00',
            ]);

        $overlapResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);

        $this->assertSame(1, PriceSlot::count());
    }

    public function test_owner_cannot_update_duration_or_price_slots_when_cluster_is_locked(): void
    {
        // Lock the cluster
        $this->cluster->update(['status' => 'locked', 'status_reason' => 'Test lock']);

        // 1. Try to update booking duration config
        $response = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/booking-configs/{$this->cluster->id}/duration", [
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['venue_cluster_id']);

        // 2. Try to create price slot
        $payload = [
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'apply_to_days' => [1, 2, 3, 4, 5],
            'start_time' => '06:00',
            'end_time' => '17:00',
            'booking_type' => 'all',
            'price' => 80000,
            'is_active' => true,
        ];

        $createResponse = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/price-slots', $payload);

        $createResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['venue_cluster_id']);
    }
}
