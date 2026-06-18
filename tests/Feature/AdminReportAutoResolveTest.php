<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\PolicyRule;
use App\Models\Report;
use App\Models\Role;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\UserRole;
use App\Models\ModerationThreshold;
use App\Services\Policy\PolicyRuleSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportAutoResolveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $player;
    private Role $adminRole;
    private SystemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('admin_user', 'admin@sportgo.vn');
        $this->assignRole($this->admin, $this->adminRole);

        $this->player = $this->createUser('player_user', 'player@sportgo.vn');

        // Seed SystemPolicy & Thresholds
        $this->policy = SystemPolicy::query()->create([
            'key' => 'moderation',
            'title' => 'Chính sách kiểm duyệt & báo cáo',
            'type' => 'moderation',
            'policy_type' => 'moderation',
            'content' => 'Test content',
            'status' => 'active',
            'is_active' => true,
            'priority' => 80,
            'version' => 1,
        ]);

        $threshold = ModerationThreshold::query()->create([
            'system_policy_id' => $this->policy->id,
            'target_type' => 'community_post',
            'warning_threshold' => 2,
            'action_threshold' => 3,
            'unique_reporters_threshold' => 2,
            'timeframe_days' => 7,
        ]);

        // Sync rules from thresholds
        app(PolicyRuleSyncService::class)->syncFromThresholds($this->policy);
    }

    public function test_admin_can_get_auto_resolve_config(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/reports/auto-resolve-config');

        $response->assertOk()
            ->assertJsonPath('data.policy_id', $this->policy->id)
            ->assertJsonStructure([
                'data' => [
                    'policy_id',
                    'configs' => [
                        'community_post' => [
                            'target_type',
                            'target_type_label',
                            'warning_threshold',
                            'action_threshold',
                            'unique_reporters_threshold',
                            'window_days',
                            'reason',
                            'is_auto_resolve_enabled',
                        ]
                    ]
                ]
            ]);
    }

    public function test_admin_can_save_auto_resolve_config(): void
    {
        $payload = [
            'configs' => [
                [
                    'target_type' => 'community_post',
                    'is_auto_resolve_enabled' => true,
                    'reason' => 'Spam too much',
                ],
                [
                    'target_type' => 'venue_post',
                    'is_auto_resolve_enabled' => false,
                    'reason' => 'Default reason',
                ],
                [
                    'target_type' => 'comment',
                    'is_auto_resolve_enabled' => false,
                    'reason' => 'Default reason',
                ],
                [
                    'target_type' => 'venue_cluster',
                    'is_auto_resolve_enabled' => false,
                    'reason' => 'Default reason',
                ],
            ]
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/report-resolve-policy', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Lưu cấu hình tự động xử lý báo cáo thành công.');

        $rule = PolicyRule::query()
            ->where('system_policy_id', $this->policy->id)
            ->where('rule_code', 'moderation_score_community_post')
            ->first();

        $this->assertNotNull($rule);
        $this->assertTrue($rule->result_json['is_auto_resolve_enabled']);
        $this->assertEquals('Spam too much', $rule->result_json['reason']);
    }

    public function test_auto_resolve_hides_content_when_threshold_is_met(): void
    {
        // Enable auto-resolve for community_post
        $rule = PolicyRule::query()
            ->where('system_policy_id', $this->policy->id)
            ->where('rule_code', 'moderation_score_community_post')
            ->first();
        
        $r = $rule->result_json ?? [];
        $r['is_auto_resolve_enabled'] = true;
        $r['reason'] = 'Auto hidden by policy';
        $rule->update(['result_json' => $r]);

        // Create a post
        $post = CommunityPost::query()->create([
            'author_id' => $this->player->id,
            'content' => 'Community post to be reported',
            'status' => 'published',
        ]);

        // Create 3 reports from 3 different users (meeting the threshold of 3 reports and 2 unique reporters)
        $u1 = $this->createUser('u1', 'u1@sportgo.vn');
        $u2 = $this->createUser('u2', 'u2@sportgo.vn');
        $u3 = $this->createUser('u3', 'u3@sportgo.vn');

        // Creating the 3rd report will trigger evaluation and hiding of the post
        Report::query()->create([
            'reporter_id' => $u1->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'description' => 'Report 1',
            'status' => 'pending',
        ]);

        Report::query()->create([
            'reporter_id' => $u2->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'description' => 'Report 2',
            'status' => 'pending',
        ]);

        Report::query()->create([
            'reporter_id' => $u3->id,
            'reportable_type' => CommunityPost::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'description' => 'Report 3',
            'status' => 'pending',
        ]);

        // Assert post status is hidden due to auto resolve
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'status' => 'hidden',
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
