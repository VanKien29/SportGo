<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        // 5. Tạo Tiện ích mẫu để đồng bộ
        \App\Models\Amenity::create(['name' => 'Wifi', 'status' => 'active']);
        \App\Models\Amenity::create(['name' => 'Water', 'status' => 'active']);
        \App\Models\Amenity::create(['name' => 'Parking', 'status' => 'active']);
        \App\Models\Amenity::create(['name' => 'Gửi xe', 'status' => 'active']);
        \App\Models\Amenity::create(['name' => 'Phòng tắm VIP', 'status' => 'active']);
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
                'province' => 'Danang Province',
                'ward' => 'Danang Ward',
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

    // ==========================================
    // ADMIN VENUE CLUSTER MANAGEMENT TESTS
    // ==========================================

    public function test_admin_can_view_venue_clusters_list_with_fee_status(): void
    {
        // Tạo hóa đơn phí mẫu cho cluster1
        \App\Models\VenuePlatformFeeLedger::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'venue_cluster_id' => $this->cluster1->id,
            'court_count' => 5,
            'billing_cycle' => 'monthly',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'amount_due' => 500000,
            'status' => 'paid',
        ]);

        // Tạo media mẫu
        \App\Models\Media::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'mediable_type' => \App\Models\VenueCluster::class,
            'mediable_id' => $this->cluster1->id,
            'collection' => 'gallery',
            'file_name' => 'san-dep.jpg',
            'file_path' => 'clusters/san-dep.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 102400,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/venue-clusters');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->cluster1->id)
            ->assertJsonPath('data.0.fee_status', 'paid')
            ->assertJsonPath('data.0.image_path', 'clusters/san-dep.jpg');
    }

    public function test_admin_can_view_venue_cluster_detail_with_images(): void
    {
        // Tạo media mẫu
        \App\Models\Media::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'mediable_type' => \App\Models\VenueCluster::class,
            'mediable_id' => $this->cluster1->id,
            'collection' => 'gallery',
            'file_name' => 'san-dep.jpg',
            'file_path' => 'clusters/san-dep.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 102400,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/venue-clusters/{$this->cluster1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.cluster.id', $this->cluster1->id)
            ->assertJsonCount(1, 'data.cluster.images')
            ->assertJsonPath('data.cluster.images.0.file_path', 'clusters/san-dep.jpg');
    }

    public function test_admin_can_lock_and_unlock_venue_cluster(): void
    {
        // 1. Khóa cụm sân
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/lock", [
                'status_reason' => 'Vi phạm điều khoản thanh toán',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'status' => 'locked',
            'status_reason' => 'Vi phạm điều khoản thanh toán',
        ]);

        // 2. Mở khóa cụm sân
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/unlock");

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'status' => 'active',
            'status_reason' => null,
        ]);
    }

    public function test_admin_can_update_venue_cluster_amenities(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/amenities", [
                'amenities' => ['Wifi', 'Gửi xe', 'Phòng tắm VIP'],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
        ]);
        $this->assertEquals(['Wifi', 'Gửi xe', 'Phòng tắm VIP'], $this->cluster1->fresh()->amenities);

        // Validate lỗi
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/amenities", [
                'amenities' => '',
            ]);
        $response->assertStatus(422);
    }

    // ==========================================
    // OWNER CLUSTER MEDIA TESTS
    // ==========================================

    public function test_owner_can_upload_and_delete_cluster_media(): void
    {
        Storage::fake('public');

        // 1. Tải lên hình ảnh
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/media", [
                'image' => UploadedFile::fake()->image('my-court.jpg'),
            ]);

        $response->assertStatus(200);
        $mediaId = $response->json('data.id');
        $filePath = $response->json('data.file_path');

        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'mediable_id' => $this->cluster1->id,
        ]);

        Storage::disk('public')->assertExists($filePath);

        // 2. Xóa hình ảnh
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->deleteJson("/api/owner/venue-clusters/{$this->cluster1->id}/media/{$mediaId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_owner_cannot_upload_or_delete_other_owners_cluster_media(): void
    {
        Storage::fake('public');

        // 1. Owner 2 không có quyền upload lên cluster1
        $response = $this->actingAs($this->owner2, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/media", [
                'image' => UploadedFile::fake()->image('hack-court.jpg'),
            ]);
        $response->assertStatus(403);

        // Tạo media mẫu của owner1
        $media = \App\Models\Media::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'mediable_type' => \App\Models\VenueCluster::class,
            'mediable_id' => $this->cluster1->id,
            'collection' => 'gallery',
            'file_name' => 'owner1-court.jpg',
            'file_path' => 'clusters/owner1-court.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 102400,
        ]);

        // 2. Owner 2 không có quyền xoá media của cluster1
        $response = $this->actingAs($this->owner2, 'sanctum')
            ->deleteJson("/api/owner/venue-clusters/{$this->cluster1->id}/media/{$media->id}");
        $response->assertStatus(403);
    }

    public function test_owner_cannot_update_cluster_or_courts_when_locked(): void
    {
        // 1. Lock the cluster
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Locked for testing',
        ]);

        // 2. Try to update cluster details as owner -> should be blocked by middleware
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->putJson("/api/owner/venue-clusters/{$this->cluster1->id}", [
                'name' => 'Should fail Name',
                'address' => 'Danang',
                'phone_contact' => '0987654321',
                'latitude' => 16.0544,
                'longitude' => 108.2022,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['venue_cluster_id']);

        // 3. Try to add a court to the locked cluster -> should be blocked
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson('/api/owner/venue-courts', [
                'venue_cluster_id' => $this->cluster1->id,
                'court_type_id' => $this->courtType->id,
                'name' => 'Sân số 2',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['venue_cluster_id']);
    }
}
