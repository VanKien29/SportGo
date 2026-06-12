<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $staff;
    private User $customer;

    private Role $superAdminRole;
    private Role $adminRole;
    private Role $staffRole;
    private Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo các Vai trò (Roles)
        $this->superAdminRole = Role::query()->create([
            'name' => 'super_admin',
            'display_name' => 'Super Admin',
            'is_system' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Quản trị viên',
            'is_system' => true,
        ]);

        $this->staffRole = Role::query()->create([
            'name' => 'system_staff',
            'display_name' => 'Nhân viên hệ thống',
            'is_system' => true,
        ]);

        $this->userRole = Role::query()->create([
            'name' => 'user',
            'display_name' => 'Người dùng',
            'is_system' => true,
        ]);

        // 2. Tạo các Tài khoản test
        $this->superAdmin = $this->createUser('super_admin_test', 'superadmin@sportgo.test');
        $this->admin = $this->createUser('admin_test', 'admin@sportgo.test');
        $this->staff = $this->createUser('staff_test', 'staff@sportgo.test');
        $this->customer = $this->createUser('customer_test', 'customer@sportgo.test');

        // 3. Gán vai trò
        $this->assignRole($this->superAdmin, $this->superAdminRole);
        $this->assignRole($this->admin, $this->adminRole);
        $this->assignRole($this->staff, $this->staffRole);
        $this->assignRole($this->customer, $this->userRole);
    }

    /**
     * Test Validation đầu vào khi tạo người dùng.
     */
    public function test_create_user_validation_rules(): void
    {
        // 1. Gửi thiếu dữ liệu (ví dụ thiếu email, password, roles)
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'username' => 'test_user',
                'full_name' => 'Test User',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'roles']);

        // 2. Định dạng username không hợp lệ (có dấu, ký tự đặc biệt)
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'username' => 'user-name-invalid!',
                'full_name' => 'Test User',
                'email' => 'test@sportgo.test',
                'password' => '12345678',
                'roles' => [$this->staffRole->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // 3. Trùng lặp username và email
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'username' => 'staff_test', // đã dùng cho $this->staff
                'full_name' => 'Test Duplicate',
                'email' => 'staff@sportgo.test', // đã dùng cho $this->staff
                'password' => '12345678',
                'roles' => [$this->staffRole->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'email']);
    }

    /**
     * Test quy tắc bảo mật: Admin thường không thể tạo tài khoản Admin hoặc Super Admin.
     */
    public function test_admin_cannot_create_or_assign_admin_roles(): void
    {
        // Admin thường cố gắng tạo tài khoản mới và gán vai trò 'admin'
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'username' => 'new_admin_attempt',
                'full_name' => 'New Admin Attempt',
                'email' => 'newadmin@sportgo.test',
                'password' => '12345678',
                'roles' => [$this->adminRole->id], // gán vai trò admin
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);

        // Super Admin thì tạo và gán vai trò Admin thành công
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/users', [
                'username' => 'new_admin_ok',
                'full_name' => 'New Admin Ok',
                'email' => 'newadmin_ok@sportgo.test',
                'password' => '12345678',
                'roles' => [$this->adminRole->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.username', 'new_admin_ok');
    }

    /**
     * Test Admin không thể chỉnh sửa hoặc gán vai trò Admin/Super Admin của tài khoản khác.
     */
    public function test_admin_cannot_edit_admin_role_or_target_admin(): void
    {
        // Admin thường cố gắng sửa tài khoản nhân viên khác lên làm Admin
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->staff->id}", [
                'full_name' => 'Staff Updated By Admin',
                'email' => 'staff_updated@sportgo.test',
                'roles' => [$this->adminRole->id], // Nâng lên admin
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);

        // Admin thường cố gắng sửa thông tin của Super Admin
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->superAdmin->id}", [
                'full_name' => 'Super Admin Hacked',
                'email' => 'superadmin_hacked@sportgo.test',
                'roles' => [$this->superAdminRole->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }

    /**
     * Test người dùng không được tự khóa chính mình.
     */
    public function test_user_cannot_lock_self(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/admin/users/{$this->superAdmin->id}/lock", [
                'lock_type' => 'permanent',
                'status_reason' => 'Tự khóa tài khoản',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    /**
     * Test khi khóa tài khoản, toàn bộ tokens phiên làm việc phải bị thu hồi.
     */
    public function test_locking_user_revokes_tokens_and_creates_audit_log(): void
    {
        // Tạo token giả cho staff
        $this->staff->createToken('test-token');
        $this->assertEquals(1, $this->staff->tokens()->count());

        // Super Admin thực hiện khóa staff tạm thời trong 1 ngày
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/admin/users/{$this->staff->id}/lock", [
                'lock_type' => 'temporary',
                'status_reason' => 'Vi phạm quy định hệ thống.',
                'locked_until' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertStatus(200);

        // Kiểm tra database: tài khoản bị khóa, lưu lý do và tokens bị thu hồi sạch
        $this->staff->refresh();
        $this->assertEquals('locked', $this->staff->status);
        $this->assertEquals('temporary', $this->staff->lock_type);
        $this->assertEquals('Vi phạm quy định hệ thống.', $this->staff->status_reason);
        $this->assertEquals(0, $this->staff->tokens()->count());

        // Kiểm tra audit log có được ghi nhận
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.locked',
            'entity_type' => 'users',
            'entity_id' => $this->staff->id,
        ]);
    }

    /**
     * Test Admin không được khóa Super Admin.
     */
    public function test_admin_cannot_lock_super_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$this->superAdmin->id}/lock", [
                'lock_type' => 'permanent',
                'status_reason' => 'Khóa Super Admin.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);
    }

    /**
     * Helper tạo user.
     */
    private function createUser(string $username, string $email): User
    {
        return User::query()->create([
            'username' => $username,
            'full_name' => str_replace('_', ' ', ucfirst($username)),
            'email' => $email,
            'phone' => '09' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);
    }

    /**
     * Helper gán vai trò.
     */
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
