<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueLockAppeal;
use App\Models\Booking;
use App\Models\PriceSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueLockAppealTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner1;
    private User $owner2;
    private User $player;
    private CourtType $courtType;
    private VenueCluster $cluster1;
    private VenueCourt $court1;

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

        $this->player = User::create([
            'username' => 'player_test',
            'full_name' => 'Player Test',
            'email' => 'player@sportgo.vn',
            'phone' => '0999999994',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        // 3. Tạo Loại Sân
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

        // 5. Tạo sân con
        $this->court1 = VenueCourt::create([
            'venue_cluster_id' => $this->cluster1->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Sân số 1',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        // 6. Tạo price slot mẫu để đặt sân
        PriceSlot::create([
            'venue_cluster_id' => $this->cluster1->id,
            'court_type_id' => $this->courtType->id,
            'name' => 'Khung giờ thường',
            'apply_to_days' => [1, 2, 3, 4, 5, 6, 7],
            'start_time' => '05:00:00',
            'end_time' => '22:00:00',
            'price' => 100000.00,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_suspend_and_resume_own_venue_cluster(): void
    {
        // 1. Tạm ngưng
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->patchJson("/api/owner/venue-clusters/{$this->cluster1->id}/suspend");

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'status' => 'locked',
            'status_reason' => 'Owner tạm ngưng kinh doanh.',
        ]);

        // 2. Mở lại
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->patchJson("/api/owner/venue-clusters/{$this->cluster1->id}/resume");

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'status' => 'active',
            'status_reason' => null,
        ]);
    }

    public function test_owner_cannot_resume_when_locked_by_admin(): void
    {
        // 1. Admin khóa
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/lock", [
                'status_reason' => 'Vi phạm quy định hệ thống',
            ]);

        // 2. Owner cố mở lại -> Phải bị chặn bởi EnforceVenueAccessRestrictions middleware hoặc controller
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->patchJson("/api/owner/venue-clusters/{$this->cluster1->id}/resume");

        $response->assertStatus(422);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $this->cluster1->id,
            'status' => 'locked',
            'status_reason' => 'Vi phạm quy định hệ thống',
        ]);
    }

    public function test_owner_can_appeal_and_admin_can_reply(): void
    {
        // 1. Admin khóa
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/venue-clusters/{$this->cluster1->id}/lock", [
                'status_reason' => 'Vi phạm quy định hệ thống',
            ]);

        // 2. Owner gửi khiếu nại kháng cáo
        $response = $this->actingAs($this->owner1, 'sanctum')
            ->postJson('/api/owner/lock-appeals', [
                'venue_cluster_id' => $this->cluster1->id,
                'title' => 'Kháng cáo vi phạm',
                'content' => 'Chúng tôi đã khắc phục và xin giải trình...',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('venue_lock_appeals', [
            'venue_cluster_id' => $this->cluster1->id,
            'owner_id' => $this->owner1->id,
            'title' => 'Kháng cáo vi phạm',
            'status' => 'pending',
        ]);

        $appealId = $response->json('data.id');

        // 3. Admin lấy danh sách appeals kèm lọc
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/lock-appeals?venue_cluster_id={$this->cluster1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $appealId);

        // 4. Admin phản hồi đồng ý giải quyết khiếu nại (resolved)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/lock-appeals/{$appealId}/reply", [
                'reply_content' => 'Đồng ý mở khóa cho cụm sân.',
                'decision' => 'resolved',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('venue_lock_appeals', [
            'id' => $appealId,
            'status' => 'resolved',
            'reply_content' => 'Đồng ý mở khóa cho cụm sân.',
            'replied_by' => $this->admin->id,
        ]);
    }

    public function test_player_interactions_when_cluster_is_locked(): void
    {
        // 1. Đặt sân trước khi cụm sân bị khóa để tạo lịch sử booking
        $booking = Booking::create([
            'booking_code' => 'BK12345',
            'customer_id' => $this->player->id,
            'venue_cluster_id' => $this->cluster1->id,
            'venue_court_id' => $this->court1->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_price' => 100000.00,
            'status' => 'confirmed',
            'payment_option' => 'no_prepay',
        ]);

        // 2. Khóa cụm sân
        $this->cluster1->update([
            'status' => 'locked',
            'status_reason' => 'Bị khóa do quá hạn nợ phí',
        ]);

        // 3. Player truy cập chi tiết cụm sân -> Vẫn xem được chi tiết
        $response = $this->getJson("/api/venues/{$this->cluster1->slug}");
        $response->assertStatus(200);

        // 4. Player truy cập xem lịch trống -> Bị chặn
        $response = $this->getJson("/api/venues/{$this->cluster1->slug}/schedule?booking_date=" . now()->addDays(2)->format('Y-m-d'));
        if ($response->status() !== 422) {
            dd($response->status(), $response->getContent());
        }
        $response->assertStatus(422);

        // 5. Player cố gắng tạo booking mới -> Bị chặn
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/bookings', [
                'venue_court_id' => $this->court1->id,
                'booking_date' => now()->addDays(2)->format('Y-m-d'),
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'payment_option' => 'no_prepay',
            ]);
        $response->assertStatus(422);

        // 6. Player hủy booking đã đặt trước đó -> Vẫn được phép
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel", [
                'reason' => 'Tôi muốn hủy lịch đặt',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }
}
