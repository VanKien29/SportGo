<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CourtType;
use App\Models\Role;
use App\Models\SlotLock;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OwnerScheduleLockTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private VenueCluster $cluster;

    private VenueCourt $court;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->owner = $this->createUser('schedule_owner');
        $this->otherOwner = $this->createUser('schedule_other_owner');
        $this->assignRole($this->owner, $role);
        $this->assignRole($this->otherOwner, $role);
        $this->date = today()->addWeek()->toDateString();

        $type = CourtType::query()->create([
            'name' => 'Cầu lông',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Cụm sân khóa lịch',
            'slug' => 'cum-san-khoa-lich',
            'address' => 'Hà Nội',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        $this->court = VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $type->id,
            'name' => 'Sân A1',
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    public function test_owner_can_create_manual_lock_and_schedule_marks_slots_as_locked(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'reason' => 'Bảo trì mặt sân.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lock_type', 'manual')
            ->assertJsonPath('data.reason', 'Bảo trì mặt sân.');

        $lockId = $response->json('data.id');

        $this->assertDatabaseHas('slot_locks', [
            'id' => $lockId,
            'venue_court_id' => $this->court->id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì mặt sân.',
            'booking_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'schedule_lock.created',
            'entity_id' => $lockId,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/bookings/schedule?'.http_build_query([
                'venue_cluster_id' => $this->cluster->id,
                'booking_date' => $this->date,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'venue_court_id' => $this->court->id,
                'start_time' => '08:30:00',
                'end_time' => '09:00:00',
                'is_available' => false,
                'busy_source' => 'slot_lock',
                'busy_status' => 'manual',
                'schedule_lock_id' => $lockId,
                'lock_reason' => 'Bảo trì mặt sân.',
            ]);
    }

    public function test_owner_cannot_create_lock_overlapping_booking_or_existing_lock(): void
    {
        Booking::query()->create([
            'booking_code' => 'BKLOCKOVERLAP',
            'customer_id' => $this->owner->id,
            'venue_court_id' => $this->court->id,
            'requested_venue_court_id' => $this->court->id,
            'venue_cluster_id' => $this->cluster->id,
            'booking_date' => $this->date,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_price' => 100000,
            'payment_option' => 'no_prepay',
            'required_payment_amount' => 0,
            'source' => 'counter',
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'reason' => 'Bảo trì.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->date,
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'manual',
            'reason' => 'Nghỉ trưa.',
            'expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/schedule-locks', [
                'venue_court_id' => $this->court->id,
                'booking_date' => $this->date,
                'start_time' => '12:30:00',
                'end_time' => '13:30:00',
                'reason' => 'Sự kiện riêng.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_owner_can_only_delete_manual_lock_in_visible_cluster(): void
    {
        $manual = $this->createLock('manual', null, 'Bảo trì.');
        $auto = $this->createLock('auto', null, null);

        $this->actingAs($this->otherOwner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$manual->id)
            ->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$auto->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_lock');

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/owner/schedule-locks/'.$manual->id)
            ->assertOk()
            ->assertJsonPath('message', 'Đã mở lại khung giờ.');

        $this->assertDatabaseMissing('slot_locks', ['id' => $manual->id]);
        $this->assertDatabaseHas('slot_locks', ['id' => $auto->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'schedule_lock.deleted',
            'entity_id' => $manual->id,
        ]);
    }

    public function test_expired_lock_cleanup_does_not_delete_manual_locks(): void
    {
        $manual = SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => today()->subDay(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'manual',
            'reason' => 'Bảo trì.',
            'expires_at' => now()->subHour(),
        ]);

        $auto = SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => today(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'locked_by' => $this->owner->id,
            'lock_type' => 'auto',
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('app:release-expired-slot-locks');

        $this->assertDatabaseHas('slot_locks', ['id' => $manual->id]);
        $this->assertDatabaseMissing('slot_locks', ['id' => $auto->id]);
    }

    private function createLock(string $type, ?string $bookingId, ?string $reason): SlotLock
    {
        return SlotLock::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'venue_court_id' => $this->court->id,
            'lock_scope' => 'court',
            'booking_date' => $this->date,
            'start_time' => $type === 'manual' ? '15:00:00' : '16:00:00',
            'end_time' => $type === 'manual' ? '16:00:00' : '17:00:00',
            'locked_by' => $this->owner->id,
            'booking_id' => $bookingId,
            'lock_type' => $type,
            'reason' => $reason,
            'expires_at' => now()->addWeek(),
        ]);
    }

    private function createUser(string $username): User
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
