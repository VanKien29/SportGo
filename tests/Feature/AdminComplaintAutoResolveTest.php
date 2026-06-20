<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\PolicyRule;
use App\Models\Role;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminComplaintAutoResolveTest extends TestCase
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

        // Seed SystemPolicy
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
    }

    public function test_admin_can_get_complaint_auto_resolve_config(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/complaints/auto-resolve-config');

        $response->assertOk()
            ->assertJsonPath('data.policy_id', $this->policy->id)
            ->assertJsonStructure([
                'data' => [
                    'policy_id',
                    'configs' => [
                        'venue' => [
                            'target_type',
                            'target_type_label',
                            'reason',
                            'is_auto_resolve_enabled',
                        ],
                        'system' => [
                            'target_type',
                            'target_type_label',
                            'reason',
                            'is_auto_resolve_enabled',
                        ]
                    ]
                ]
            ]);
    }

    public function test_admin_can_save_complaint_auto_resolve_config(): void
    {
        $payload = [
            'configs' => [
                [
                    'target_type' => 'venue',
                    'is_auto_resolve_enabled' => true,
                    'reason' => 'Auto resolved venue complaint',
                ],
                [
                    'target_type' => 'system',
                    'is_auto_resolve_enabled' => false,
                    'reason' => 'Default system reason',
                ],
            ]
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/complaint-resolve-policy', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Lưu cấu hình tự động xử lý khiếu nại thành công.');

        $rule = PolicyRule::query()
            ->where('system_policy_id', $this->policy->id)
            ->where('rule_code', 'complaint_auto_resolve_venue')
            ->first();

        $this->assertNotNull($rule);
        $this->assertTrue($rule->result_json['is_auto_resolve_enabled']);
        $this->assertEquals('Auto resolved venue complaint', $rule->result_json['reason']);
    }

    public function test_complaint_auto_resolves_when_enabled(): void
    {
        // 1. Enable auto-resolve for system complaint
        PolicyRule::query()->create([
            'system_policy_id' => $this->policy->id,
            'rule_code' => 'complaint_auto_resolve_system',
            'rule_name' => 'Tự động xử lý khiếu nại: Hệ thống',
            'rule_type' => 'complaint_auto_resolve',
            'action_code' => 'complaint.created',
            'decision_key' => 'complaint_auto_resolve',
            'conflict_group' => 'complaint_auto_resolve_system',
            'condition_json' => ['complaint_type' => 'system'],
            'result_json' => [
                'is_auto_resolve_enabled' => true,
                'reason' => 'Auto resolved by system policy',
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        // 2. Create complaint
        $complaint = Complaint::query()->create([
            'complaint_type' => 'system',
            'customer_id' => $this->player->id,
            'content' => 'System error on booking slot',
            'status' => 'open',
        ]);

        // 3. Assert status is resolved, and resolve_note is set
        $this->assertEquals('resolved', $complaint->fresh()->status);
        $this->assertEquals('Auto resolved by system policy', $complaint->fresh()->resolve_note);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaint.resolved',
            'entity_type' => 'complaints',
            'entity_id' => $complaint->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->player->id,
            'type' => 'complaint_updated',
            'reference_id' => $complaint->id,
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
