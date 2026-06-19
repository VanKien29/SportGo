<?php

namespace Tests\Feature;

use App\Models\CourtType;
use App\Models\OwnerBankAccount;
use App\Models\PartnerApplication;
use App\Models\PartnerApplicationCourt;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Jobs\RevokeVenueOwnerRoleJob;
use App\Jobs\SendRevocationReminderJob;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerMailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBannerPartnerApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Admin',
            'is_system' => true,
        ]);

        Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Chủ sân',
            'is_system' => true,
        ]);

        $this->admin = $this->createUser('banner_admin', 'banner.admin@sportgo.vn');
        $this->assignRole($this->admin, $adminRole);
    }

    public function test_admin_can_create_banner_and_public_active_endpoint_returns_it(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/banners', [
                'title' => 'Banner test trang chủ',
                'image' => UploadedFile::fake()->image('home-banner.jpg', 1200, 480),
                'link_url' => 'https://sportgo.test/booking',
                'position' => 'home',
                'sort_order' => 1,
                'is_active' => '1',
                'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.title', 'Banner test trang chủ');

        $bannerId = $response->json('data.id');
        $imagePath = $response->json('data.image_path');

        Storage::disk('public')->assertExists($imagePath);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/banners?position=home')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $bannerId);

        $this->getJson('/api/banners/active/home')
            ->assertOk()
            ->assertJsonPath('data.0.id', $bannerId)
            ->assertJsonPath('data.0.position', 'home');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/banners/{$bannerId}")
            ->assertOk();

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertDatabaseMissing('banners', ['id' => $bannerId]);
    }

    public function test_admin_can_approve_partner_application_and_create_venue_cluster(): void
    {
        Queue::fake();

        $applicant = $this->createUser('partner_applicant', 'kiennguyennguyen0@gmail.com');
        $courtType = CourtType::query()->create([
            'name' => 'Sân cầu lông',
            'description' => 'Cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $application = PartnerApplication::query()->create([
            'user_id' => $applicant->id,
            'business_name' => 'Hộ kinh doanh sân test',
            'tax_code' => 'MST001',
            'venue_name' => 'SportGo Test Partner',
            'venue_address' => '12 Nguyễn Trãi, Hà Nội',
            'venue_map_url' => 'https://maps.example.test',
            'venue_latitude' => 21.0278000,
            'venue_longitude' => 105.8342000,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        PartnerApplicationCourt::query()->create([
            'partner_application_id' => $application->id,
            'court_type_id' => $courtType->id,
            'name' => 'Sân A',
            'sort_order' => 1,
        ]);

        OwnerBankAccount::query()->create([
            'owner_id' => $applicant->id,
            'partner_application_id' => $application->id,
            'bank_name' => 'VCB',
            'bank_code' => 'VCB',
            'account_number' => '123456789',
            'account_holder_name' => 'NGUYEN VAN A',
            'status' => 'pending',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/partner-applications/{$application->id}/approve", [
                'review_note' => 'Hồ sơ hợp lệ.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'contract_pending_owner_signature')
            ->assertJsonPath('data.approved_venue_cluster.name', 'SportGo Test Partner');

        $venueClusterId = $response->json('data.approved_venue_cluster_id');

        $this->assertDatabaseHas('partner_applications', [
            'id' => $application->id,
            'status' => 'contract_pending_owner_signature',
            'reviewed_by' => $this->admin->id,
            'approved_venue_cluster_id' => $venueClusterId,
        ]);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $venueClusterId,
            'owner_id' => $applicant->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('venue_courts', [
            'venue_cluster_id' => $venueClusterId,
            'court_type_id' => $courtType->id,
            'name' => 'Sân A',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('owner_bank_accounts', [
            'owner_id' => $applicant->id,
            'partner_application_id' => $application->id,
            'status' => 'active',
        ]);

        $ownerRoleId = Role::query()->where('name', 'venue_owner')->value('id');
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $applicant->id,
            'role_id' => $ownerRoleId,
            'scope_type' => 'system',
        ]);

        $contract = PartnerContract::query()
            ->where('partner_application_id', $application->id)
            ->firstOrFail();

        $this->assertSame('pending_owner_signature', $contract->status);

        $this->actingAs($applicant, 'sanctum')
            ->postJson('/api/user/partner-application/sign-contract', [
                'contract_id' => $contract->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $applicant->id,
            'role_id' => $ownerRoleId,
            'scope_type' => 'system',
        ]);
        $this->assertDatabaseHas('partner_contracts', [
            'id' => $contract->id,
            'status' => 'pending_sportgo_signature',
        ]);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $venueClusterId,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/contracts/{$contract->id}/approve-signature")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('partner_contracts', [
            'id' => $contract->id,
            'status' => 'signed_active',
        ]);
        $this->assertDatabaseHas('partner_applications', [
            'id' => $application->id,
            'status' => 'completed',
        ]);

        $this->actingAs($applicant, 'sanctum')
            ->postJson("/api/owner/contracts/{$contract->id}/request-termination", [
                'reason' => 'Không còn nhu cầu vận hành cụm sân.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $termination = PartnerTerminationRequest::query()
            ->where('partner_contract_id', $contract->id)
            ->firstOrFail();

        $this->assertSame('submitted', $termination->status);
        $this->assertDatabaseHas('partner_termination_documents', [
            'partner_termination_request_id' => $termination->id,
            'document_type' => 'owner_termination_request',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/contracts/{$contract->id}/approve-termination")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Queue::assertPushed(SendRevocationReminderJob::class);
        Queue::assertPushed(RevokeVenueOwnerRoleJob::class);

        $this->assertDatabaseHas('partner_termination_requests', [
            'id' => $termination->id,
            'status' => 'transition_period',
        ]);
        $this->assertDatabaseHas('partner_settlements', [
            'partner_termination_request_id' => $termination->id,
            'partner_contract_id' => $contract->id,
            'status' => 'approved',
        ]);

        (new RevokeVenueOwnerRoleJob($termination->id))->handle(
            app(PartnerApplicationService::class),
            app(PartnerMailDispatcher::class)
        );

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $applicant->id,
            'role_id' => $ownerRoleId,
            'scope_type' => 'system',
        ]);
        $this->assertDatabaseHas('partner_termination_requests', [
            'id' => $termination->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('partner_contracts', [
            'id' => $contract->id,
            'status' => 'terminated',
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
