<?php

namespace Tests\Feature;

use App\Mail\Partner\PartnerApplicationApprovedMail;
use App\Mail\Partner\PartnerApplicationReceivedMail;
use App\Mail\Partner\PartnerApplicationRejectedMail;
use App\Mail\Partner\PartnerContractSignedByOwnerMail;
use App\Mail\Partner\PartnerDocumentOtpMail;
use App\Models\CourtType;
use App\Models\GeneratedDocument;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    public function test_user_can_submit_partner_application_with_bank_courts_and_documents(): void
    {
        Storage::fake('public');
        Queue::fake();
        Mail::fake();
        Http::fake([
            'api.vietqr.io/v2/banks' => Http::response([
                'code' => '00',
                'data' => [[
                    'id' => 1,
                    'name' => 'Ngân hàng TMCP Ngoại thương Việt Nam',
                    'code' => 'VCB',
                    'bin' => '970436',
                    'shortName' => 'Vietcombank',
                    'transferSupported' => 1,
                    'lookupSupported' => 1,
                ]],
            ]),
            'https://provinces.open-api.vn/api/v2/' => Http::response([
                ['code' => 1, 'name' => 'Thành phố Hà Nội'],
            ]),
            'https://provinces.open-api.vn/api/v2/p/1*' => Http::response([
                'code' => 1,
                'name' => 'Thành phố Hà Nội',
                'wards' => [
                    ['code' => 10101, 'name' => 'Phường Thanh Xuân'],
                ],
            ]),
        ]);

        $user = $this->createUser('kien_partner', 'kiennguyennguyen0@gmail.com');
        $courtType = CourtType::query()->create([
            'name' => 'Sân cầu lông',
            'description' => 'Cầu lông',
            'player_count' => 4,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/user/partner-application', [
            'applicant_full_name' => 'Nguyễn Kiên',
            'applicant_phone' => '0912345678',
            'applicant_email' => 'kiennguyennguyen0@gmail.com',
            'applicant_birth_date' => '1990-01-01',
            'applicant_address' => 'Hà Nội',
            'applicant_type' => 'business',
            'representative_name' => 'Nguyễn Kiên',
            'representative_identity_type' => 'cccd',
            'representative_identity_number' => '001203000001',
            'representative_identity_issued_date' => '2024-01-01',
            'representative_identity_issued_place' => 'Cục CSQLHC về TTXH',
            'representative_position' => 'Chủ cơ sở',
            'business_name' => 'Hộ kinh doanh SportGo Kiên',
            'tax_code' => '0109999999',
            'business_code' => 'HKD-KIEN',
            'business_license_number' => 'GPKD-KIEN-001',
            'business_address' => '12 Nguyễn Trãi, Hà Nội',
            'venue_name' => 'SportGo Kiên Test',
            'street_address' => '12 Nguyễn Trãi',
            'venue_address' => '12 Nguyễn Trãi, Phường Thanh Xuân, Hà Nội',
            'venue_province_code' => '1',
            'venue_ward_code' => '10101',
            'venue_map_url' => 'https://maps.google.com/?q=21.0278,105.8342',
            'venue_latitude' => '21.0278000',
            'venue_longitude' => '105.8342000',
            'venue_phone' => '0912345678',
            'venue_email' => 'kiennguyennguyen0@gmail.com',
            'venue_description' => 'Cụm sân đăng ký thử nghiệm.',
            'expected_opening_hours' => '05:00 - 23:00',
            'parking_info' => 'Có bãi xe máy.',
            'amenities' => json_encode(['Bãi xe', 'Nước uống']),
            'court_count_total' => 2,
            'base_price_per_hour' => 120000,
            'courts' => json_encode([
                ['court_type_id' => $courtType->id, 'name' => 'Sân 1'],
                ['court_type_id' => $courtType->id, 'name' => 'Sân 2'],
            ]),
            'bank_name' => 'Vietcombank',
            'bank_code' => 'VCB',
            'bank_bin' => '970436',
            'account_number' => '1234567890',
            'account_holder_name' => 'NGUYEN KIEN',
            'bank_branch' => 'Hà Nội',
            'confirmed' => '1',
            'identity_documents' => [
                UploadedFile::fake()->image('cccd-front.jpg', 800, 500),
                UploadedFile::fake()->image('cccd-back.jpg', 800, 500),
            ],
            'business_license_documents' => [
                UploadedFile::fake()->create('business-license.pdf', 120, 'application/pdf'),
            ],
            'facility_images' => [
                UploadedFile::fake()->image('facility-1.jpg', 1200, 800),
                UploadedFile::fake()->image('facility-2.jpg', 1200, 800),
            ],
            'bank_documents' => [
                UploadedFile::fake()->create('bank-proof.pdf', 80, 'application/pdf'),
            ],
            'lease_documents' => [
                UploadedFile::fake()->create('lease-contract.pdf', 80, 'application/pdf'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'draft');

        $applicationId = $response->json('data.id');

        $this->assertDatabaseHas('generated_documents', [
            'partner_application_id' => $applicationId,
            'document_type' => 'partner_application_form',
            'status' => 'pending_owner_signature',
        ]);

        $applicationDocument = GeneratedDocument::query()
            ->where('partner_application_id', $applicationId)
            ->where('document_type', 'partner_application_form')
            ->firstOrFail();

        $otpResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/user/partner-application/{$applicationId}/sign-document/request-otp", [
                'signature_image' => $this->signatureImage(),
                'confirmed' => true,
                'confirmation_text' => 'Tôi xác nhận đã đọc, kiểm tra và chịu trách nhiệm về tính chính xác, hợp pháp của toàn bộ thông tin, tài liệu trong đơn đăng ký này.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $otp = $this->queuedDocumentOtp($otpResponse->json('data.signing_request_id'));

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/user/partner-application/{$applicationId}/sign-document/verify-otp", [
                'signing_request_id' => $otpResponse->json('data.signing_request_id'),
                'otp' => $otp,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.application.status', 'submitted');

        Mail::assertQueued(PartnerApplicationReceivedMail::class, function (PartnerApplicationReceivedMail $mail) use ($applicationId): bool {
            return $mail->application->id === $applicationId;
        });

        $this->assertDatabaseHas('partner_applications', [
            'id' => $applicationId,
            'user_id' => $user->id,
            'venue_name' => 'SportGo Kiên Test',
            'venue_province' => 'Thành phố Hà Nội',
            'venue_ward' => 'Phường Thanh Xuân',
            'venue_province_code' => '1',
            'venue_ward_code' => '10101',
            'bank_code' => 'VCB',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('owner_bank_accounts', [
            'owner_id' => $user->id,
            'partner_application_id' => $applicationId,
            'bank_code' => 'VCB',
            'account_number' => '1234567890',
            'status' => 'pending',
        ]);
        $this->assertSame(2, PartnerApplicationCourt::query()->where('partner_application_id', $applicationId)->count());
        $this->assertSame(7, \App\Models\PartnerApplicationDocument::query()->where('partner_application_id', $applicationId)->count());
        $this->assertDatabaseHas('generated_documents', [
            'partner_application_id' => $applicationId,
            'document_type' => 'partner_application_form',
            'status' => 'completed',
        ]);

        $applicationDocument->refresh();

        $this->assertDocxContains($applicationDocument, [
            'Nguyễn Kiên',
            'SportGo Kiên Test',
            '1234567890',
            'Sân 1',
        ]);
        $this->assertDocxMissing($applicationDocument, [
            '{{full_name}}',
            '{{venue_name}}',
            '{{account_number}}',
        ]);
    }

    public function test_admin_rejects_partner_application_with_required_reason_and_email(): void
    {
        Mail::fake();

        $applicant = $this->createUser('rejected_partner', 'reject.partner@sportgo.vn');
        $application = PartnerApplication::query()->create([
            'user_id' => $applicant->id,
            'business_name' => 'Hộ kinh doanh cần bổ sung',
            'tax_code' => '0101234567',
            'venue_name' => 'SportGo Reject Test',
            'venue_address' => '1 Test, Hà Nội',
            'venue_map_url' => 'https://maps.google.com/?q=21.0278,105.8342',
            'venue_latitude' => 21.0278000,
            'venue_longitude' => 105.8342000,
            'venue_email' => 'reject.partner@sportgo.vn',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/partner-applications/{$application->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $reason = 'Thiếu hợp đồng thuê mặt bằng hợp lệ.';

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/partner-applications/{$application->id}/reject", [
                'reason' => $reason,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_reason', $reason);

        $this->assertDatabaseHas('partner_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'status_reason' => $reason,
        ]);

        Mail::assertQueued(PartnerApplicationRejectedMail::class, function (PartnerApplicationRejectedMail $mail) use ($application): bool {
            return $mail->application->id === $application->id;
        });
    }

    public function test_admin_can_approve_partner_application_and_create_venue_cluster(): void
    {
        Queue::fake();
        Mail::fake();

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
            ->assertJsonPath('data.status', 'contract_pending_sportgo_signature')
            ->assertJsonPath('data.approved_venue_cluster.name', 'SportGo Test Partner');

        $venueClusterId = $response->json('data.approved_venue_cluster_id');

        $this->assertDatabaseHas('partner_applications', [
            'id' => $application->id,
            'status' => 'contract_pending_sportgo_signature',
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

        $this->assertSame('pending_sportgo_signature', $contract->status);
        Mail::assertNotQueued(PartnerApplicationApprovedMail::class);

        $contractDocument = GeneratedDocument::query()
            ->where('partner_contract_id', $contract->id)
            ->where('document_type', 'partner_contract')
            ->firstOrFail();

        $this->assertDocxContains($contractDocument, [
            'Hộ kinh doanh sân test',
            'MST001',
            'partner_applicant',
        ]);
        $this->assertDocxMissing($contractDocument, [
            '{{party_b_name}}',
            '{{owner_email}}',
            '{{contract_number}}',
        ]);

        $adminOtpResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/contracts/{$contract->id}/approve-signature/request-otp", [
                'signature_image' => $this->signatureImage(),
                'confirmed' => true,
                'confirmation_text' => 'Tôi xác nhận đã kiểm tra toàn bộ nội dung hợp đồng và ký với vai trò đại diện SportGo.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $adminOtp = $this->queuedDocumentOtp($adminOtpResponse->json('data.signing_request_id'));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/contracts/{$contract->id}/approve-signature/verify-otp", [
                'signing_request_id' => $adminOtpResponse->json('data.signing_request_id'),
                'otp' => $adminOtp,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Mail::assertQueued(PartnerApplicationApprovedMail::class, function (PartnerApplicationApprovedMail $mail) use ($application): bool {
            return $mail->application->id === $application->id;
        });

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $applicant->id,
            'role_id' => $ownerRoleId,
            'scope_type' => 'system',
        ]);
        $this->assertDatabaseHas('partner_contracts', [
            'id' => $contract->id,
            'status' => 'pending_owner_signature',
        ]);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $venueClusterId,
            'status' => 'pending',
        ]);

        $ownerOtpResponse = $this->actingAs($applicant, 'sanctum')
            ->postJson('/api/user/partner-application/sign-contract/request-otp', [
                'contract_id' => $contract->id,
                'signature_image' => $this->signatureImage(),
                'confirmed' => true,
                'confirmation_text' => 'Tôi xác nhận đã đọc, hiểu rõ toàn bộ nội dung hợp đồng, đồng ý giao kết hợp đồng này với SportGo và xác nhận thông tin trong hợp đồng là đúng.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $ownerOtp = $this->queuedDocumentOtp($ownerOtpResponse->json('data.signing_request_id'));

        $this->actingAs($applicant, 'sanctum')
            ->postJson('/api/user/partner-application/sign-contract/verify-otp', [
                'signing_request_id' => $ownerOtpResponse->json('data.signing_request_id'),
                'otp' => $ownerOtp,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Mail::assertQueued(PartnerContractSignedByOwnerMail::class, function (PartnerContractSignedByOwnerMail $mail) use ($contract): bool {
            return $mail->contract->id === $contract->id;
        });

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $applicant->id,
            'role_id' => $ownerRoleId,
            'scope_type' => 'system',
        ]);
        $this->assertDatabaseHas('partner_contracts', [
            'id' => $contract->id,
            'status' => 'signed_active',
        ]);
        $this->assertDatabaseHas('venue_clusters', [
            'id' => $venueClusterId,
            'status' => 'active',
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

    private function signatureImage(): string
    {
        return 'data:image/png;base64,' . base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
    }

    private function queuedDocumentOtp(string $signingRequestId): string
    {
        $otp = null;

        Mail::assertQueued(PartnerDocumentOtpMail::class, function (PartnerDocumentOtpMail $mail) use ($signingRequestId, &$otp): bool {
            if ($mail->signingRequest->id !== $signingRequestId) {
                return false;
            }

            $otp = $mail->otp;

            return true;
        });

        $this->assertNotNull($otp, 'Không lấy được OTP ký văn bản từ mail fake.');

        return $otp;
    }

    private function assertDocxContains(GeneratedDocument $document, array $needles): void
    {
        $text = $this->docxText($document);

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $text);
        }
    }

    private function assertDocxMissing(GeneratedDocument $document, array $needles): void
    {
        $text = $this->docxText($document);

        foreach ($needles as $needle) {
            $this->assertStringNotContainsString($needle, $text);
        }
    }

    private function docxText(GeneratedDocument $document): string
    {
        $path = Storage::disk('local')->path($document->generated_file_path);
        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($path) === true, "Không mở được file DOCX: {$path}");

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        return html_entity_decode(strip_tags(str_replace(['</w:t><w:t>', '</w:t><w:t '], ' ', $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
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
