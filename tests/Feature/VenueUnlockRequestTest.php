<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueUnlockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueUnlockRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner1;
    private User $owner2;
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

        // 3. Tạo Cụm sân cho owner 1
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

    /**
     * 1. Owner gửi yêu cầu mở khóa khi cụm sân đang bị khóa → 201
     */
    public function test_owner_can_submit_unlock_request_when_locked(): void
    {
        // Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
            'locked_at' => now(),
            'locked_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/unlock-requests", [
                'reason' => 'Tôi đã khắc phục và xin giải trình mở khóa.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('venue_unlock_requests', [
            'venue_cluster_id' => $this->cluster1->id,
            'requested_by' => $this->owner1->id,
            'status' => 'pending',
            'reason' => 'Tôi đã khắc phục và xin giải trình mở khóa.',
        ]);
    }

    /**
     * 2. Owner không thể gửi khi cụm sân đang active → 422
     */
    public function test_owner_cannot_submit_unlock_request_when_active(): void
    {
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/unlock-requests", [
                'reason' => 'Xin giải trình mở khóa.',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cụm sân hiện không ở trạng thái bị khóa.']);
    }

    /**
     * 3. Owner không thể gửi khi đã có request pending khác → 422
     */
    public function test_owner_cannot_submit_multiple_pending_unlock_requests(): void
    {
        // Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
            'locked_at' => now(),
            'locked_by' => $this->admin->id,
        ]);

        // Tạo sẵn một request pending
        VenueUnlockRequest::create([
            'venue_cluster_id' => $this->cluster1->id,
            'requested_by' => $this->owner1->id,
            'status' => 'pending',
            'reason' => 'Giải trình lần 1',
        ]);

        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/unlock-requests", [
                'reason' => 'Giải trình lần 2',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Bạn đã có yêu cầu mở khóa đang chờ xét duyệt. Vui lòng chờ Admin phản hồi hoặc hủy yêu cầu cũ.']);
    }

    /**
     * 4. Owner có thể hủy request pending → 200
     */
    public function test_owner_can_cancel_pending_unlock_request(): void
    {
        // Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
        ]);

        $request = VenueUnlockRequest::create([
            'venue_cluster_id' => $this->cluster1->id,
            'requested_by' => $this->owner1->id,
            'status' => 'pending',
            'reason' => 'Giải trình',
        ]);

        $response = $this->actingAs($this->owner1, 'sanctum')
            ->patchJson("/api/owner/venue-clusters/{$this->cluster1->id}/unlock-requests/{$request->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_unlock_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * 5. Admin duyệt yêu cầu → cụm sân chuyển sang active → 200
     */
    public function test_admin_can_approve_unlock_request(): void
    {
        // Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
            'locked_at' => now(),
            'locked_by' => $this->admin->id,
        ]);

        $request = VenueUnlockRequest::create([
            'venue_cluster_id' => $this->cluster1->id,
            'requested_by' => $this->owner1->id,
            'status' => 'pending',
            'reason' => 'Giải trình',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/unlock-requests/{$request->id}/approve", [
                'admin_note' => 'Đồng ý mở khóa.',
            ]);

        $response->assertStatus(200);

        // Kiểm tra request update
        $this->assertDatabaseHas('venue_unlock_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'admin_note' => 'Đồng ý mở khóa.',
        ]);

        // Kiểm tra cluster được unlock
        $this->cluster1->refresh();
        $this->assertEquals('active', $this->cluster1->status);
        $this->assertNull($this->cluster1->status_reason);
        $this->assertNull($this->cluster1->locked_at);
        $this->assertNull($this->cluster1->locked_by);
    }

    /**
     * 6. Admin từ chối yêu cầu (có admin_note) → 200
     */
    public function test_admin_can_reject_unlock_request(): void
    {
        // Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
            'locked_at' => now(),
            'locked_by' => $this->admin->id,
        ]);

        $request = VenueUnlockRequest::create([
            'venue_cluster_id' => $this->cluster1->id,
            'requested_by' => $this->owner1->id,
            'status' => 'pending',
            'reason' => 'Giải trình',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/unlock-requests/{$request->id}/reject", [
                'admin_note' => 'Lý do giải trình không hợp lý.',
            ]);

        $response->assertStatus(200);

        // Kiểm tra request update
        $this->assertDatabaseHas('venue_unlock_requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'reviewed_by' => $this->admin->id,
            'admin_note' => 'Lý do giải trình không hợp lý.',
        ]);

        // Cluster vẫn phải locked
        $this->cluster1->refresh();
        $this->assertEquals('locked', $this->cluster1->status);
    }

    /**
     * 7. Owner không thể gửi yêu cầu cho cụm sân của người khác → 403
     */
    public function test_owner_cannot_submit_unlock_request_for_others_venue(): void
    {
        // Khóa cụm sân (sở hữu bởi owner1)
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định',
        ]);

        // Owner 2 cố tình gửi yêu cầu giải trình cho cụm sân của Owner 1
        $response = $this->actingAs($this->owner2, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster1->id}/unlock-requests", [
                'reason' => 'Hack giải trình.',
            ]);

        $response->assertStatus(403);
    }
}
