<?php

namespace Tests\Feature;

use App\Models\BookingConfig;
use App\Models\CourtType;
use App\Models\HolidayPrice;
use App\Models\PriceSlot;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueBasePrice;
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

    public function test_pricing_duration_endpoint_cannot_bypass_booking_rules(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/booking-configs/{$this->cluster->id}/duration", [
                'min_duration_minutes' => 45,
                'max_duration_minutes' => 1470,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['min_duration_minutes', 'max_duration_minutes']);

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/booking-configs/{$this->cluster->id}/duration", [
                'min_duration_minutes' => 150,
                'max_duration_minutes' => 180,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_duration_minutes');
    }

    public function test_owner_can_load_pricing_configuration(): void
    {
        PriceSlot::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'apply_to_days' => [1, 2, 3, 4, 5],
            'start_time' => '06:00:00',
            'end_time' => '17:00:00',
            'booking_type' => 'all',
            'price' => 80000,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/pricing')
            ->assertOk()
            ->assertJsonPath('clusters.0.id', $this->cluster->id)
            ->assertJsonPath("court_types_by_cluster.{$this->cluster->id}.0.id", $this->courtType->id)
            ->assertJsonPath('system_default_price', 10000)
            ->assertJsonCount(0, 'base_prices')
            ->assertJsonPath('price_slots.0.price', '80000.00')
            ->assertJsonCount(0, 'holiday_prices');
    }

    public function test_owner_can_set_base_price_for_a_court_type(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/base-prices/{$this->courtType->id}", [
                'venue_cluster_id' => $this->cluster->id,
                'price' => 75000,
            ])
            ->assertOk()
            ->assertJsonPath('price', '75000.00')
            ->assertJsonPath('court_type.id', $this->courtType->id);

        $this->assertDatabaseHas('venue_base_prices', [
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'price' => 75000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'pricing.base_price_updated',
            'entity_type' => VenueBasePrice::class,
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
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'pricing.weekly_created',
        ]);
    }

    public function test_all_booking_price_conflicts_with_specific_booking_price(): void
    {
        PriceSlot::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'apply_to_days' => [6, 7],
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'booking_type' => 'all',
            'price' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/price-slots', [
                'venue_cluster_id' => $this->cluster->id,
                'court_type_id' => $this->courtType->id,
                'apply_to_days' => [7],
                'start_time' => '10:00',
                'end_time' => '13:00',
                'booking_type' => 'single',
                'price' => 120000,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_owner_can_manage_special_date_prices_with_overlap_validation_and_audit(): void
    {
        $payload = [
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $this->courtType->id,
            'date_type' => 'holiday',
            'holiday_date' => today()->addMonth()->toDateString(),
            'start_time' => '06:00',
            'end_time' => '12:00',
            'booking_type' => 'all',
            'price' => 150000,
            'note' => 'Giá ngày lễ.',
            'is_active' => true,
        ];

        $created = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/holiday-prices', $payload)
            ->assertCreated()
            ->assertJsonPath('price', '150000.00')
            ->assertJsonPath('note', 'Giá ngày lễ.');

        $id = $created->json('id');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/holiday-prices', [
                ...$payload,
                'start_time' => '11:30',
                'end_time' => '15:00',
                'booking_type' => 'single',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/owner/holiday-prices/{$id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.special_created',
            'entity_id' => $id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.special_toggled',
            'entity_id' => $id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/owner/holiday-prices/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('holiday_prices', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.special_deleted',
            'entity_id' => $id,
        ]);
        $this->assertSame(0, HolidayPrice::query()->count());
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
