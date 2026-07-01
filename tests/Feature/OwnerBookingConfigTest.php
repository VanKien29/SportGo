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
            ->assertJsonPath('data.0.booking_config.slot_hold_minutes', 20)
            ->assertJsonPath('data.0.booking_config.min_advance_booking_minutes', 30)
            ->assertJsonPath('data.0.booking_config.fixed_open_time', '08:00')
            ->assertJsonPath('data.0.booking_config.fixed_close_time', '22:00');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 180,
                'min_advance_booking_minutes' => 90,
                'fixed_open_time' => '08:00',
                'fixed_close_time' => '22:00',
                'special_operating_hours' => [[
                    'start_date' => '2026-06-22',
                    'end_date' => '2026-06-25',
                    'open_time' => '08:00',
                    'close_time' => '24:00',
                ]],
                'slot_hold_minutes' => 25,
                'reminder_before_minutes' => 60,
                'allow_full_payment' => true,
                'allow_deposit' => true,
                'allow_no_prepay' => false,
                'deposit_percent' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.min_duration_minutes', 60)
            ->assertJsonPath('data.min_advance_booking_minutes', 90)
            ->assertJsonPath('data.deposit_percent', '40.00')
            ->assertJsonPath('data.reset_membership_progress_on_upgrade', false);

        $this->assertDatabaseHas('booking_configs', [
            'venue_cluster_id' => $this->cluster->id,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
            'min_advance_booking_minutes' => 90,
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

    public function test_minimum_duration_cannot_exceed_two_hours(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'min_duration_minutes' => 150,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_duration_minutes');
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

    public function test_operating_hours_require_valid_duration_and_non_overlapping_special_ranges(): void
    {
        $payload = $this->validPayload();
        $payload['fixed_close_time'] = '08:30';
        $payload['special_operating_hours'] = [
            [
                'start_date' => '2026-06-22',
                'end_date' => '2026-06-25',
                'open_time' => '08:00',
                'close_time' => '24:00',
            ],
            [
                'start_date' => '2026-06-25',
                'end_date' => '2026-06-27',
                'open_time' => '05:00',
                'close_time' => '20:00',
            ],
        ];

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fixed_close_time',
                'special_operating_hours.1.start_date',
            ]);
    }

    public function test_minimum_advance_booking_has_no_maximum_limit(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'min_advance_booking_minutes' => 525600,
            ])
            ->assertOk()
            ->assertJsonPath('data.min_advance_booking_minutes', 525600);
    }

    public function test_membership_tiers_reject_duplicate_upgrade_conditions(): void
    {
        $tiers = $this->validMembershipTiers();
        $tiers[1]['min_completed_bookings'] = 0;
        $tiers[1]['min_spend_amount'] = 0;

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'membership_tiers' => $tiers,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('membership_tiers');
    }

    public function test_membership_tiers_reject_lower_condition_for_higher_tier(): void
    {
        $tiers = $this->validMembershipTiers();
        $tiers[2]['min_completed_bookings'] = 4;

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/owner/booking-configs/'.$this->cluster->id, [
                ...$this->validPayload(),
                'membership_tiers' => $tiers,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('membership_tiers');
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
            'min_advance_booking_minutes' => 30,
            'fixed_open_time' => '08:00',
            'fixed_close_time' => '22:00',
            'special_operating_hours' => [],
            'slot_hold_minutes' => 20,
            'reminder_before_minutes' => 30,
            'allow_full_payment' => true,
            'allow_deposit' => true,
            'allow_no_prepay' => true,
            'deposit_percent' => 30,
        ];
    }

    private function validMembershipTiers(): array
    {
        return [
            ['tier_key' => 'standard', 'discount_percent' => 0, 'min_completed_bookings' => 0, 'min_spend_amount' => 0],
            ['tier_key' => 'silver', 'discount_percent' => 3, 'min_completed_bookings' => 5, 'min_spend_amount' => 500000],
            ['tier_key' => 'gold', 'discount_percent' => 5, 'min_completed_bookings' => 15, 'min_spend_amount' => 2000000],
            ['tier_key' => 'diamond', 'discount_percent' => 8, 'min_completed_bookings' => 30, 'min_spend_amount' => 5000000],
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
