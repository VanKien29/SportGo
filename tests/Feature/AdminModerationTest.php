<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\Complaint;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $reporter;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Quản trị viên',
            'is_system' => true,
        ]);
        $userRole = Role::query()->create([
            'name' => 'user',
            'display_name' => 'Người dùng',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('moderation_admin', 'admin@sportgo.test');
        $this->reporter = $this->createUser('moderation_reporter', 'reporter@sportgo.test');
        $this->author = $this->createUser('moderation_author', 'author@sportgo.test');

        $this->assignRole($this->admin, $adminRole);
        $this->assignRole($this->reporter, $userRole);
        $this->assignRole($this->author, $userRole);
    }

    public function test_admin_can_review_and_hide_reported_content_with_audit_and_notifications(): void
    {
        $post = CommunityPost::query()->create([
            'author_id' => $this->author->id,
            'content' => 'Nội dung quảng cáo lặp lại cần kiểm duyệt.',
            'status' => 'published',
        ]);

        $report = Report::query()->create([
            'reporter_id' => $this->reporter->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'description' => 'Bài viết spam nhiều lần.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('summary.pending', 1);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/reports/{$report->id}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewing');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/reports/{$report->id}/resolve", [
                'decision' => 'resolved',
                'action_taken' => 'content_hidden',
                'action_note' => 'Đã xác minh nội dung spam và ẩn bài.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.action_taken', 'content_hidden');

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'hidden',
            'status_reason' => 'Đã xác minh nội dung spam và ẩn bài.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report.resolved',
            'entity_type' => 'reports',
            'entity_id' => $report->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->reporter->id,
            'type' => 'report_processed',
            'reference_id' => $report->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->author->id,
            'type' => 'report_processed',
            'reference_id' => $report->id,
        ]);
    }

    public function test_admin_can_assign_and_resolve_complaint_with_audit_and_notification(): void
    {
        $complaint = Complaint::query()->create([
            'complaint_type' => 'system',
            'customer_id' => $this->reporter->id,
            'content' => 'Cần kiểm tra trạng thái giao dịch.',
            'status' => 'open',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/complaints')
            ->assertOk()
            ->assertJsonPath('data.0.id', $complaint->id)
            ->assertJsonPath('summary.open', 1);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/complaints/{$complaint->id}/assign", [
                'assigned_to' => $this->admin->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.assigned_to.id', $this->admin->id);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/complaints/{$complaint->id}/resolve", [
                'status' => 'resolved',
                'resolve_note' => 'Đã đối soát và phản hồi kết quả cho khách hàng.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolve_note', 'Đã đối soát và phản hồi kết quả cho khách hàng.');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'resolved',
            'assigned_to' => $this->admin->id,
            'resolved_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.resolved',
            'entity_type' => 'complaints',
            'entity_id' => $complaint->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->reporter->id,
            'type' => 'complaint_updated',
            'reference_id' => $complaint->id,
        ]);
    }

    public function test_report_filters_reject_invalid_values_and_reversed_dates(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/reports?target_type=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_type');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/reports?date_from=2026-06-20&date_to=2026-06-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_report_cannot_be_taken_over_by_another_admin(): void
    {
        $otherAdmin = $this->createUser('other_moderation_admin', 'other-admin@sportgo.test');
        $this->assignRole($otherAdmin, Role::query()->where('name', 'admin')->firstOrFail());
        $report = Report::query()->create([
            'reporter_id' => $this->reporter->id,
            'reportable_type' => User::class,
            'reportable_id' => $this->author->id,
            'reason' => 'harassment',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/reports/{$report->id}/review")
            ->assertOk();

        $this->actingAs($otherAdmin, 'sanctum')
            ->patchJson("/api/admin/reports/{$report->id}/review")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'reviewing',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => str_replace('_', ' ', ucfirst($username)),
            'email' => $email,
            'phone' => '09'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);
    }

    private function assignRole(User $user, Role $role): void
    {
        UserRole::query()->firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);
    }
}
