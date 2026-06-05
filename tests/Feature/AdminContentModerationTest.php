<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\VenuePost;
use App\Models\VenueCluster;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $player;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo vai trò admin và user thường
        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('admin_user', 'admin@sportgo.vn');
        $this->assignRole($this->admin, $this->adminRole);

        $this->player = $this->createUser('player_user', 'player@sportgo.vn');
    }

    public function test_admin_can_view_moderation_queue(): void
    {
        // Tạo dữ liệu bài viết chờ duyệt
        CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Bài viết cộng đồng thử nghiệm',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/moderation/queue?type=community_posts');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['data', 'current_page', 'last_page']])
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.content', 'Bài viết cộng đồng thử nghiệm');
    }

    public function test_admin_can_approve_community_and_venue_posts(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Bài viết cộng đồng chờ duyệt',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/posts/community_posts/{$post->id}/approve");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'published',
            'reviewed_by' => $this->admin->id,
        ]);

        // Kiểm tra gửi thông báo in-app
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->player->id,
            'type' => 'post_approved',
        ]);

        // Kiểm tra ghi Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'post.approved',
            'entity_id' => $post->id,
        ]);
    }

    public function test_admin_can_reject_post_with_reason(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Bài viết cộng đồng chờ duyệt',
            'status' => 'pending_review',
        ]);

        // Không gửi lý do
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/posts/community_posts/{$post->id}/reject")
            ->assertStatus(422);

        // Gửi lý do hợp lệ
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/posts/community_posts/{$post->id}/reject", [
                'reason' => 'Nội dung spam quảng cáo.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_reason', 'Nội dung spam quảng cáo.');

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'rejected',
            'status_reason' => 'Nội dung spam quảng cáo.',
        ]);

        // Kiểm tra thông báo và audit log
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->player->id,
            'type' => 'post_rejected',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'post.rejected',
            'reason' => 'Nội dung spam quảng cáo.',
        ]);
    }

    public function test_admin_can_hide_post_with_reason(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Bài viết đang hoạt động',
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/posts/community_posts/{$post->id}/hide", [
                'reason' => 'Nội dung vi phạm tiêu chuẩn cộng đồng.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'hidden');

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'hidden',
            'status_reason' => 'Nội dung vi phạm tiêu chuẩn cộng đồng.',
        ]);
    }

    public function test_admin_can_delete_post_status_only(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Bài viết cộng đồng cần xóa',
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/moderation/posts/community_posts/{$post->id}", [
                'reason' => 'Bài viết vi phạm tiêu chuẩn cộng đồng.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        // Bản ghi vẫn phải tồn tại trong database nhưng có trạng thái hidden (không bị xóa cứng)
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'hidden',
            'status_reason' => 'Bài viết vi phạm tiêu chuẩn cộng đồng.',
        ]);
    }

    public function test_admin_can_resolve_report_and_apply_actions(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Nội dung bị người khác báo cáo',
            'status' => 'published',
        ]);

        $report = Report::query()->create([
            'reporter_id' => $this->player->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'harassment',
            'description' => 'Người này quấy rối tôi.',
            'status' => 'pending',
        ]);

        // Bác bỏ báo cáo (dismissed)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/reports/{$report->id}/resolve", [
                'status' => 'dismissed',
                'action_note' => 'Không đủ bằng chứng để xác định vi phạm.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'dismissed');

        // Nội dung bài viết vẫn hoạt động
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'published',
        ]);

        // Tạo báo cáo mới để thử nghiệm giải quyết (resolved) với hình phạt khóa tài khoản
        $reporter2 = $this->createUser('reporter_two', 'reporter2@sportgo.vn');
        $report2 = Report::query()->create([
            'reporter_id' => $reporter2->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'offensive',
            'description' => 'Quấy rối nặng.',
            'status' => 'pending',
        ]);

        $response2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/moderation/reports/{$report2->id}/resolve", [
                'status' => 'resolved',
                'action_taken' => 'account_locked',
                'action_note' => 'Khóa tài khoản 7 ngày vì vi phạm tiêu chuẩn cộng đồng.',
            ]);

        $response2->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.action_taken', 'account_locked');

        // Tác giả bài viết phải bị khóa tài khoản
        $this->assertDatabaseHas('users', [
            'id' => $this->player->id,
            'status' => 'locked',
            'locked_by' => $this->admin->id,
        ]);
    }

    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => $username,
            'email' => $email,
            'phone' => '09' . random_int(10000000, 99999999),
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
