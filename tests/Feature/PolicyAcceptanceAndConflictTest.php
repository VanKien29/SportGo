<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PolicyActionBinding;
use App\Models\PolicyRule;
use App\Models\Role;
use App\Models\SystemPolicy;
use App\Models\User;
use App\Models\UserPolicyAcceptance;
use App\Models\UserRole;
use App\Services\Admin\PolicyConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyAcceptanceAndConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $userRole = Role::create([
            'name' => 'user',
            'display_name' => 'Người dùng',
            'is_system' => true,
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Quản trị viên',
            'is_system' => true,
        ]);

        $this->user = User::create([
            'username' => 'policy_user',
            'full_name' => 'Policy User',
            'email' => 'policy-user@sportgo.vn',
            'phone' => '0900000001',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'username' => 'policy_admin',
            'full_name' => 'Policy Admin',
            'email' => 'policy-admin@sportgo.vn',
            'phone' => '0900000002',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $userRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        UserRole::create([
            'user_id' => $this->admin->id,
            'role_id' => $adminRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);
    }

    public function test_user_must_accept_only_active_effective_required_policies(): void
    {
        $required = $this->createPolicy([
            'key' => 'terms_required',
            'title' => 'Điều khoản bắt buộc',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => true,
            'effective_from' => now()->subDay(),
        ]);

        $this->createPolicy([
            'key' => 'terms_future',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => true,
            'effective_from' => now()->addDay(),
        ]);

        $this->createPolicy([
            'key' => 'terms_not_required',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => false,
            'effective_from' => now()->subDay(),
        ]);

        $this->createPolicy([
            'key' => 'terms_expired',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => true,
            'effective_from' => now()->subDays(3),
            'effective_to' => now()->subDay(),
        ]);

        $this->getJson('/api/policies/required')
            ->assertUnauthorized();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/policies/required');

        $response->assertOk()
            ->assertJsonPath('required', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', $required->id)
            ->assertJsonPath('data.0.policy_type_label', 'Điều khoản sử dụng');
    }

    public function test_user_acceptance_is_idempotent_and_audited(): void
    {
        $policy = $this->createPolicy([
            'key' => 'terms_acceptance',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => true,
            'effective_from' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/policies/{$policy->id}/accept")
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('already_accepted', false);

        $this->assertDatabaseHas('user_policy_acceptances', [
            'user_id' => $this->user->id,
            'system_policy_id' => $policy->id,
            'policy_version' => '1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->user->id,
            'action' => 'policy.accepted',
            'entity_type' => 'user_policy_acceptances',
            'policy_id' => $policy->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/policies/{$policy->id}/accept")
            ->assertOk()
            ->assertJsonPath('already_accepted', true);

        $this->assertSame(1, UserPolicyAcceptance::count());
        $this->assertSame(1, AuditLog::where('action', 'policy.accepted')->count());

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/policies/required')
            ->assertOk()
            ->assertJsonPath('required', false)
            ->assertJsonPath('count', 0);
    }

    public function test_user_cannot_accept_inactive_or_not_required_policy(): void
    {
        $policy = $this->createPolicy([
            'key' => 'terms_inactive',
            'policy_type' => 'terms',
            'status' => 'draft',
            'is_active' => false,
            'require_reaccept' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/policies/{$policy->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Chính sách này hiện không cần hoặc không thể xác nhận.');
    }

    public function test_admin_cannot_change_rules_or_bindings_on_active_policy(): void
    {
        $policy = $this->createPolicy([
            'key' => 'active_terms_policy',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
            'require_reaccept' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/policies/{$policy->id}/bindings", [
                'module' => 'auth',
                'action_code' => 'first_login.accept_policy',
                'description' => 'Bắt người dùng xác nhận chính sách.',
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/policies/{$policy->id}/rules", $this->rulePayload([
                'action_code' => 'first_login.accept_policy',
                'rule_type' => 'first_login_accept_required',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy']);
    }

    public function test_admin_rule_validation_rejects_mismatched_action_and_rule_type(): void
    {
        $policy = $this->createPolicy([
            'key' => 'general_draft_policy',
            'policy_type' => 'general',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/policies/{$policy->id}/rules", $this->rulePayload([
                'action_code' => 'booking.cancel_by_customer',
                'rule_type' => 'terms_acceptance_required',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action_code']);
    }

    public function test_publish_requires_active_binding_for_active_rule(): void
    {
        $policy = $this->createPolicy([
            'key' => 'booking_policy_without_binding',
            'policy_type' => 'booking_cancellation',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->createRule($policy, [
            'action_code' => 'booking.cancel_by_customer',
            'rule_type' => 'cancel_before_hours',
            'decision_key' => 'cancel_allowed',
            'conflict_group' => 'booking_cancel_window',
            'condition_json' => ['hours_before_start' => ['gte' => 6]],
            'result_json' => ['allow_cancel' => true],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/policies/{$policy->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action_bindings']);
    }

    public function test_policy_conflict_service_rejects_internal_conflicting_rules(): void
    {
        $policy = $this->createPolicy([
            'key' => 'refund_internal_conflict',
            'policy_type' => 'refund',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $this->createBinding($policy, 'refund.request', 'refund');
        $this->createRule($policy, [
            'rule_code' => 'refund_1',
            'rule_name' => 'Hoàn 50%',
            'action_code' => 'refund.request',
            'rule_type' => 'refund_percent_by_cancel_time',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'refund_percent_minimum',
            'condition_json' => ['hours_before_start' => ['gte' => 24]],
            'result_json' => ['refund_percent' => 50],
        ]);
        $this->createRule($policy, [
            'rule_code' => 'refund_2',
            'rule_name' => 'Hoàn 80%',
            'action_code' => 'refund.request',
            'rule_type' => 'refund_percent_by_cancel_time',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'refund_percent_minimum',
            'condition_json' => ['hours_before_start' => ['gte' => 24]],
            'result_json' => ['refund_percent' => 80],
        ]);

        $errors = app(PolicyConflictService::class)->validateBeforePublish($policy);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cùng điều kiện nhưng trả kết quả khác nhau', $errors[0]);
    }

    public function test_publish_new_version_can_replace_old_active_policy_with_same_key(): void
    {
        $oldPolicy = $this->createPolicy([
            'key' => 'refund_replace_policy',
            'version' => 1,
            'policy_type' => 'refund',
            'status' => 'active',
            'is_active' => true,
            'effective_from' => now()->subDay(),
        ]);
        $this->createBinding($oldPolicy, 'refund.request', 'refund');
        $this->createRule($oldPolicy, [
            'action_code' => 'refund.request',
            'rule_type' => 'refund_percent_by_cancel_time',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'refund_percent_minimum',
            'condition_json' => ['hours_before_start' => ['gte' => 24]],
            'result_json' => ['refund_percent' => 50],
        ]);

        $newPolicy = $this->createPolicy([
            'key' => 'refund_replace_policy',
            'version' => 2,
            'policy_type' => 'refund',
            'status' => 'draft',
            'is_active' => false,
            'replaced_policy_id' => $oldPolicy->id,
        ]);
        $this->createBinding($newPolicy, 'refund.request', 'refund');
        $this->createRule($newPolicy, [
            'action_code' => 'refund.request',
            'rule_type' => 'refund_percent_by_cancel_time',
            'decision_key' => 'refund_percent',
            'conflict_group' => 'refund_percent_minimum',
            'condition_json' => ['hours_before_start' => ['gte' => 24]],
            'result_json' => ['refund_percent' => 80],
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/policies/{$newPolicy->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('system_policies', [
            'id' => $oldPolicy->id,
            'status' => 'archived',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('system_policies', [
            'id' => $newPolicy->id,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_draft_policy_as_archived_and_keep_status_history(): void
    {
        $policy = $this->createPolicy([
            'key' => 'delete_draft_policy',
            'policy_type' => 'terms',
            'status' => 'draft',
            'is_active' => false,
        ]);

        // Create status histories
        \App\Models\PolicyStatusHistory::create([
            'system_policy_id' => $policy->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $this->admin->id,
            'actor_type' => 'admin',
            'reason' => 'Created draft policy',
        ]);

        // Create override constraints
        \App\Models\PolicyOverrideConstraint::create([
            'system_policy_id' => $policy->id,
            'rule_code' => 'rule_1',
            'constraint_key' => 'min_cancel_hours',
            'constraint_name' => 'Min cancel hours',
            'comparison_direction' => 'exact_only',
            'message_vi' => 'Không được sửa quy tắc',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/policies/{$policy->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Đã xóa bản nháp chính sách.');

        $this->assertDatabaseHas('system_policies', [
            'id' => $policy->id,
            'status' => 'archived',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('policy_status_histories', [
            'system_policy_id' => $policy->id,
            'old_status' => 'draft',
            'new_status' => 'archived',
        ]);
        $this->assertDatabaseHas('policy_override_constraints', ['system_policy_id' => $policy->id]);
    }

    public function test_admin_cannot_delete_non_draft_policy(): void
    {
        $policy = $this->createPolicy([
            'key' => 'delete_active_policy',
            'policy_type' => 'terms',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/policies/{$policy->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['policy']);

        $this->assertDatabaseHas('system_policies', ['id' => $policy->id]);
    }

    private function createPolicy(array $attributes = []): SystemPolicy
    {
        return SystemPolicy::create([
            'key' => $attributes['key'] ?? 'policy_' . uniqid(),
            'version' => $attributes['version'] ?? 1,
            'title' => $attributes['title'] ?? 'Chính sách kiểm thử',
            'content' => $attributes['content'] ?? 'Nội dung chính sách kiểm thử.',
            'type' => $attributes['type'] ?? 'general',
            'policy_type' => $attributes['policy_type'] ?? 'general',
            'status' => $attributes['status'] ?? 'draft',
            'is_active' => $attributes['is_active'] ?? false,
            'is_overridable' => $attributes['is_overridable'] ?? false,
            'priority' => $attributes['priority'] ?? 0,
            'effective_from' => $attributes['effective_from'] ?? null,
            'effective_to' => $attributes['effective_to'] ?? null,
            'require_reaccept' => $attributes['require_reaccept'] ?? false,
            'change_summary' => $attributes['change_summary'] ?? null,
            'replaced_policy_id' => $attributes['replaced_policy_id'] ?? null,
            'created_by' => $attributes['created_by'] ?? $this->admin->id,
            'updated_by' => $attributes['updated_by'] ?? $this->admin->id,
        ]);
    }

    private function createBinding(SystemPolicy $policy, string $actionCode, string $module): PolicyActionBinding
    {
        return PolicyActionBinding::create([
            'system_policy_id' => $policy->id,
            'module' => $module,
            'action_code' => $actionCode,
            'description' => 'Binding kiểm thử',
            'is_active' => true,
        ]);
    }

    private function createRule(SystemPolicy $policy, array $attributes = []): PolicyRule
    {
        return PolicyRule::create([
            'system_policy_id' => $policy->id,
            'action_code' => $attributes['action_code'] ?? 'first_login.accept_policy',
            'rule_code' => $attributes['rule_code'] ?? 'rule_' . uniqid(),
            'rule_name' => $attributes['rule_name'] ?? 'Quy tắc kiểm thử',
            'rule_type' => $attributes['rule_type'] ?? 'terms_acceptance_required',
            'decision_key' => $attributes['decision_key'] ?? 'must_accept_terms',
            'conflict_group' => $attributes['conflict_group'] ?? 'terms_acceptance',
            'condition_json' => $attributes['condition_json'] ?? ['active_policy_version_not_accepted' => true],
            'result_json' => $attributes['result_json'] ?? ['must_accept' => true],
            'priority' => $attributes['priority'] ?? 0,
            'is_active' => $attributes['is_active'] ?? true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function rulePayload(array $overrides = []): array
    {
        return [
            'action_code' => $overrides['action_code'] ?? 'first_login.accept_policy',
            'rule_code' => $overrides['rule_code'] ?? 'rule_' . uniqid(),
            'rule_name' => $overrides['rule_name'] ?? 'Quy tắc kiểm thử',
            'rule_type' => $overrides['rule_type'] ?? 'terms_acceptance_required',
            'decision_key' => $overrides['decision_key'] ?? 'must_accept_terms',
            'conflict_group' => $overrides['conflict_group'] ?? 'terms_acceptance',
            'condition_json' => $overrides['condition_json'] ?? ['active_policy_version_not_accepted' => true],
            'result_json' => $overrides['result_json'] ?? ['must_accept' => true],
            'priority' => $overrides['priority'] ?? 0,
            'is_active' => $overrides['is_active'] ?? true,
        ];
    }
}
