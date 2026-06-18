<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerBookingConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private VenueCluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->owner = $this->createOwner('booking_config_owner');
        $this->otherOwner = $this->createOwner('booking_config_other');
        $this->assignRole($this->owner, $role);
        $this->assignRole($this->otherOwner, $role);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân cấu hình booking',
            'slug' => 'cum-san-cau-hinh-booking',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);
    }

    public function test_owner_can_view_and_update_complete_booking_config(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/owner/booking-configs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->cluster->id)
            ->assertJsonPath('data.0.booking_config.slot_hold_minutes', 20);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'slot_hold_minutes' => 25,
                'reminder_before_minutes' => 60,
                'allow_full_payment' => true,
                'allow_deposit' => true,
                'allow_no_prepay' => false,
                'deposit_percent' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.min_duration_minutes', 60)
            ->assertJsonPath('data.deposit_percent', '40.00');

        $this->assertDatabaseHas('booking_configs', [
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
            'slot_hold_minutes' => 25,
            'reminder_before_minutes' => 60,
            'allow_no_prepay' => false,
            'deposit_percent' => 40,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'booking_config.updated',
            'entity_id' => $this->cluster->id,
        ]);
    }

    public function test_duration_must_follow_30_minute_steps(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'min_duration_minutes' => 45,
                'max_duration_minutes' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['min_duration_minutes', 'max_duration_minutes']);
    }

    public function test_booking_config_rejects_excessive_duration_and_invalid_time_steps(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'max_duration_minutes' => 1470,
                'slot_hold_minutes' => 7,
                'reminder_before_minutes' => 17,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'max_duration_minutes',
                'slot_hold_minutes',
                'reminder_before_minutes',
            ]);
    }

    public function test_at_least_one_payment_method_and_valid_deposit_are_required(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'allow_full_payment' => false,
                'allow_deposit' => false,
                'allow_no_prepay' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_methods');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'deposit_percent' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deposit_percent');
    }

    public function test_owner_cannot_update_another_owner_cluster(): void
    {
        $this->actingAs($this->otherOwner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, $this->validPayload())
            ->assertForbidden();
    }

    private function validPayload(): array
    {
        return [
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 180,
            'slot_hold_minutes' => 20,
            'reminder_before_minutes' => 30,
            'allow_full_payment' => true,
            'allow_deposit' => true,
            'allow_no_prepay' => true,
            'deposit_percent' => 30,
        ];
    }

    private function createOwner(string $username): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $username.'@sportgo.test',
            'phone' => '09'.random_int(10000000, 99999999),
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
