<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner1;
    private User $owner2;
    private CourtType $courtType;
    private VenueCluster $cluster1;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo Roles
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Admin', 'is_system' => true]);
        $ownerRole = Role::create(['name' => 'venue_owner', 'display_name' => 'Owner', 'is_system' => true]);

        // 2. Tạo Users
        $this->admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@sportgo.vn',
            'phone' => '0999999991',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->admin->id,
            'role_id' => $adminRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->owner1 = User::create([
            'username' => 'owner1_test',
            'full_name' => 'Owner 1 Test',
            'email' => 'owner1@sportgo.vn',
            'phone' => '0999999992',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->owner1->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->owner2 = User::create([
            'username' => 'owner2_test',
            'full_name' => 'Owner 2 Test',
            'email' => 'owner2@sportgo.vn',
            'phone' => '0999999993',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->owner2->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // 3. Tạo Loại Sân mẫu
        $this->courtType = CourtType::create([
            'name' => 'Badminton',
            'description' => 'Sân cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        // 4. Tạo Cụm sân cho owner 1
        $this->cluster1 = VenueCluster::create([
            'owner_id' => $this->owner1->id,
            'name' => 'Owner 1 Cluster',
            'slug' => 'owner-1-cluster',
            'address' => 'Hanoi',
            'latitude' => 21.0285,
            'longitude' => 105.8542,
            'status' => 'active',
        ]);
    }

    // ==========================================
    // COURT TYPE TESTS (ADMIN ONLY)
    // ==========================================

    public function test_admin_can_crud_court_types(): void
    {
        // 1. Xem danh sách
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/court-types');
        $response->assertStatus(200);

        // 2. Tạo mới
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/court-types', [
                'name' => 'Tennis',
                'description' => 'Sân Tennis tiêu chuẩn',
                'player_count' => 4,
                'is_active' => true,
            ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('court_types', ['name' => 'Tennis']);

        $courtTypeId = $response->json('data.id');

        // 3. Cập nhật
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/court-types/{$courtTypeId}", [
                'name' => 'Tennis Double',
                'player_count' => 4,
                'is_active' => true,
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('court_types', ['id' => $courtTypeId, 'name' => 'Tennis Double']);

        // 4. Xóa mềm
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/court-types/{$courtTypeId}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('court_types', ['id' => $courtTypeId]);
    }

    public function test_owner_cannot_access_court_type_endpoints(): void
    {
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson('/api/admin/court-types', [
                'name' => 'Ping Pong',
                'player_count' => 2,
            ]);
        $response->assertStatus(403);
    }

    // ==========================================
    // VENUE CLUSTER TESTS (OWNER)
    // ==========================================

    public function test_owner_can_view_own_clusters(): void
    {
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->getJson('/api/owner/venue-clusters');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->cluster1->id);
    }

    public function test_owner_can_update_own_cluster(): void
    {
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->putJson("/api/owner/venue-clusters/{$this->cluster1->id}", [
                'name' => 'Updated Cluster Name',
                'address' => 'Danang',
                'phone_contact' => '0987654321',
                'amenities' => ['Wifi', 'Water', 'Parking'],
                'latitude' => 16.0544,
                'longitude' => 108.2022,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'name' => 'Updated Cluster Name',
            'address' => 'Danang',
        ]);
    }

    public function test_owner_cannot_update_other_owners_cluster(): void
    {
        $response = $this->actingAs($this->owner2, 'sanctum')
            ->putJson("/api/owner/venue-clusters/{$this->cluster1->id}", [
                'name' => 'Hack Cluster Name',
                'address' => 'Danang',
            ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // VENUE COURT TESTS (OWNER)
    // ==========================================

    public function test_owner_can_crud_venue_courts(): void
    {
        // 1. Thêm sân con
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson('/api/owner/venue-courts', [
                'venue_cluster_id' => $this->cluster1->id,
                'court_type_id' => $this->courtType->id,
                'name' => 'Sân số 1',
                'sort_order' => 1,
            ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('venue_courts', ['name' => 'Sân số 1']);

        $courtId = $response->json('data.id');

        // 2. Lấy danh sách sân con
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->getJson("/api/owner/venue-courts?venue_cluster_id={$this->cluster1->id}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $courtId);

        // 3. Cập nhật sân con
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->putJson("/api/owner/venue-courts/{$courtId}", [
                'name' => 'Sân số 1 VIP',
                'status' => 'inactive',
                'sort_order' => 2,
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_courts', [
            'id' => $courtId,
            'name' => 'Sân số 1 VIP',
            'status' => 'inactive',
        ]);

        // 4. Xóa mềm sân con
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->deleteJson("/api/owner/venue-courts/{$courtId}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('venue_courts', ['id' => $courtId]);
    }

    public function test_owner_cannot_add_court_to_other_owners_cluster(): void
    {
        $response = $this->actingAs($this->owner2, 'sanctum')
            ->postJson('/api/owner/venue-courts', [
                'venue_cluster_id' => $this->cluster1->id,
                'court_type_id' => $this->courtType->id,
                'name' => 'Sân hack',
            ]);

        $response->assertStatus(403);
    }
}
