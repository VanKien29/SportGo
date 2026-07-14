<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueStaffAssignment;
use App\Services\VenueStaffAccessService;
use App\Models\VenueStaffShift;
use App\Models\VenueStaffShiftSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $staff;
    private VenueCluster $cluster;
    private Role $ownerRole;
    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->ownerRole = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->staffRole = Role::query()->create([
            'name' => 'venue_staff',
            'display_name' => 'Nhân viên sân',
            'is_system' => true,
        ]);

        // Create users
        $this->owner = User::query()->create([
            'username' => 'owner_user',
            'full_name' => 'Owner User',
            'email' => 'owner@sportgo.vn',
            'phone' => '0987654321',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->staff = User::query()->create([
            'username' => 'staff_user',
            'full_name' => 'Staff User',
            'email' => 'staff@sportgo.vn',
            'phone' => '0912345678',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // Assign roles
        UserRole::query()->create([
            'user_id' => $this->owner->id,
            'role_id' => $this->ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // Create cluster owned by owner
        $this->cluster = VenueCluster::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_id' => $this->owner->id,
            'name' => 'Cụm Sân Test',
            'slug' => 'cum-san-test',
            'address' => '123 Test St',
            'latitude' => 21.0278,
            'longitude' => 105.8342,
            'status' => 'active',
        ]);

        // Assign staff to cluster
        UserRole::query()->create([
            'user_id' => $this->staff->id,
            'role_id' => $this->staffRole->id,
            'scope_type' => 'venue',
            'scope_id' => $this->cluster->id,
        ]);

        VenueStaffAssignment::query()->create([
            'user_id' => $this->staff->id,
            'venue_cluster_id' => $this->cluster->id,
            'scope_type' => 'all_cluster',
            'scope_key' => 'all',
            'assigned_by' => $this->owner->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_can_crud_shifts(): void
    {
        // 1. Create template shift
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/staff-shifts', [
                'venue_cluster_id' => $this->cluster->id,
                'name' => 'Ca Sáng',
                'start_time' => '06:00',
                'end_time' => '12:00',
                'description' => 'Ca làm việc buổi sáng',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Ca Sáng');

        $shiftId = $response->json('data.id');

        // 2. List template shifts
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/owner/staff-shifts?venue_cluster_id={$this->cluster->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        // 3. Update template shift
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/staff-shifts/{$shiftId}", [
                'name' => 'Ca Sáng Sớm',
                'start_time' => '05:30',
                'end_time' => '11:30',
                'description' => 'Ca làm việc sáng sớm',
                'is_active' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Ca Sáng Sớm');

        // 4. Delete template shift
        $response = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/owner/staff-shifts/{$shiftId}");

        $response->assertOk();
        $this->assertDatabaseMissing('venue_staff_shifts', ['id' => $shiftId]);
    }

    public function test_owner_can_schedule_shifts_for_staff(): void
    {
        // Setup a template shift first
        $shift = VenueStaffShift::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'name' => 'Ca Chiều',
            'start_time' => '12:00:00',
            'end_time' => '18:00:00',
            'is_active' => true,
        ]);

        // 1. Assign shift schedule
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/owner/staff-shifts/schedules', [
                'venue_cluster_id' => $this->cluster->id,
                'user_ids' => [$this->staff->id],
                'dates' => [now()->toDateString()],
                'venue_staff_shift_id' => $shift->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('venue_staff_shift_schedules', [
            'user_id' => $this->staff->id,
            'venue_cluster_id' => $this->cluster->id,
            'date' => now()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '18:00:00',
            'status' => 'scheduled',
        ]);

        $scheduleId = VenueStaffShiftSchedule::query()->first()->id;

        // 2. Update schedule status / note
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/staff-shifts/schedules/{$scheduleId}", [
                'date' => now()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '18:00',
                'status' => 'absent',
                'notes' => 'Nghỉ có phép',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'absent');

        // 3. Delete schedule
        $response = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/owner/staff-shifts/schedules/{$scheduleId}");

        $response->assertOk();
        $this->assertDatabaseMissing('venue_staff_shift_schedules', ['id' => $scheduleId]);
    }

    public function test_staff_can_view_own_schedules_and_attendance(): void
    {
        $schedule = VenueStaffShiftSchedule::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'user_id' => $this->staff->id,
            'date' => now()->toDateString(),
            'start_time' => now()->subMinutes(10)->format('H:i:s'),
            'end_time' => now()->addHours(6)->format('H:i:s'),
            'status' => 'scheduled',
        ]);

        // 1. View own schedules
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/owner/staff-shifts/my-schedules');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $schedule->id);

        // 2. Check-in (Today, within valid time window)
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/owner/staff-shifts/schedules/{$schedule->id}/check-in");

        $response->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        // 3. Check-out
        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/owner/staff-shifts/schedules/{$schedule->id}/check-out");

        $response->assertOk()
            ->assertJsonPath('data.status', 'checked_out');
    }

    public function test_staff_only_sees_assigned_court_types_and_cannot_access_other_types(): void
    {
        $assignedType = CourtType::query()->create(['name' => 'Badminton']);
        $otherType = CourtType::query()->create(['name' => 'Tennis']);

        $assignedCourt = VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $assignedType->id,
            'name' => 'Sân Badminton 1',
            'status' => 'active',
        ]);
        $otherCourt = VenueCourt::query()->create([
            'venue_cluster_id' => $this->cluster->id,
            'court_type_id' => $otherType->id,
            'name' => 'Sân Tennis 1',
            'status' => 'active',
        ]);

        VenueStaffAssignment::query()
            ->where('user_id', $this->staff->id)
            ->where('venue_cluster_id', $this->cluster->id)
            ->update([
                'scope_type' => 'court_type',
                'court_type_id' => $assignedType->id,
                'scope_key' => 'court_type:' . $assignedType->id,
            ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/owner/venue-courts?venue_cluster_id=' . $this->cluster->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedCourt->id);

        $this->expectException(ValidationException::class);

        app(VenueStaffAccessService::class)->assertCourtAccess($this->staff, $otherCourt);
    }
}
