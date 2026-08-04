<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueStaffAssignment;
use App\Services\Auth\VenueStaffMenuPermissionService;
use App\Services\Partner\PartnerDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_does_not_bypass_assigned_group_permissions(): void
    {
        $admin = $this->user('restricted_admin');
        $role = $this->role('admin', 'Quản trị viên');
        $this->assignRole($admin, $role);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/vouchers')
            ->assertForbidden();

        $this->grant($role, ['voucher.view']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/vouchers')
            ->assertOk();
    }

    public function test_action_permission_without_access_is_ineffective_and_returns_403(): void
    {
        $admin = $this->user('voucher_creator');
        $role = $this->role('system_staff', 'Nhân viên voucher');
        $this->assignRole($admin, $role);
        $this->grant($role, ['voucher.create']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [])
            ->assertForbidden();

        $this->grant($role, ['voucher.view']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/vouchers', [])
            ->assertUnprocessable();
    }

    public function test_role_api_rejects_invalid_dependencies_and_cascades_access_revocation(): void
    {
        $superAdmin = $this->user('super_admin_permissions');
        $superRole = $this->role('super_admin', 'Super Admin');
        $targetRole = $this->role('voucher_operator', 'Nhân viên voucher');
        $this->assignRole($superAdmin, $superRole);

        $viewId = Permission::query()->where('code', 'voucher.view')->value('id');
        $createId = Permission::query()->where('code', 'voucher.create')->value('id');

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/admin/roles/{$targetRole->id}/permissions", [
                'permission_ids' => [$createId],
            ])
            ->assertUnprocessable();

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson("/api/admin/roles/{$targetRole->id}/permissions", [
                'permission_ids' => [$viewId, $createId],
            ])
            ->assertOk();

        $this->actingAs($superAdmin, 'sanctum')
            ->patchJson("/api/admin/roles/{$targetRole->id}/permissions/toggle", [
                'permission_id' => $viewId,
                'action' => 'revoke',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $targetRole->id,
            'permission_id' => $viewId,
        ]);
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $targetRole->id,
            'permission_id' => $createId,
        ]);
    }

    public function test_venue_staff_only_accesses_granted_menu_and_never_owner_finance(): void
    {
        [$owner, $staff, $cluster] = $this->venueWorkspace();

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/owner/vouchers?venue_cluster_id={$cluster->id}")
            ->assertForbidden();

        app(VenueStaffMenuPermissionService::class)->sync(
            $staff,
            (string) $cluster->id,
            ['vouchers'],
            $owner
        );

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/owner/vouchers?venue_cluster_id={$cluster->id}")
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/owner/finance/wallets?venue_cluster_id={$cluster->id}")
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath("venue_staff_permissions.{$cluster->id}.0", 'vouchers');
    }

    public function test_voucher_menu_requires_all_cluster_assignment(): void
    {
        [$owner, $staff, $cluster] = $this->venueWorkspace();

        VenueStaffAssignment::query()
            ->where('user_id', $staff->id)
            ->where('venue_cluster_id', $cluster->id)
            ->update([
                'scope_type' => 'court_type',
                'scope_key' => 'court_type:999',
            ]);

        $this->expectException(ValidationException::class);

        app(VenueStaffMenuPermissionService::class)->sync(
            $staff,
            (string) $cluster->id,
            ['vouchers'],
            $owner
        );
    }

    public function test_document_profile_uses_system_management_data(): void
    {
        SystemSetting::upsertProfileValue('company_name', 'Công ty TNHH Thể thao Việt');
        SystemSetting::upsertProfileValue('tax_code', '0101234567');
        SystemSetting::upsertProfileValue('company_address', '123 Nguyễn Trãi, Hà Nội');
        SystemSetting::upsertProfileValue('representative_name', 'Nguyễn Văn An');
        SystemSetting::upsertProfileValue('representative_title', 'Tổng giám đốc');
        SystemSetting::upsertProfileValue('support_email', 'hotro@example.vn');

        $payload = SystemSetting::documentProfilePayload();

        $this->assertSame('Công ty TNHH Thể thao Việt', $payload['sportgo_company_name']);
        $this->assertSame('0101234567', $payload['sportgo_tax_code']);
        $this->assertSame('123 Nguyễn Trãi, Hà Nội', $payload['sportgo_address']);
        $this->assertSame('Nguyễn Văn An', $payload['sportgo_representative_name']);
        $this->assertSame('Tổng giám đốc', $payload['sportgo_representative_title']);
        $this->assertSame('hotro@example.vn', $payload['sportgo_email']);
    }

    public function test_generated_partner_document_uses_system_management_data(): void
    {
        Storage::fake('local');

        SystemSetting::upsertProfileValue('company_name', 'Công ty TNHH Thể thao Việt');
        SystemSetting::upsertProfileValue('tax_code', '0101234567');
        SystemSetting::upsertProfileValue('company_address', '123 Nguyễn Trãi, Hà Nội');
        SystemSetting::upsertProfileValue('representative_name', 'Nguyễn Văn An');
        SystemSetting::upsertProfileValue('representative_title', 'Tổng giám đốc');
        SystemSetting::upsertProfileValue('support_email', 'hotro@example.vn');

        $owner = $this->user('document_profile_owner');
        $document = app(PartnerDocumentService::class)->generateDocument(
            'partner_contract',
            $owner,
            [
                'sportgo_company_name' => 'Dữ liệu cũ không được dùng',
                'sportgo_tax_code' => '9999999999',
                'applicant_name' => $owner->full_name,
                'applicant_email' => $owner->email,
            ],
            $owner,
            ['owner_id' => $owner->id]
        );

        $this->assertSame('Công ty TNHH Thể thao Việt', $document->render_data['sportgo_company_name']);
        $this->assertSame('0101234567', $document->render_data['sportgo_tax_code']);
        Storage::disk('local')->assertExists($document->generated_file_path);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open(Storage::disk('local')->path($document->generated_file_path)) === true);
        $documentXml = (string) $archive->getFromName('word/document.xml');
        $archive->close();

        $documentText = html_entity_decode(strip_tags($documentXml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $this->assertStringContainsString('Công ty TNHH Thể thao Việt', $documentText);
        $this->assertStringNotContainsString('Dữ liệu cũ không được dùng', $documentText);
    }

    private function venueWorkspace(): array
    {
        $owner = $this->user('venue_owner_permissions');
        $staff = $this->user('venue_staff_permissions');
        $ownerRole = $this->role('venue_owner', 'Chủ sân');
        $staffRole = $this->role('venue_staff', 'Nhân viên sân');
        $this->assignRole($owner, $ownerRole);
        $this->assignRole($staff, $staffRole, 'venue');

        $cluster = VenueCluster::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Cụm sân phân quyền',
            'slug' => 'cum-san-phan-quyen',
            'address' => 'Hà Nội',
            'latitude' => 21.0278000,
            'longitude' => 105.8342000,
            'status' => 'active',
        ]);

        VenueStaffAssignment::query()->create([
            'user_id' => $staff->id,
            'venue_cluster_id' => $cluster->id,
            'scope_type' => 'all_cluster',
            'scope_key' => 'all',
            'assigned_by' => $owner->id,
            'status' => 'active',
        ]);

        return [$owner, $staff, $cluster];
    }

    private function user(string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'email' => "{$username}@sportgo.test",
            'status' => 'active',
        ]);
    }

    private function role(string $name, string $displayName): Role
    {
        return Role::query()->create([
            'name' => $name,
            'display_name' => $displayName,
            'is_system' => in_array($name, ['super_admin', 'admin', 'system_staff', 'venue_owner', 'venue_staff'], true),
        ]);
    }

    private function assignRole(User $user, Role $role, string $scopeType = 'system'): void
    {
        UserRole::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType,
            'scope_id' => 0,
        ]);
    }

    private function grant(Role $role, array $codes): void
    {
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('code', $codes)->pluck('id')->all()
        );
    }
}
