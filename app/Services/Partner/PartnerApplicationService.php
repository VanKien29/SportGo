<?php

namespace App\Services\Partner;

use App\Jobs\RevokeVenueOwnerRoleJob;
use App\Jobs\SendRevocationReminderJob;
use App\Mail\Partner\PartnerApplicationApprovedMail;
use App\Mail\Partner\PartnerApplicationReceivedMail;
use App\Mail\Partner\PartnerApplicationRejectedMail;
use App\Mail\Partner\PartnerApplicationSupplementRequiredMail;
use App\Mail\Partner\PartnerContractSignedByOwnerMail;
use App\Mail\Partner\PartnerTerminationConfirmedMail;
use App\Mail\Partner\PartnerTerminationReceivedMail;
use App\Mail\Partner\PartnerUnilateralTerminationMail;
use App\Models\AuditLog;
use App\Models\CourtType;
use App\Models\GeneratedDocument;
use App\Models\Media;
use App\Models\Notification;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerApplication;
use App\Models\PartnerApplicationCourt;
use App\Models\PartnerApplicationDocument;
use App\Models\PartnerApplicationStatusHistory;
use App\Models\PartnerContract;
use App\Models\PriceSlot;
use App\Models\PartnerSettlement;
use App\Models\PartnerSettlementItem;
use App\Models\PartnerTerminationDocument;
use App\Models\PartnerTerminationRequest;
use App\Models\PartnerTerminationStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerApplicationService
{
    private const ZERO_UUID = '00000000-0000-0000-0000-000000000000';
    private const REVIEWABLE_STATUSES = ['pending', 'reviewing', 'submitted'];

    public function __construct(
        private readonly PartnerDocumentService $documents,
        private readonly PartnerMailDispatcher $mail,
    ) {
    }

    public function submitApplication(User $user, array $data, ?Request $request = null, bool $requiresApplicationSignature = false): PartnerApplication
    {
        $hasOpenApplication = PartnerApplication::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($hasOpenApplication) {
            throw ValidationException::withMessages([
                'application' => 'Bạn đã có một hồ sơ đối tác đang xử lý hoặc đang hiệu lực. Vui lòng theo dõi hồ sơ hiện tại.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $request, $requiresApplicationSignature): PartnerApplication {
            $initialStatus = $requiresApplicationSignature ? 'draft' : 'submitted';
            $application = PartnerApplication::create([
                'user_id' => $user->id,
                'applicant_full_name' => $data['applicant_full_name'] ?? $user->full_name,
                'applicant_phone' => $data['applicant_phone'] ?? $user->phone,
                'applicant_email' => $data['applicant_email'] ?? $user->email,
                'applicant_birth_date' => $data['applicant_birth_date'] ?? null,
                'applicant_address' => $data['applicant_address'] ?? null,
                'applicant_type' => $data['applicant_type'] ?? 'individual',
                'representative_name' => $data['representative_name'] ?? ($data['applicant_full_name'] ?? $user->full_name),
                'representative_identity_type' => $data['representative_identity_type'] ?? 'cccd',
                'representative_identity_number' => $data['representative_identity_number'] ?? null,
                'representative_identity_issued_date' => $data['representative_identity_issued_date'] ?? null,
                'representative_identity_issued_place' => $data['representative_identity_issued_place'] ?? null,
                'representative_position' => $data['representative_position'] ?? null,
                'business_name' => $data['business_name'],
                'business_code' => $data['business_code'] ?? null,
                'tax_code' => $data['tax_code'] ?? null,
                'business_license_number' => $data['business_license_number'] ?? null,
                'business_address' => $data['business_address'] ?? ($data['venue_address'] ?? null),
                'business_representative_name' => $data['business_representative_name'] ?? ($data['representative_name'] ?? $user->full_name),
                'business_representative_position' => $data['business_representative_position'] ?? null,
                'venue_name' => $data['venue_name'],
                'venue_address' => $data['venue_address'],
                'venue_province' => $data['venue_province'] ?? null,
                'venue_province_code' => $data['venue_province_code'] ?? null,
                'venue_district' => $data['venue_district'] ?? null,
                'venue_district_code' => $data['venue_district_code'] ?? null,
                'venue_ward' => $data['venue_ward'] ?? null,
                'venue_ward_code' => $data['venue_ward_code'] ?? null,
                'venue_map_url' => $data['venue_map_url'] ?? null,
                'venue_latitude' => $data['venue_latitude'],
                'venue_longitude' => $data['venue_longitude'],
                'venue_phone' => $data['venue_phone'] ?? $user->phone,
                'venue_email' => $data['venue_email'] ?? $user->email,
                'venue_description' => $data['venue_description'] ?? null,
                'expected_opening_hours' => $data['expected_opening_hours'] ?? null,
                'parking_info' => $data['parking_info'] ?? null,
                'amenities' => $data['amenities'] ?? [],
                'court_count_total' => $data['court_count_total'] ?? count($data['courts'] ?? []),
                'base_price_per_hour' => (int) ($data['base_price_per_hour'] ?? 0),
                'status' => $initialStatus,
                'submitted_at' => $requiresApplicationSignature ? null : now(),
            ]);

            $application->forceFill([
                'bank_name' => $data['bank_name'] ?? null,
                'bank_code' => $data['bank_code'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_verification_status' => $data['bank_verification_status'] ?? 'pending',
                'bank_verified_at' => ($data['bank_verification_status'] ?? null) === 'verified' ? now() : null,
            ])->save();

            foreach ($data['courts'] ?? [] as $index => $court) {
                $courtType = CourtType::query()->find($court['court_type_id']);
                PartnerApplicationCourt::create([
                    'partner_application_id' => $application->id,
                    'court_type_id' => $court['court_type_id'],
                    'court_type_name_snapshot' => $courtType?->name,
                    'expected_court_count' => $court['expected_court_count'] ?? 1,
                    'name' => $court['name'],
                    'note' => $court['note'] ?? null,
                    'sort_order' => $court['sort_order'] ?? ($index + 1),
                ]);
            }

            $this->storeApplicationDocuments($application, $data['document_files'] ?? []);
            $this->storeBankAccountFromApplication($application, null, 'pending');
            $this->generateApplicationForm($application, $user, $data, $requiresApplicationSignature);

            if ($requiresApplicationSignature) {
                $this->applicationHistory($application, null, 'draft', $user, 'user', 'Tạo đơn đăng ký đối tác, chờ ký điện tử trước khi gửi.');
                $this->audit('partner_application_form_generated', $application, $user, 'user', null, ['status' => 'draft'], $request);
            } else {
                $this->markApplicationSubmitted($application, $user, $request);
            }

            return $application->fresh(['user', 'courts.courtType', 'documents', 'contracts', 'generatedDocuments']);
        });
    }

    public function updateDraftApplication(PartnerApplication $application, User $user, array $data, ?Request $request = null): PartnerApplication
    {
        if ($application->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền sửa hồ sơ này.');
        }

        if (! in_array($application->status, ['draft', 'need_supplement'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có hồ sơ nháp hoặc hồ sơ đang cần bổ sung mới được sửa và tạo lại đơn đăng ký.',
            ]);
        }

        return DB::transaction(function () use ($application, $user, $data, $request): PartnerApplication {
            $oldStatus = $application->status;
            $application->forceFill([
                'applicant_full_name' => $data['applicant_full_name'] ?? $user->full_name,
                'applicant_phone' => $data['applicant_phone'] ?? $user->phone,
                'applicant_email' => $data['applicant_email'] ?? $user->email,
                'applicant_birth_date' => $data['applicant_birth_date'] ?? null,
                'applicant_address' => $data['applicant_address'] ?? null,
                'applicant_type' => $data['applicant_type'] ?? 'individual',
                'representative_name' => $data['representative_name'] ?? ($data['applicant_full_name'] ?? $user->full_name),
                'representative_identity_type' => $data['representative_identity_type'] ?? 'cccd',
                'representative_identity_number' => $data['representative_identity_number'] ?? null,
                'representative_identity_issued_date' => $data['representative_identity_issued_date'] ?? null,
                'representative_identity_issued_place' => $data['representative_identity_issued_place'] ?? null,
                'representative_position' => $data['representative_position'] ?? null,
                'business_name' => $data['business_name'],
                'business_code' => $data['business_code'] ?? null,
                'tax_code' => $data['tax_code'] ?? null,
                'business_license_number' => $data['business_license_number'] ?? null,
                'business_address' => $data['business_address'] ?? ($data['venue_address'] ?? null),
                'business_representative_name' => $data['business_representative_name'] ?? ($data['representative_name'] ?? $user->full_name),
                'business_representative_position' => $data['business_representative_position'] ?? null,
                'venue_name' => $data['venue_name'],
                'venue_address' => $data['venue_address'],
                'venue_province' => $data['venue_province'] ?? null,
                'venue_province_code' => $data['venue_province_code'] ?? null,
                'venue_district' => $data['venue_district'] ?? null,
                'venue_district_code' => $data['venue_district_code'] ?? null,
                'venue_ward' => $data['venue_ward'] ?? null,
                'venue_ward_code' => $data['venue_ward_code'] ?? null,
                'venue_map_url' => $data['venue_map_url'] ?? null,
                'venue_latitude' => $data['venue_latitude'],
                'venue_longitude' => $data['venue_longitude'],
                'venue_phone' => $data['venue_phone'] ?? $user->phone,
                'venue_email' => $data['venue_email'] ?? $user->email,
                'venue_description' => $data['venue_description'] ?? null,
                'expected_opening_hours' => $data['expected_opening_hours'] ?? null,
                'parking_info' => $data['parking_info'] ?? null,
                'amenities' => $data['amenities'] ?? [],
                'court_count_total' => $data['court_count_total'] ?? count($data['courts'] ?? []),
                'base_price_per_hour' => (int) ($data['base_price_per_hour'] ?? 0),
                'status' => 'draft',
                'status_reason' => null,
            ])->save();

            $application->forceFill([
                'bank_name' => $data['bank_name'] ?? null,
                'bank_code' => $data['bank_code'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_verification_status' => $data['bank_verification_status'] ?? 'pending',
                'bank_verified_at' => ($data['bank_verification_status'] ?? null) === 'verified' ? now() : null,
            ])->save();

            $application->courts()->delete();
            foreach ($data['courts'] ?? [] as $index => $court) {
                $courtType = CourtType::query()->find($court['court_type_id']);
                PartnerApplicationCourt::create([
                    'partner_application_id' => $application->id,
                    'court_type_id' => $court['court_type_id'],
                    'court_type_name_snapshot' => $courtType?->name,
                    'expected_court_count' => $court['expected_court_count'] ?? 1,
                    'name' => $court['name'],
                    'note' => $court['note'] ?? null,
                    'sort_order' => $court['sort_order'] ?? ($index + 1),
                ]);
            }

            $documentFiles = $data['document_files'] ?? [];
            if (collect($documentFiles)->flatten()->isNotEmpty()) {
                $this->storeApplicationDocuments($application, $documentFiles, 'draft_update_' . now()->format('YmdHis'));
            }

            $application->generatedDocuments()
                ->where('document_type', 'partner_application_form')
                ->where('status', 'pending_owner_signature')
                ->update(['status' => 'cancelled']);

            $this->storeBankAccountFromApplication($application, null, 'pending');
            $this->generateApplicationForm($application->fresh(['courts.courtType', 'documents']), $user, $data, true);

            $this->applicationHistory($application, $oldStatus, 'draft', $user, 'user', 'Cập nhật thông tin hồ sơ và tạo lại đơn đăng ký đối tác.');
            $this->audit('partner_application_draft_updated', $application, $user, 'user', ['status' => $oldStatus], ['status' => 'draft'], $request);

            return $application->fresh($this->detailRelations());
        });
    }

    public function submitSignedApplication(PartnerApplication $application, User $user, ?Request $request = null): PartnerApplication
    {
        if ($application->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền gửi hồ sơ này.');
        }

        if ($application->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có thể gửi hồ sơ đang chờ ký đơn đăng ký.',
            ]);
        }

        $applicationForm = $application->generatedDocuments()
            ->where('document_type', 'partner_application_form')
            ->latest()
            ->first();

        $hasOwnerSignature = $applicationForm?->signatures()
            ->where('signer_side', 'owner')
            ->where('status', 'signed')
            ->exists();

        if (! $applicationForm || ! $hasOwnerSignature) {
            throw ValidationException::withMessages([
                'signature' => 'Bạn cần ký điện tử trên đơn đăng ký trước khi gửi hồ sơ.',
            ]);
        }

        return DB::transaction(function () use ($application, $user, $request): PartnerApplication {
            $this->markApplicationSubmitted($application, $user, $request, 'draft');

            return $application->fresh($this->detailRelations());
        });
    }

    public function previewApplicationForm(User $user, array $data): GeneratedDocument
    {
        return $this->documents->generateDocument('partner_application_form', $user, $this->applicationRenderDataFromArray($data), $user, [
            'status' => 'draft',
            'owner_id' => $user->id,
            'reference_type' => User::class,
            'reference_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'title' => 'Bản xem trước đơn đăng ký đối tác ' . ($data['venue_name'] ?? ''),
        ]);
    }

    private function markApplicationSubmitted(PartnerApplication $application, User $user, ?Request $request = null, ?string $oldStatus = null): void
    {
        $application->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'status_reason' => null,
        ])->save();

        $this->applicationHistory($application, $oldStatus, 'submitted', $user, 'user', 'Nộp hồ sơ đăng ký đối tác đã ký.');
        $this->audit('partner_application_submitted', $application, $user, 'user', $oldStatus ? ['status' => $oldStatus] : null, ['status' => 'submitted'], $request);
        $this->notifyAdmins('partner_application_submitted', 'Có hồ sơ đối tác mới', $application->venue_name . ' vừa gửi hồ sơ đăng ký đối tác.', $application->id);
        $this->mail->queue($application->venue_email ?? $user->email, new PartnerApplicationReceivedMail($application));
    }

    public function approve(PartnerApplication $application, User $admin, array $data, ?Request $request = null): PartnerApplication
    {
        if (! in_array($application->status, self::REVIEWABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ này đã được xử lý, không thể duyệt lại.',
            ]);
        }

        return DB::transaction(function () use ($application, $admin, $data, $request): PartnerApplication {
            $application->loadMissing(['user', 'courts.courtType', 'bankAccounts']);
            $oldStatus = $application->status;
            $cluster = $this->createVenueCluster($application);
            $this->createVenueCourts($application, $cluster, $data, 'inactive');
            $this->createInitialPriceSlots($application, $cluster);
            $this->activateBankAccounts($application, $admin->id);

            $application->forceFill([
                'status' => 'contract_pending_sportgo_signature',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'status_reason' => $data['review_note'] ?? 'Hồ sơ đã được duyệt, đang chờ SportGo ký hợp đồng trước khi gửi cho chủ sân.',
                'approved_venue_cluster_id' => $cluster->id,
            ])->save();

            $contract = $this->createContractForApplication($application->fresh(['user', 'courts.courtType']), $admin, $cluster);
            $application->forceFill(['current_contract_id' => $contract->id])->save();

            $this->applicationHistory($application, $oldStatus, 'contract_pending_sportgo_signature', $admin, 'admin', $data['review_note'] ?? null);
            $this->audit('partner_application_approved', $application, $admin, 'admin', ['status' => $oldStatus], ['status' => $application->status], $request);
            $this->audit('partner_contract_generated', $contract, $admin, 'admin', null, ['contract_code' => $contract->contract_code], $request);
            $this->notifyUser($application->user, 'partner_application_approved_pending_sportgo_signature', 'Hồ sơ đối tác đã được duyệt', 'SportGo đang ký hợp đồng đối tác trước khi gửi bạn xác nhận.', $application->id);

            return $application->fresh($this->detailRelations());
        });
    }

    public function reject(PartnerApplication $application, User $admin, string $reason, ?Request $request = null): PartnerApplication
    {
        if (! in_array($application->status, self::REVIEWABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ này đã được xử lý, không thể từ chối lại.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do từ chối.']);
        }

        return DB::transaction(function () use ($application, $admin, $reason, $request): PartnerApplication {
            $application->loadMissing('user');
            $oldStatus = $application->status;
            $application->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'status_reason' => $reason,
            ])->save();

            $this->applicationHistory($application, $oldStatus, 'rejected', $admin, 'admin', $reason);
            $this->audit('partner_application_rejected', $application, $admin, 'admin', ['status' => $oldStatus], ['status' => 'rejected'], $request, $reason);
            $this->notifyUser($application->user, 'partner_application_rejected', 'Hồ sơ đối tác bị từ chối', 'Lý do: ' . $reason, $application->id);

            $this->mail->queue($application->venue_email ?? $application->user->email, new PartnerApplicationRejectedMail($application));

            return $application->fresh($this->detailRelations());
        });
    }

    public function requireSupplement(PartnerApplication $application, User $admin, string $reason, ?Request $request = null): PartnerApplication
    {
        if (! in_array($application->status, self::REVIEWABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ này đã được xử lý, không thể yêu cầu bổ sung.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Vui lòng nhập nội dung cần bổ sung.']);
        }

        return DB::transaction(function () use ($application, $admin, $reason, $request): PartnerApplication {
            $application->loadMissing('user');
            $oldStatus = $application->status;
            $application->forceFill([
                'status' => 'need_supplement',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'status_reason' => $reason,
            ])->save();

            $this->applicationHistory($application, $oldStatus, 'need_supplement', $admin, 'admin', $reason);
            $this->audit('partner_application_need_supplement', $application, $admin, 'admin', ['status' => $oldStatus], ['status' => 'need_supplement'], $request, $reason);
            $this->notifyUser($application->user, 'partner_application_need_supplement', 'Hồ sơ đối tác cần bổ sung', 'Nội dung cần bổ sung: ' . $reason, $application->id);
            $this->mail->queue($application->venue_email ?? $application->user->email, new PartnerApplicationSupplementRequiredMail($application));

            return $application->fresh($this->detailRelations());
        });
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function submitSupplementDocuments(PartnerApplication $application, User $user, array $files, ?string $note = null, ?Request $request = null): PartnerApplication
    {
        if ($application->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền bổ sung hồ sơ này.');
        }

        if ($application->status !== 'need_supplement') {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có hồ sơ đang cần bổ sung mới được nộp thêm giấy tờ.',
            ]);
        }

        if (count($files) === 0) {
            throw ValidationException::withMessages([
                'additional_documents' => 'Vui lòng tải lên ít nhất một giấy tờ bổ sung.',
            ]);
        }

        return DB::transaction(function () use ($application, $user, $files, $note, $request): PartnerApplication {
            $oldStatus = $application->status;
            $this->storeApplicationDocuments($application, ['additional' => $files], 'supplement_' . now()->format('YmdHis'));

            $reason = trim((string) $note);
            $application->forceFill([
                'status' => 'submitted',
                'submitted_at' => now(),
                'status_reason' => $reason !== '' ? $reason : 'Người dùng đã bổ sung giấy tờ theo yêu cầu.',
            ])->save();

            $historyReason = $reason !== ''
                ? 'Người dùng bổ sung hồ sơ: ' . $reason
                : 'Người dùng đã bổ sung giấy tờ theo yêu cầu.';

            $this->applicationHistory($application, $oldStatus, 'submitted', $user, 'user', $historyReason);
            $this->audit('partner_application_supplement_submitted', $application, $user, 'user', ['status' => $oldStatus], ['status' => 'submitted'], $request, $historyReason);
            $this->notifyAdmins('partner_application_supplement_submitted', 'Hồ sơ đối tác đã được bổ sung', $application->venue_name . ' vừa nộp giấy tờ bổ sung.', $application->id);

            return $application->fresh($this->detailRelations());
        });
    }

    public function signOwnerContract(PartnerContract $contract, User $owner, Request $request, ?string $signatureImage = null): PartnerContract
    {
        if ($contract->owner_id !== $owner->id) {
            abort(403, 'Bạn không có quyền ký hợp đồng này.');
        }

        if ($contract->status !== 'pending_owner_signature') {
            throw ValidationException::withMessages(['status' => 'Hợp đồng không ở trạng thái chờ chủ sân ký.']);
        }

        return DB::transaction(function () use ($contract, $owner, $request, $signatureImage): PartnerContract {
            $contract->loadMissing(['application.user', 'generatedDocument', 'venueCluster']);
            $document = $contract->generatedDocument;
            if (! $document) {
                throw ValidationException::withMessages(['document' => 'Không tìm thấy văn bản hợp đồng để ký.']);
            }

            $ownerSignerName = $this->applicationSignerName($contract->application, $owner);
            $this->documents->signDocument($document, $owner, 'owner', $signatureImage, $request, [
                'signer_full_name' => $ownerSignerName,
                'signer_title' => $contract->application?->representative_position ?: 'Chủ sân',
                'signer_organization' => $contract->application?->business_name,
            ]);

            $hasSportgoSignature = $this->documentHasSignature($document->refresh(), 'sportgo');

            $contract->forceFill([
                'status' => $hasSportgoSignature ? 'signed_active' : 'pending_sportgo_signature',
                'owner_signed_at' => now(),
                'effective_from' => $contract->effective_from ?: now(),
                'effective_to' => $hasSportgoSignature ? ($contract->effective_to ?: now()->addYear()) : $contract->effective_to,
            ])->save();

            $contract->application?->forceFill([
                'status' => $hasSportgoSignature ? 'completed' : 'contract_pending_sportgo_signature',
            ])->save();

            $this->audit('partner_contract_signed_owner', $contract, $owner, 'owner', null, ['status' => $contract->status], $request);

            if ($hasSportgoSignature) {
                if ($contract->venue_cluster_id) {
                    VenueCluster::query()->whereKey($contract->venue_cluster_id)->update([
                        'status' => 'active',
                        'status_reason' => null,
                    ]);
                    VenueCourt::query()->where('venue_cluster_id', $contract->venue_cluster_id)->update(['status' => 'active']);
                }

                $this->grantVenueOwnerRole($owner->id, $owner->id);
                $this->applicationHistory($contract->application, 'contract_pending_owner_signature', 'completed', $owner, 'owner', 'Chủ sân đã ký hợp đồng. Hồ sơ hoàn tất.');
                $this->audit('venue_owner_role_granted', $contract->application, $owner, 'owner', null, ['user_id' => $owner->id], $request);
                $this->notifyUser($owner, 'partner_contract_signed_owner', 'Bạn đã trở thành đối tác/chủ sân của SportGo', 'Tài khoản đã được cấp quyền Chủ sân.', $contract->partner_application_id);
                $this->mail->queue($owner->email ?? $contract->application?->venue_email, new PartnerContractSignedByOwnerMail($contract->fresh(['application.user', 'generatedDocument'])));
            } else {
                $this->applicationHistory($contract->application, 'contract_pending_owner_signature', 'contract_pending_sportgo_signature', $owner, 'owner', 'Chủ sân đã ký hợp đồng, chờ SportGo ký xác nhận.');
                $this->notifyAdmins('partner_contract_signed_owner', 'Hợp đồng chờ SportGo ký', $contract->contract_code . ' đã được chủ sân ký.', $contract->partner_application_id);
            }

            return $contract->fresh(['application', 'generatedDocument.signatures', 'venueCluster']);
        });
    }

    public function signAdminContract(PartnerContract $contract, User $admin, Request $request, ?string $signatureImage = null): PartnerContract
    {
        if ($contract->status !== 'pending_sportgo_signature') {
            throw ValidationException::withMessages(['status' => 'Hợp đồng không ở trạng thái chờ SportGo ký.']);
        }

        return DB::transaction(function () use ($contract, $admin, $request, $signatureImage): PartnerContract {
            $contract->loadMissing(['application.user', 'generatedDocument']);
            $document = $contract->generatedDocument;
            if (! $document) {
                throw ValidationException::withMessages(['document' => 'Không tìm thấy văn bản hợp đồng để ký.']);
            }

            $this->documents->signDocument($document, $admin, 'sportgo', $signatureImage, $request, [
                'signer_title' => 'Đại diện SportGo',
                'signer_organization' => 'SportGo',
            ]);

            $hasOwnerSignature = $this->documentHasSignature($document->refresh(), 'owner');

            $contract->forceFill([
                'status' => $hasOwnerSignature ? 'signed_active' : 'pending_owner_signature',
                'approved_by' => $admin->id,
                'sportgo_signed_at' => now(),
                'effective_from' => $contract->effective_from ?: now(),
                'effective_to' => $contract->effective_to ?: now()->addYear(),
            ])->save();

            $contract->application?->forceFill([
                'status' => $hasOwnerSignature ? 'completed' : 'contract_pending_owner_signature',
            ])->save();

            $this->audit('partner_contract_signed_admin', $contract, $admin, 'admin', null, ['status' => $contract->status], $request);

            if ($hasOwnerSignature) {
                if ($contract->venue_cluster_id) {
                    VenueCluster::query()->whereKey($contract->venue_cluster_id)->update([
                        'status' => 'active',
                        'status_reason' => null,
                    ]);
                    VenueCourt::query()->where('venue_cluster_id', $contract->venue_cluster_id)->update(['status' => 'active']);
                }

                $this->grantVenueOwnerRole($contract->owner_id, $admin->id);
                $this->applicationHistory($contract->application, 'contract_pending_sportgo_signature', 'completed', $admin, 'admin', 'SportGo đã ký xác nhận hợp đồng. Hồ sơ hoàn tất.');
                $this->audit('venue_owner_role_granted', $contract->application, $admin, 'admin', null, ['user_id' => $contract->owner_id], $request);
                $this->notifyUser($contract->application->user, 'partner_contract_completed', 'Bạn đã trở thành đối tác/chủ sân của SportGo', 'Tài khoản đã được cấp quyền Chủ sân.', $contract->partner_application_id);
                $this->mail->queue($contract->application->user, new PartnerContractSignedByOwnerMail($contract->fresh(['application.user', 'generatedDocument'])));
            } else {
                $this->applicationHistory($contract->application, 'contract_pending_sportgo_signature', 'contract_pending_owner_signature', $admin, 'admin', 'SportGo đã ký hợp đồng, chờ chủ sân ký xác nhận.');
                $this->notifyUser($contract->application->user, 'partner_application_approved', 'Hồ sơ đối tác đã được duyệt', 'Vui lòng đăng nhập hệ thống để xem và ký giấy/hợp đồng đối tác.', $contract->partner_application_id);
                $this->mail->queue($contract->application->venue_email ?? $contract->application->user->email, new PartnerApplicationApprovedMail($contract->application->fresh(['user', 'contracts.generatedDocument.signatures'])));
            }

            return $contract->fresh(['application', 'generatedDocument.signatures']);
        });
    }

    public function requestTermination(PartnerContract $contract, User $owner, Request $request, string $reason, ?string $signatureImage = null): PartnerTerminationRequest
    {
        if ($contract->owner_id !== $owner->id) {
            abort(403, 'Bạn không có quyền yêu cầu chấm dứt hợp đồng này.');
        }

        if ($contract->status !== 'signed_active') {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể yêu cầu chấm dứt hợp đồng đang hiệu lực.']);
        }

        return DB::transaction(function () use ($contract, $owner, $request, $reason, $signatureImage): PartnerTerminationRequest {
            $contract->loadMissing(['application.user']);
            $termination = PartnerTerminationRequest::create([
                'termination_code' => $this->uniqueTerminationCode('OWNER'),
                'partner_contract_id' => $contract->id,
                'partner_application_id' => $contract->partner_application_id,
                'owner_id' => $owner->id,
                'venue_cluster_id' => $contract->venue_cluster_id,
                'termination_type' => 'mutual_agreement',
                'requested_by' => $owner->id,
                'requested_at' => now(),
                'reason' => $reason,
                'requested_effective_date' => now()->addDays(30)->toDateString(),
                'status' => 'submitted',
            ]);

            $document = $this->generateTerminationRequestDocument($termination, $contract, $owner);
            if ($signatureImage) {
                $ownerSignerName = $this->applicationSignerName($contract->application, $owner);
                $this->documents->signDocument($document, $owner, 'owner', $signatureImage, $request, [
                    'signer_full_name' => $ownerSignerName,
                    'signer_title' => $contract->application?->representative_position ?: 'Chủ sân',
                    'signer_organization' => $contract->application?->business_name,
                ]);
            }

            PartnerTerminationDocument::create([
                'partner_termination_request_id' => $termination->id,
                'generated_document_id' => $document->id,
                'document_type' => 'owner_termination_request',
                'file_path' => $document->generated_file_path,
                'status' => $signatureImage ? 'signed' : 'generated',
                'generated_by' => $owner->id,
                'generated_at' => now(),
            ]);

            $this->terminationHistory($termination, null, 'submitted', $owner, 'owner', $reason);
            $this->audit('termination_requested', $termination, $owner, 'owner', null, ['reason' => $reason], $request, $reason);
            $this->notifyAdmins('termination_requested', 'Có yêu cầu chấm dứt hợp tác', $contract->contract_code . ' vừa có yêu cầu chấm dứt.', $contract->partner_application_id);

            $this->mail->queue($owner, new PartnerTerminationReceivedMail([
                'owner_name' => $this->applicationSignerName($contract->application, $owner),
                'contract_code' => $contract->contract_code,
                'requested_at' => $this->timestamp($termination->requested_at),
                'reason' => $reason,
                'status_url' => url('/owner/partner-profile'),
            ]));

            return $termination->fresh(['documents.generatedDocument', 'contract', 'settlement']);
        });
    }

    public function confirmTermination(PartnerTerminationRequest $termination, User $admin, Request $request): PartnerTerminationRequest
    {
        if (! in_array($termination->status, ['submitted', 'reviewing'], true)) {
            throw ValidationException::withMessages(['status' => 'Yêu cầu chấm dứt không ở trạng thái chờ xác nhận.']);
        }

        return DB::transaction(function () use ($termination, $admin, $request): PartnerTerminationRequest {
            $termination->loadMissing(['contract.application.user']);
            $contract = $termination->contract;
            $transitionEndAt = now()->addDays(30);
            $oldStatus = $termination->status;

            $termination->forceFill([
                'status' => 'transition_period',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'effective_termination_date' => now(),
                'transition_end_at' => $transitionEndAt,
            ])->save();

            $settlement = $this->createSettlement($termination, $contract, $admin);
            $liquidationDoc = $this->generateLiquidationDocument($termination, $contract, $settlement, $admin);
            $settlementDoc = $this->generateSettlementDocument($termination, $contract, $settlement, $admin);

            foreach ([[$liquidationDoc, 'mutual_liquidation_minutes'], [$settlementDoc, 'settlement_minutes']] as [$document, $type]) {
                PartnerTerminationDocument::create([
                    'partner_termination_request_id' => $termination->id,
                    'generated_document_id' => $document->id,
                    'document_type' => $type,
                    'file_path' => $document->generated_file_path,
                    'status' => 'generated',
                    'generated_by' => $admin->id,
                    'generated_at' => now(),
                ]);
            }

            $this->scheduleRevocationJobs($termination);
            $this->terminationHistory($termination, $oldStatus, 'transition_period', $admin, 'admin', 'SportGo xác nhận yêu cầu chấm dứt.');
            $this->audit('termination_confirmed', $termination, $admin, 'admin', ['status' => $oldStatus], ['transition_end_at' => $transitionEndAt], $request);
            $this->notifyUser($contract->application->user, 'termination_confirmed', 'Yêu cầu chấm dứt đã được xác nhận', 'Thông tin quyết toán đã được ghi nhận.', $contract->partner_application_id);

            $this->mail->queue($contract->application->user, new PartnerTerminationConfirmedMail($this->terminationMailData($termination, $contract, $settlement, $admin)));

            return $termination->fresh(['documents.generatedDocument', 'contract.application.user', 'settlement.items', 'settlement.withdrawalRequests']);
        });
    }

    public function initiateUnilateralTermination(PartnerContract $contract, User $admin, Request $request, string $reason): PartnerTerminationRequest
    {
        if ($contract->status !== 'signed_active') {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể chấm dứt hợp đồng đang hiệu lực.']);
        }

        return DB::transaction(function () use ($contract, $admin, $request, $reason): PartnerTerminationRequest {
            $contract->loadMissing(['application.user']);
            $transitionEndAt = now()->addDays(30);
            $termination = PartnerTerminationRequest::create([
                'termination_code' => $this->uniqueTerminationCode('SPORTGO'),
                'partner_contract_id' => $contract->id,
                'partner_application_id' => $contract->partner_application_id,
                'owner_id' => $contract->owner_id,
                'venue_cluster_id' => $contract->venue_cluster_id,
                'termination_type' => 'unilateral_by_sportgo',
                'requested_by' => $admin->id,
                'requested_at' => now(),
                'reason' => $reason,
                'requested_effective_date' => $transitionEndAt->toDateString(),
                'status' => 'transition_period',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'effective_termination_date' => now(),
                'transition_end_at' => $transitionEndAt,
            ]);

            $settlement = $this->createSettlement($termination, $contract, $admin);
            $notice = $this->generateUnilateralNoticeDocument($termination, $contract, $settlement, $admin);
            $settlementDoc = $this->generateSettlementDocument($termination, $contract, $settlement, $admin);

            foreach ([[$notice, 'unilateral_notice'], [$settlementDoc, 'settlement_minutes']] as [$document, $type]) {
                PartnerTerminationDocument::create([
                    'partner_termination_request_id' => $termination->id,
                    'generated_document_id' => $document->id,
                    'document_type' => $type,
                    'file_path' => $document->generated_file_path,
                    'status' => 'generated',
                    'generated_by' => $admin->id,
                    'generated_at' => now(),
                ]);
            }

            $this->scheduleRevocationJobs($termination);
            $this->terminationHistory($termination, null, 'transition_period', $admin, 'admin', $reason);
            $this->audit('unilateral_termination_initiated', $termination, $admin, 'admin', null, ['reason' => $reason, 'transition_end_at' => $transitionEndAt], $request, $reason);
            $this->notifyUser($contract->application->user, 'unilateral_termination_initiated', 'SportGo thông báo chấm dứt hợp tác', 'Vui lòng xem thông tin quyết toán và thời hạn chuyển tiếp.', $contract->partner_application_id);

            $this->mail->queue($contract->application->user, new PartnerUnilateralTerminationMail([
                'owner_name' => $this->applicationSignerName($contract->application, $contract->application?->user),
                'contract_code' => $contract->contract_code,
                'issued_at' => $this->timestamp($termination->requested_at),
                'reason' => $reason,
                'revocation_date' => $this->timestamp($transitionEndAt),
                'refund_amount' => $this->money($settlement->final_payable_to_owner),
            ]));

            return $termination->fresh(['documents.generatedDocument', 'contract.application.user', 'settlement.items', 'settlement.withdrawalRequests']);
        });
    }

    public function revokeOwnerRole(PartnerTerminationRequest $termination): void
    {
        DB::transaction(function () use ($termination): void {
            $termination->loadMissing(['contract.application.user', 'settlement.withdrawalRequests']);
            $roleId = Role::query()->where('name', 'venue_owner')->value('id');
            if ($roleId) {
                UserRole::query()
                    ->where('user_id', $termination->owner_id)
                    ->where('role_id', $roleId)
                    ->where('scope_type', 'system')
                    ->where('scope_id', self::ZERO_UUID)
                    ->delete();
            }

            $termination->forceFill([
                'status' => 'completed',
                'owner_access_revoked_at' => now(),
            ])->save();

            $termination->contract?->forceFill([
                'status' => 'terminated',
                'terminated_at' => now(),
            ])->save();

            $termination->contract?->application?->forceFill([
                'terminated_at' => now(),
            ])->save();

            if ($termination->venue_cluster_id) {
                VenueCluster::query()->whereKey($termination->venue_cluster_id)->update([
                    'status' => 'locked',
                    'status_reason' => 'Quyền đối tác đã chấm dứt theo hồ sơ ' . $termination->termination_code,
                    'locked_at' => now(),
                ]);
                VenueCourt::query()->where('venue_cluster_id', $termination->venue_cluster_id)->update([
                    'status' => 'inactive',
                ]);
            }

            $this->terminationHistory($termination, 'transition_period', 'completed', null, 'system', 'Tự động thu hồi quyền chủ sân sau giai đoạn chuyển tiếp.');
            $this->audit('venue_owner_role_revoked', $termination, null, 'system', null, ['owner_id' => $termination->owner_id], null);
            $owner = $termination->contract?->application?->user;
            if ($owner) {
                $this->notifyUser($owner, 'venue_owner_role_revoked', 'Quyền đối tác đã chấm dứt', 'Tài khoản không còn quyền truy cập trang quản lý sân.', $termination->partner_application_id);
            }
        });
    }

    public function detailRelations(): array
    {
        return [
            'user:id,full_name,username,email,phone,status',
            'reviewedBy:id,full_name,username,email',
            'approvedVenueCluster:id,name,status,slug,address,status_reason',
            'courts.courtType:id,name',
            'documents.media',
            'generatedDocuments.signatures.signer:id,full_name,email',
            'bankAccounts',
            'statusHistories.changedBy:id,full_name,username,email',
            'contracts.generatedDocument.signatures.signer:id,full_name,email',
            'contracts.terminations.documents.generatedDocument',
            'contracts.terminations.settlement.items',
            'terminationRequests.documents.generatedDocument',
            'terminationRequests.settlement.items',
            'terminationRequests.settlement.withdrawalRequests',
        ];
    }

    private function documentHasSignature(GeneratedDocument $document, string $side): bool
    {
        return $document->signatures()
            ->where('signer_side', $side)
            ->where('status', 'signed')
            ->exists();
    }

    private function applicationSignerName(?PartnerApplication $application, ?User $fallbackUser = null): ?string
    {
        return $application?->representative_name
            ?: $application?->applicant_full_name
            ?: $fallbackUser?->full_name
            ?: $fallbackUser?->username
            ?: $fallbackUser?->email;
    }

    private function applicationDisplayName(?PartnerApplication $application, ?User $fallbackUser = null): ?string
    {
        return $application?->business_name ?: $this->applicationSignerName($application, $fallbackUser);
    }

    private function applicationContactPhone(?PartnerApplication $application, ?User $fallbackUser = null): ?string
    {
        return $application?->applicant_phone ?: $application?->venue_phone ?: $fallbackUser?->phone;
    }

    private function applicationContactEmail(?PartnerApplication $application, ?User $fallbackUser = null): ?string
    {
        return $application?->applicant_email ?: $application?->venue_email ?: $fallbackUser?->email;
    }

    public function cancelApplication(PartnerApplication $application, User $user, ?string $reason = null, ?Request $request = null): PartnerApplication
    {
        if ($application->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền hủy hồ sơ này.');
        }

        if (! in_array($application->status, ['draft', 'pending', 'submitted', 'reviewing', 'need_supplement'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ này đã được xử lý, không thể hủy.',
            ]);
        }

        return DB::transaction(function () use ($application, $user, $reason, $request): PartnerApplication {
            $oldStatus = $application->status;
            $application->forceFill([
                'status' => 'cancelled',
                'status_reason' => $reason ?: 'Người dùng hủy hồ sơ đăng ký đối tác.',
            ])->save();

            $this->applicationHistory($application, $oldStatus, 'cancelled', $user, 'user', $application->status_reason);
            $this->audit('partner_application_cancelled', $application, $user, 'user', ['status' => $oldStatus], ['status' => 'cancelled'], $request, $application->status_reason);

            return $application->fresh($this->detailRelations());
        });
    }

    private function createVenueCluster(PartnerApplication $application): VenueCluster
    {
        if ($application->approved_venue_cluster_id) {
            $cluster = VenueCluster::query()->find($application->approved_venue_cluster_id);
            if ($cluster) {
                return $cluster;
            }
        }

        return VenueCluster::create([
                'owner_id' => $application->user_id,
                'name' => $application->venue_name,
                'slug' => $this->uniqueVenueSlug($application->venue_name),
                'description' => $application->venue_description ?: $application->business_name,
                'phone_contact' => $application->venue_phone ?: $application->user?->phone,
                'province' => $application->venue_province,
                'province_code' => $application->venue_province_code,
                'district_code' => $application->venue_district_code,
                'ward' => $application->venue_ward,
                'ward_code' => $application->venue_ward_code,
                'address' => $application->venue_address,
                'map_url' => $application->venue_map_url,
                'latitude' => $application->venue_latitude,
                'longitude' => $application->venue_longitude,
                'amenities' => $application->amenities,
                'status' => 'pending',
                'status_reason' => 'Chờ chủ sân ký hợp đồng hợp tác.',
        ]);
    }

    private function createVenueCourts(PartnerApplication $application, VenueCluster $cluster, array $data, string $status): void
    {
        if ($application->courts->isEmpty()) {
            VenueCourt::firstOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => $data['initial_court_name'] ?? 'Sân 1',
                ],
                [
                    'court_type_id' => $data['court_type_id'],
                    'status' => $status,
                    'sort_order' => 1,
                ]
            );

            return;
        }

        foreach ($application->courts as $index => $court) {
            VenueCourt::firstOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'name' => $court->name,
                ],
                [
                    'court_type_id' => $court->court_type_id,
                    'status' => $status,
                    'sort_order' => $court->sort_order ?: ($index + 1),
                ]
            );
        }
    }

    private function createInitialPriceSlots(PartnerApplication $application, VenueCluster $cluster): void
    {
        $basePrice = (int) $application->base_price_per_hour;
        if ($basePrice <= 0) {
            return;
        }

        $courtTypeIds = $application->courts
            ->pluck('court_type_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($courtTypeIds as $courtTypeId) {
            PriceSlot::query()->firstOrCreate(
                [
                    'venue_cluster_id' => $cluster->id,
                    'court_type_id' => $courtTypeId,
                    'booking_type' => 'all',
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:00',
                ],
                [
                    'price' => $basePrice,
                    'apply_to_days' => [1, 2, 3, 4, 5, 6, 7],
                    'is_active' => true,
                ]
            );
        }
    }

    private function createContractForApplication(PartnerApplication $application, User $admin, VenueCluster $cluster): PartnerContract
    {
        $contractCode = $this->uniqueContractCode();
        $renderData = $this->contractRenderData($application, $contractCode);
        $document = $this->documents->generateDocument('partner_contract', $application, $renderData, $admin, [
            'status' => 'pending_sportgo_signature',
            'partner_application_id' => $application->id,
            'owner_id' => $application->user_id,
            'venue_cluster_id' => $cluster->id,
            'title' => 'Hợp đồng hợp tác đối tác ' . $application->venue_name,
        ]);

        $contract = PartnerContract::create([
            'contract_code' => $contractCode,
            'partner_application_id' => $application->id,
            'owner_id' => $application->user_id,
            'venue_cluster_id' => $cluster->id,
            'contract_title' => 'Hợp đồng hợp tác đối tác ' . $application->venue_name,
            'status' => 'pending_sportgo_signature',
            'generated_document_id' => $document->id,
            'generated_by' => $admin->id,
            'approved_by' => $admin->id,
            'effective_from' => now(),
            'effective_to' => now()->addYear(),
        ]);

        $document->forceFill([
            'partner_contract_id' => $contract->id,
            'reference_type' => PartnerContract::class,
            'reference_id' => $contract->id,
            'entity_type' => PartnerContract::class,
            'entity_id' => $contract->id,
        ])->save();

        return $contract;
    }

    private function storeApplicationDocuments(PartnerApplication $application, array $documentFiles, ?string $batch = null): void
    {
        $definitions = [
            'identity' => [
                'group' => 'legal_identity',
                'title' => 'CCCD/CMND/Hộ chiếu người đại diện',
                'description' => 'Giấy tờ định danh của người đại diện đăng ký đối tác.',
            ],
            'business_license' => [
                'group' => 'business_license',
                'title' => 'Giấy đăng ký kinh doanh hoặc giấy tờ pháp lý cơ sở',
                'description' => 'Tài liệu chứng minh quyền kinh doanh/quản lý cơ sở sân.',
            ],
            'facility' => [
                'group' => 'facility_images',
                'title' => 'Hình ảnh cơ sở sân',
                'description' => 'Ảnh tổng quan, mặt sân, khu vực phụ trợ và biển hiệu nếu có.',
            ],
            'bank' => [
                'group' => 'bank_documents',
                'title' => 'Chứng từ ngân hàng',
                'description' => 'Tài liệu chứng minh hoặc xác nhận tài khoản ngân hàng nhận thanh toán.',
            ],
            'lease' => [
                'group' => 'lease_contract',
                'title' => 'Hợp đồng hoặc giấy tờ thuê mặt bằng',
                'description' => 'Tài liệu chứng minh quyền sử dụng hoặc khai thác mặt bằng cụm sân.',
            ],
            'additional' => [
                'group' => 'additional_documents',
                'title' => 'Tài liệu bổ sung',
                'description' => 'Tài liệu khác người đăng ký muốn SportGo xem xét thêm.',
            ],
        ];

        foreach ($definitions as $type => $definition) {
            foreach ($documentFiles[$type] ?? [] as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $folder = 'partner-applications/' . $application->id . '/' . $type . ($batch ? '/' . $batch : '');
                $path = $file->store($folder, 'public');
                $media = Media::query()->create([
                    'mediable_type' => PartnerApplication::class,
                    'mediable_id' => $application->id,
                    'collection' => 'partner_application_' . $type,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'sort_order' => $index + 1,
                ]);

                PartnerApplicationDocument::query()->create([
                    'partner_application_id' => $application->id,
                    'media_id' => $media->id,
                    'document_type' => $type,
                    'document_group' => $definition['group'],
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'file_path' => $path,
                    'status' => 'uploaded',
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }

    private function generateApplicationForm(PartnerApplication $application, User $actor, array $data = [], bool $requiresSignature = false): GeneratedDocument
    {
        $application->loadMissing(['user', 'courts.courtType', 'documents']);
        $ownerSignerName = $application->representative_name ?: $application->applicant_full_name ?: ($application->user?->full_name ?: $application->user?->username);

        return $this->documents->generateDocument('partner_application_form', $application, [
            'full_name' => $application->applicant_full_name,
            'id_number' => $application->representative_identity_number,
            'phone' => $application->applicant_phone,
            'email' => $application->applicant_email,
            'applicant_type' => $application->applicant_type,
            'applicant_birth_date' => $application->applicant_birth_date?->format('d/m/Y'),
            'sportgo_company_name' => 'Công ty TNHH SportGo',
            'sportgo_tax_code' => '0000000000',
            'sportgo_address' => 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội',
            'sportgo_representative' => 'Nguyễn Đức Kiên',
            'sportgo_representative_position' => 'Giám đốc',
            'location_date' => ($application->venue_province ?? 'Hà Nội') . ', ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'),
            'applicant_address' => $application->applicant_address,
            'representative_name' => $application->representative_name,
            'owner_full_name' => $ownerSignerName,
            'owner_signer_full_name' => $ownerSignerName,
            'business_representative_name' => $application->business_representative_name ?: $application->representative_name,
            'representative_identity_type' => $application->representative_identity_type,
            'representative_identity_number' => $application->representative_identity_number,
            'representative_identity_issued_date' => $application->representative_identity_issued_date?->format('d/m/Y'),
            'representative_identity_issued_place' => $application->representative_identity_issued_place,
            'representative_position' => $application->representative_position,
            'venue_name' => $application->venue_name,
            'venue_address' => $application->venue_address,
            'venue_province' => $application->venue_province,
            'venue_ward' => $application->venue_ward,
            'venue_map_url' => $application->venue_map_url,
            'venue_latitude' => $application->venue_latitude,
            'venue_longitude' => $application->venue_longitude,
            'court_count' => $application->court_count_total,
            'court_count_total' => $application->court_count_total,
            'base_price_per_hour' => (int) ($application->base_price_per_hour ?: ($data['base_price_per_hour'] ?? 0)),
            'base_price_per_hour_label' => $this->money((int) ($application->base_price_per_hour ?: ($data['base_price_per_hour'] ?? 0))),
            'courts_summary' => $this->courtsSummary($application),
            'submitted_at' => $this->timestamp($application->submitted_at),
            'applicant_full_name' => $application->applicant_full_name,
            'business_name' => $application->business_name,
            'business_license_number' => $application->business_license_number,
            'business_address' => $application->business_address,
            'tax_code' => $application->tax_code,
            'bank_name' => $application->bank_name,
            'account_number' => $application->account_number,
            'account_holder_name' => $application->account_holder_name,
            'bank_verification_status' => $application->bank_verification_status,
            'bank_verification_label' => $application->bank_verification_status === 'verified'
                ? 'Đã xác minh tự động'
                : 'Chưa xác minh ngân hàng',
            'bank_verified_at' => $this->timestamp($application->bank_verified_at),
            'attachments' => $application->documents->pluck('title')->unique()->implode(', '),
            'id_issued_info' => ($application->representative_identity_issued_date ? $application->representative_identity_issued_date->format('d/m/Y') : '...') . ', ' . ($application->representative_identity_issued_place ?: '...'),
            'venue_phone' => $application->venue_phone ?: $application->applicant_phone,
            'court_types' => $application->courts->pluck('courtType.name')->filter()->unique()->implode(', '),
            'amenities' => is_array($application->amenities) ? implode(', ', $application->amenities) : ($application->amenities ?: 'Không có'),
            'venue_description' => $application->venue_description,
            'expected_opening_hours' => $application->expected_opening_hours ?: '...',
            'bank_branch' => $application->bank_branch ?: 'Không có',
        ], $actor, [
            'partner_application_id' => $application->id,
            'owner_id' => $application->user_id,
            'title' => 'Đơn đăng ký đối tác ' . $application->venue_name,
            'status' => $requiresSignature ? 'pending_owner_signature' : 'generated',
        ]);
    }

    private function courtNote(array $court): ?string
    {
        $note = [
            'base_price' => isset($court['base_price']) ? (int) $court['base_price'] : null,
            'note' => $court['note'] ?? null,
        ];

        $note = array_filter($note, fn ($value) => $value !== null && $value !== '');

        return $note === [] ? null : json_encode($note, JSON_UNESCAPED_UNICODE);
    }

    private function courtsSummary(PartnerApplication $application): string
    {
        return $application->courts
            ->map(function (PartnerApplicationCourt $court): string {
                $note = json_decode((string) $court->note, true) ?: [];
                $price = isset($note['base_price'])
                    ? ' - giá cơ bản ' . number_format((int) $note['base_price'], 0, ',', '.') . 'đ'
                    : '';
                $typeName = $court->courtType?->name ?: $court->court_type_name_snapshot ?: 'Loại sân';

                return trim($court->name . ' (' . $typeName . ')' . $price);
            })
            ->filter()
            ->implode('; ');
    }

    private function applicationRenderDataFromArray(array $data): array
    {
        $basePrice = (int) ($data['base_price_per_hour'] ?? 0);

        return [
            'full_name' => $data['applicant_full_name'] ?? null,
            'id_number' => $data['representative_identity_number'] ?? null,
            'phone' => $data['applicant_phone'] ?? null,
            'email' => $data['applicant_email'] ?? null,
            'applicant_full_name' => $data['applicant_full_name'] ?? null,
            'applicant_phone' => $data['applicant_phone'] ?? null,
            'applicant_email' => $data['applicant_email'] ?? null,
            'applicant_birth_date' => isset($data['applicant_birth_date'])
                ? Carbon::parse($data['applicant_birth_date'])->format('d/m/Y')
                : null,
            'sportgo_company_name' => 'Công ty TNHH SportGo',
            'sportgo_tax_code' => '0000000000',
            'sportgo_address' => 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội',
            'sportgo_representative' => 'Nguyễn Đức Kiên',
            'sportgo_representative_position' => 'Giám đốc',
            'location_date' => ($data['venue_province'] ?? 'Hà Nội') . ', ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'),
            'applicant_address' => $data['applicant_address'] ?? null,
            'applicant_type' => $data['applicant_type'] ?? null,
            'representative_name' => $data['representative_name'] ?? null,
            'owner_full_name' => $data['representative_name'] ?? ($data['applicant_full_name'] ?? null),
            'owner_signer_full_name' => $data['representative_name'] ?? ($data['applicant_full_name'] ?? null),
            'business_representative_name' => $data['business_representative_name'] ?? ($data['representative_name'] ?? null),
            'representative_identity_type' => $data['representative_identity_type'] ?? null,
            'representative_identity_number' => $data['representative_identity_number'] ?? null,
            'representative_identity_issued_date' => $data['representative_identity_issued_date'] ?? null,
            'representative_identity_issued_place' => $data['representative_identity_issued_place'] ?? null,
            'representative_position' => $data['representative_position'] ?? null,
            'business_name' => $data['business_name'] ?? null,
            'business_code' => $data['business_code'] ?? null,
            'business_license_number' => $data['business_license_number'] ?? null,
            'business_address' => $data['business_address'] ?? null,
            'tax_code' => $data['tax_code'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'venue_province' => $data['venue_province'] ?? null,
            'venue_district' => null,
            'venue_ward' => $data['venue_ward'] ?? null,
            'venue_map_url' => $data['venue_map_url'] ?? null,
            'venue_latitude' => $data['venue_latitude'] ?? null,
            'venue_longitude' => $data['venue_longitude'] ?? null,
            'venue_phone' => $data['venue_phone'] ?? null,
            'venue_email' => $data['venue_email'] ?? null,
            'venue_description' => $data['venue_description'] ?? null,
            'court_count' => $data['court_count_total'] ?? count($data['courts'] ?? []),
            'court_count_total' => $data['court_count_total'] ?? count($data['courts'] ?? []),
            'base_price_per_hour' => $basePrice,
            'base_price_per_hour_label' => $this->money($basePrice),
            'courts_summary' => $this->courtsSummaryFromArray($data['courts'] ?? []),
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_verification_status' => $data['bank_verification_status'] ?? 'pending',
            'bank_verification_label' => ($data['bank_verification_status'] ?? 'pending') === 'verified'
                ? 'Đã xác minh tự động'
                : 'Chưa xác minh ngân hàng',
            'bank_verified_at' => $data['bank_verified_at'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'submitted_at' => $data['submitted_at'] ?? $this->timestamp(now()),
            'id_issued_info' => $this->joinFilledForDocument([
                $data['representative_identity_issued_date'] ?? null,
                $data['representative_identity_issued_place'] ?? null,
            ], ' - '),
            'court_types' => $this->courtTypesSummaryFromArray($data['courts'] ?? []),
            'amenities' => is_array($data['amenities'] ?? null) ? implode(', ', $data['amenities']) : ($data['amenities'] ?? null),
            'expected_opening_hours' => $data['expected_opening_hours'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
        ];
    }

    private function joinFilledForDocument(array $values, string $separator = ', '): ?string
    {
        $items = collect($values)
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
            ->filter()
            ->unique()
            ->values();

        return $items->isEmpty() ? null : $items->implode($separator);
    }

    private function courtTypesSummaryFromArray(array $courts): ?string
    {
        $names = collect($courts)
            ->map(function (array $court): ?string {
                if (! empty($court['court_type_name'])) {
                    return $court['court_type_name'];
                }

                if (! empty($court['court_type_name_snapshot'])) {
                    return $court['court_type_name_snapshot'];
                }

                if (! empty($court['court_type_id'])) {
                    return CourtType::query()->whereKey($court['court_type_id'])->value('name');
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    private function courtsSummaryFromArray(array $courts): string
    {
        return collect($courts)
            ->map(function (array $court): string {
                $typeName = $court['court_type_name'] ?? $court['court_type_name_snapshot'] ?? null;
                if (! $typeName && isset($court['court_type_id'])) {
                    $typeName = CourtType::query()->whereKey($court['court_type_id'])->value('name');
                }

                return trim(($court['name'] ?? 'Sân con') . ' (' . ($typeName ?: 'Loại sân') . ')');
            })
            ->filter()
            ->implode('; ');
    }

    private function generateTerminationRequestDocument(PartnerTerminationRequest $termination, PartnerContract $contract, User $actor): GeneratedDocument
    {
        $application = $contract->application;
        $ownerSignerName = $this->applicationSignerName($application, $actor);
        $partnerDisplayName = $this->applicationDisplayName($application, $actor);

        return $this->documents->generateDocument('termination_request', $termination, [
            'full_name' => $ownerSignerName,
            'business_name' => $application?->business_name,
            'representative_name' => $ownerSignerName,
            'owner_full_name' => $ownerSignerName,
            'owner_signer_full_name' => $ownerSignerName,
            'party_b_name' => $partnerDisplayName,
            'party_b_id' => $application?->tax_code ?: $application?->representative_identity_number,
            'party_b_address' => $application?->business_address ?: $application?->venue_address,
            'owner_phone' => $this->applicationContactPhone($application, $actor),
            'owner_email' => $this->applicationContactEmail($application, $actor),
            'venue_name' => $application?->venue_name,
            'contract_number' => $contract->contract_code,
            'contract_code' => $contract->contract_code,
            'termination_reason' => $termination->reason,
            'reason' => $termination->reason,
            'request_date' => $this->timestamp($termination->requested_at),
            'termination_code' => $termination->termination_code,
            'requested_at' => $this->timestamp($termination->requested_at),
            'requested_by' => $ownerSignerName,
            'termination_type' => 'Chấm dứt theo yêu cầu chủ sân',
            'requested_effective_date' => $termination->requested_effective_date?->format('d/m/Y'),
            'owner_bank_account_snapshot' => $this->bankSnapshot($application),
            'owner_signed_at' => $this->timestamp(now()),
        ], $actor, [
            'partner_application_id' => $contract->partner_application_id,
            'partner_contract_id' => $contract->id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
        ]);
    }

    private function generateLiquidationDocument(PartnerTerminationRequest $termination, PartnerContract $contract, PartnerSettlement $settlement, User $admin): GeneratedDocument
    {
        $application = $contract->application;
        $ownerSignerName = $this->applicationSignerName($application, $application?->user);
        $partnerDisplayName = $this->applicationDisplayName($application, $application?->user);

        return $this->documents->generateDocument('mutual_liquidation_minutes', $termination, [
            'termination_date' => $this->timestamp($termination->effective_termination_date),
            'party_a_rep' => $admin->full_name ?: $admin->username,
            'party_b_name' => $partnerDisplayName,
            'business_name' => $application?->business_name,
            'representative_name' => $ownerSignerName,
            'owner_full_name' => $ownerSignerName,
            'owner_signer_full_name' => $ownerSignerName,
            'party_b_id' => $application?->tax_code ?: $application?->representative_identity_number,
            'party_b_address' => $application?->business_address ?: $application?->venue_address,
            'owner_phone' => $this->applicationContactPhone($application, $application?->user),
            'owner_email' => $this->applicationContactEmail($application, $application?->user),
            'settlement_table' => $this->settlementTableText($settlement),
            'effective_date' => $this->timestamp($termination->transition_end_at),
            'liquidation_minutes_code' => 'BBTL-' . $termination->termination_code,
            'contract_code' => $contract->contract_code,
            'termination_request_code' => $termination->termination_code,
            'termination_reason' => $termination->reason,
            'agreed_termination_date' => $this->timestamp($termination->transition_end_at),
            'venue_name' => $application?->venue_name,
            'court_count_total' => $application?->court_count_total,
            'owner_wallet_available_amount' => $this->money($settlement->owner_wallet_available_amount),
            'unpaid_platform_fee_amount' => $this->money($settlement->unpaid_platform_fee_amount),
            'final_payable_to_owner' => $this->money($settlement->final_payable_to_owner),
            'final_receivable_from_owner' => $this->money($settlement->final_receivable_from_owner),
            'owner_access_revocation_date' => $this->timestamp($termination->transition_end_at),
        ], $admin, [
            'partner_application_id' => $contract->partner_application_id,
            'partner_contract_id' => $contract->id,
            'partner_termination_request_id' => $termination->id,
            'partner_settlement_id' => $settlement->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
        ]);
    }

    private function generateUnilateralNoticeDocument(PartnerTerminationRequest $termination, PartnerContract $contract, PartnerSettlement $settlement, User $admin): GeneratedDocument
    {
        $application = $contract->application;
        $ownerSignerName = $this->applicationSignerName($application, $application?->user);
        $partnerDisplayName = $this->applicationDisplayName($application, $application?->user);

        return $this->documents->generateDocument('unilateral_termination_notice', $termination, [
            'document_number' => 'CV-' . $termination->termination_code,
            'notice_code' => 'CV-' . $termination->termination_code,
            'issue_date' => $this->timestamp($termination->requested_at),
            'issued_at' => $this->timestamp($termination->requested_at),
            'issuer_side' => 'SportGo',
            'receiver_name' => $partnerDisplayName,
            'venue_owner_name' => $ownerSignerName,
            'business_name' => $application?->business_name,
            'representative_name' => $ownerSignerName,
            'owner_full_name' => $ownerSignerName,
            'owner_signer_full_name' => $ownerSignerName,
            'party_b_name' => $partnerDisplayName,
            'party_b_id' => $application?->tax_code ?: $application?->representative_identity_number,
            'party_b_address' => $application?->business_address ?: $application?->venue_address,
            'owner_phone' => $this->applicationContactPhone($application, $application?->user),
            'owner_email' => $this->applicationContactEmail($application, $application?->user),
            'contract_code' => $contract->contract_code,
            'venue_name' => $application?->venue_name,
            'legal_basis_text' => 'Theo điều khoản chấm dứt hợp tác trong hợp đồng đã ký.',
            'termination_reason' => $termination->reason,
            'effective_date' => $this->timestamp($termination->transition_end_at),
            'effective_termination_date' => $this->timestamp($termination->transition_end_at),
            'transition_end_at' => $this->timestamp($termination->transition_end_at),
            'required_actions' => 'Hoàn tất bàn giao, rút toàn bộ số dư khỏi ví chủ sân và xử lý các booking còn tồn tại bằng hoàn tiền mặt nếu cần.',
            'settlement_deadline' => $this->timestamp(now()->addDays(14)),
            'issuer_representative_name' => $admin->full_name ?: $admin->username,
        ], $admin, [
            'partner_application_id' => $contract->partner_application_id,
            'partner_contract_id' => $contract->id,
            'partner_termination_request_id' => $termination->id,
            'partner_settlement_id' => $settlement->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
        ]);
    }

    private function generateSettlementDocument(PartnerTerminationRequest $termination, PartnerContract $contract, PartnerSettlement $settlement, User $admin): GeneratedDocument
    {
        $application = $contract->application;
        $ownerSignerName = $this->applicationSignerName($application, $application?->user);
        $partnerDisplayName = $this->applicationDisplayName($application, $application?->user);

        return $this->documents->generateDocument('settlement_minutes', $settlement, [
            'total_paid' => $this->money($settlement->getAttribute('calculation_total_paid') ?? $settlement->platform_fee_remaining_refund_amount),
            'months_used' => $settlement->getAttribute('calculation_months_used') ?? 0,
            'months_remaining' => $settlement->getAttribute('calculation_months_remaining') ?? 0,
            'refund_amount' => $this->money($settlement->final_payable_to_owner),
            'bank_account' => $this->bankSnapshot($application),
            'bank_name' => $application?->bank_name,
            'account_number' => $application?->account_number,
            'account_holder_name' => $application?->account_holder_name,
            'calculation_date' => $this->timestamp(now()),
            'settlement_code' => $settlement->settlement_code,
            'settlement_date' => $this->timestamp(now()),
            'contract_code' => $contract->contract_code,
            'termination_request_code' => $termination->termination_code,
            'owner_full_name' => $ownerSignerName,
            'owner_signer_full_name' => $ownerSignerName,
            'representative_name' => $ownerSignerName,
            'business_name' => $application?->business_name,
            'party_b_name' => $partnerDisplayName,
            'venue_name' => $application?->venue_name,
            'owner_wallet_available_amount' => $this->money($settlement->owner_wallet_available_amount),
            'platform_fee_remaining_refund_amount' => $this->money($settlement->platform_fee_remaining_refund_amount),
            'unpaid_platform_fee_amount' => $this->money($settlement->unpaid_platform_fee_amount),
            'penalty_amount' => $this->money($settlement->penalty_amount),
            'adjustment_amount' => $this->money($settlement->adjustment_amount),
            'final_payable_to_owner' => $this->money($settlement->final_payable_to_owner),
            'final_receivable_from_owner' => $this->money($settlement->final_receivable_from_owner),
            'settlement_items' => $this->settlementTableText($settlement),
            'withdrawal_code' => $settlement->withdrawalRequests()->latest()->value('request_code'),
            'withdrawal_status' => $settlement->withdrawalRequests()->latest()->value('status'),
        ], $admin, [
            'partner_application_id' => $contract->partner_application_id,
            'partner_contract_id' => $contract->id,
            'partner_termination_request_id' => $termination->id,
            'partner_settlement_id' => $settlement->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
        ]);
    }

    private function createSettlement(PartnerTerminationRequest $termination, PartnerContract $contract, User $admin): PartnerSettlement
    {
        $calculation = $this->calculatePlatformFeeRefund($contract);
        $wallet = OwnerWallet::query()
            ->where('owner_id', $contract->owner_id)
            ->where('venue_cluster_id', $contract->venue_cluster_id)
            ->lockForUpdate()
            ->first();
        $ownerWalletAvailable = max(0, (float) ($wallet?->available_balance ?? 0));
        $ownerWalletPending = max(0, (float) ($wallet?->pending_withdrawal_balance ?? 0));
        $finalPayableToOwner = $ownerWalletAvailable + (float) $calculation['refund_amount'];

        $settlement = PartnerSettlement::create([
            'settlement_code' => $this->uniqueSettlementCode(),
            'partner_termination_request_id' => $termination->id,
            'partner_contract_id' => $contract->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
            'owner_wallet_available_amount' => $ownerWalletAvailable,
            'owner_wallet_pending_amount' => $ownerWalletPending,
            'platform_fee_remaining_refund_amount' => $calculation['refund_amount'],
            'unpaid_platform_fee_amount' => $calculation['unpaid_amount'],
            'penalty_amount' => 0,
            'adjustment_amount' => 0,
            'final_payable_to_owner' => $finalPayableToOwner,
            'final_receivable_from_owner' => $calculation['unpaid_amount'],
            'status' => 'approved',
            'calculated_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'note' => json_encode([
                ...$calculation,
                'owner_wallet_available_amount' => $ownerWalletAvailable,
                'owner_wallet_pending_amount' => $ownerWalletPending,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $settlement->setAttribute('calculation_total_paid', $calculation['total_paid']);
        $settlement->setAttribute('calculation_months_used', $calculation['months_used']);
        $settlement->setAttribute('calculation_months_remaining', $calculation['months_remaining']);

        if ((float) $calculation['refund_amount'] > 0) {
            PartnerSettlementItem::create([
                'partner_settlement_id' => $settlement->id,
                'item_type' => 'platform_fee_remaining_refund',
                'description' => 'Hoàn phí nền tảng chưa sử dụng: ' . $calculation['months_remaining'] . ' tháng còn lại.',
                'amount' => $calculation['refund_amount'],
                'direction' => 'payable_to_owner',
                'reference_type' => VenuePlatformFeeLedger::class,
                'reference_id' => $contract->venue_cluster_id,
                'created_at' => now(),
            ]);
        }

        if ($ownerWalletAvailable > 0) {
            PartnerSettlementItem::create([
                'partner_settlement_id' => $settlement->id,
                'item_type' => 'owner_wallet_balance',
                'description' => 'Rút toàn bộ số dư ví chủ sân khỏi hệ thống khi chấm dứt hợp tác.',
                'amount' => $ownerWalletAvailable,
                'direction' => 'payable_to_owner',
                'reference_type' => OwnerWallet::class,
                'reference_id' => $wallet?->id,
                'created_at' => now(),
            ]);
        }

        if ($finalPayableToOwner > 0) {
            $this->createWithdrawalForSettlement($settlement, $contract, $finalPayableToOwner, (float) $calculation['refund_amount']);
        }

        return $settlement;
    }

    private function calculatePlatformFeeRefund(PartnerContract $contract): array
    {
        $ledgers = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $contract->venue_cluster_id)
            ->where('status', 'paid')
            ->get();

        $totalPaid = (float) $ledgers->sum('amount_paid');
        $unpaidAmount = (float) VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $contract->venue_cluster_id)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount_due');

        $totalMonths = (int) $ledgers->sum(function (VenuePlatformFeeLedger $ledger): int {
            if ($ledger->period_months) {
                return max(1, (int) $ledger->period_months);
            }

            return max(1, (int) ceil(Carbon::parse($ledger->period_start)->diffInDays(Carbon::parse($ledger->period_end)) / 30));
        });

        if ($totalMonths <= 0) {
            $totalMonths = $contract->effective_from && $contract->effective_to
                ? max(1, (int) ceil($contract->effective_from->diffInDays($contract->effective_to) / 30))
                : 12;
        }

        $start = $contract->effective_from ?: $contract->created_at ?: now();
        $monthsUsed = max(1, (int) ceil($start->diffInDays(now()) / 30));
        $monthsRemaining = max(0, $totalMonths - $monthsUsed);
        $refundAmount = $totalPaid > 0 ? round(($totalPaid / $totalMonths) * $monthsRemaining, 2) : 0.0;

        return [
            'total_paid' => $totalPaid,
            'total_months' => $totalMonths,
            'months_used' => $monthsUsed,
            'months_remaining' => $monthsRemaining,
            'refund_amount' => $refundAmount,
            'unpaid_amount' => $unpaidAmount,
        ];
    }

    private function createWithdrawalForSettlement(PartnerSettlement $settlement, PartnerContract $contract, float $amount, float $platformRefundAmount = 0.0): void
    {
        $bankAccount = OwnerBankAccount::query()
            ->where('owner_id', $contract->owner_id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();

        if (! $bankAccount) {
            return;
        }

        $wallet = OwnerWallet::query()->firstOrCreate(
            [
                'owner_id' => $contract->owner_id,
                'venue_cluster_id' => $contract->venue_cluster_id,
            ],
            [
                'available_balance' => 0,
                'pending_withdrawal_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]
        );

        $balanceBefore = (float) $wallet->available_balance;
        $balanceAfterCredit = $balanceBefore + max(0, $platformRefundAmount);
        $wallet->forceFill([
            'available_balance' => max(0, $balanceAfterCredit - $amount),
            'pending_withdrawal_balance' => (float) $wallet->pending_withdrawal_balance + $amount,
            'total_earned' => (float) $wallet->total_earned + max(0, $platformRefundAmount),
        ])->save();

        $withdrawal = OwnerWithdrawalRequest::create([
            'request_code' => 'WR-' . Str::upper(Str::random(8)),
            'source' => 'partner_termination_settlement',
            'partner_settlement_id' => $settlement->id,
            'partner_termination_request_id' => $settlement->partner_termination_request_id,
            'auto_created' => true,
            'owner_id' => $contract->owner_id,
            'owner_wallet_id' => $wallet->id,
            'owner_bank_account_id' => $bankAccount->id,
            'amount' => $amount,
            'status' => 'pending',
            'owner_note' => 'Tự động tạo từ quyết toán chấm dứt hợp tác và rút toàn bộ số dư ví khỏi hệ thống.',
            'requested_at' => now(),
            'metadata' => [
                'contract_code' => $contract->contract_code,
                'settlement_code' => $settlement->settlement_code,
                'platform_refund_amount' => $platformRefundAmount,
                'wallet_balance_before_withdrawal' => $balanceBefore,
            ],
        ]);

        OwnerWalletLedger::create([
            'owner_wallet_id' => $wallet->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceAfterCredit,
            'balance_after' => (float) $wallet->available_balance,
            'reference_code' => 'WDR-' . Str::upper(Str::random(8)),
            'reference_type' => OwnerWithdrawalRequest::class,
            'reference_id' => $withdrawal->id,
            'description' => 'Rút toàn bộ số dư ví do chấm dứt hợp đồng đối tác.',
            'metadata' => [
                'partner_settlement_id' => $settlement->id,
                'partner_termination_request_id' => $settlement->partner_termination_request_id,
            ],
        ]);
    }

    private function scheduleRevocationJobs(PartnerTerminationRequest $termination): void
    {
        $transitionEndAt = $termination->transition_end_at ?: now()->addDays(30);
        $reminderAt = $transitionEndAt->copy()->subDays(7);

        SendRevocationReminderJob::dispatch($termination->id)->delay($reminderAt->isFuture() ? $reminderAt : now());
        RevokeVenueOwnerRoleJob::dispatch($termination->id)->delay($transitionEndAt);
    }

    private function grantVenueOwnerRole(string $userId, ?string $actorId): void
    {
        $role = Role::query()->where('name', 'venue_owner')->first();
        if (! $role) {
            return;
        }

        UserRole::firstOrCreate(
            [
                'user_id' => $userId,
                'role_id' => $role->id,
                'scope_type' => 'system',
                'scope_id' => self::ZERO_UUID,
            ],
            [
                'granted_by' => $actorId,
            ]
        );
    }

    private function activateBankAccounts(PartnerApplication $application, ?string $actorId): void
    {
        $this->storeBankAccountFromApplication($application, $actorId, 'active');
        $application->bankAccounts()->update([
            'status' => 'active',
            'verified_by' => $actorId,
            'verified_at' => now(),
            'rejected_reason' => null,
        ]);
    }

    private function storeBankAccountFromApplication(PartnerApplication $application, ?string $actorId, string $status): void
    {
        if (! $application->account_number || ! $application->bank_name || ! $application->account_holder_name) {
            return;
        }

        $bankCode = $application->bank_code ?: Str::upper(Str::slug($application->bank_name, '_'));

        OwnerBankAccount::updateOrCreate(
            [
                'owner_id' => $application->user_id,
                'bank_code' => $bankCode,
                'account_number' => $application->account_number,
            ],
            [
                'partner_application_id' => $application->id,
                'bank_name' => $application->bank_name,
                'account_holder_name' => $application->account_holder_name,
                'branch_name' => $application->bank_branch,
                'status' => $status,
                'is_default' => true,
                'verified_by' => $status === 'active' ? $actorId : null,
                'verified_at' => $status === 'active' ? now() : null,
                'rejected_reason' => null,
            ]
        );
    }

    private function contractRenderData(PartnerApplication $application, string $contractCode): array
    {
        $application->loadMissing(['user', 'courts.courtType', 'bankAccounts']);

        $bankAccount = $application->bankAccounts
            ->sortByDesc(fn (OwnerBankAccount $account) => (int) ($account->is_default ?? false))
            ->sortByDesc(fn (OwnerBankAccount $account) => $account->status === 'active' ? 1 : 0)
            ->first();
        $bankName = $application->bank_name ?: $bankAccount?->bank_name;
        $accountNumber = $application->account_number ?: $bankAccount?->account_number;
        $accountHolderName = $application->account_holder_name ?: $bankAccount?->account_holder_name;
        $courtCount = (int) ($application->court_count_total ?: $application->courts->count());
        $ownerName = $this->applicationSignerName($application, $application->user);
        $ownerPhone = $application->applicant_phone ?: $application->venue_phone ?: $application->user?->phone;
        $ownerEmail = $application->applicant_email ?: $application->venue_email ?: $application->user?->email;

        return [
            'contract_number' => $contractCode,
            'contract_code' => $contractCode,
            'signed_date' => now()->format('d/m/Y'),
            'party_b_name' => $application->business_name,
            'party_b_id' => $application->tax_code ?: $application->representative_identity_number,
            'party_b_address' => $application->business_address ?: $application->venue_address,
            'venue_cluster_list' => $application->venue_name . ' - ' . $application->venue_address,
            'contract_start_date' => now()->format('d/m/Y'),
            'contract_duration' => '12 tháng',
            'contract_title' => 'Hợp đồng hợp tác đối tác ' . $application->venue_name,
            'effective_from' => now()->format('d/m/Y'),
            'effective_to' => now()->addYear()->format('d/m/Y'),
            'sportgo_company_name' => 'Công ty TNHH SportGo',
            'sportgo_tax_code' => '0000000000',
            'sportgo_address' => 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội',
            'sportgo_representative_name' => 'Nguyễn Đức Kiên',
            'sportgo_representative_title' => 'Giám đốc',
            'sportgo_authorization_basis' => 'Người đại diện theo pháp luật',
            'owner_full_name' => $ownerName,
            'owner_phone' => $ownerPhone,
            'owner_email' => $ownerEmail,
            'representative_name' => $application->representative_name ?: $ownerName,
            'representative_position' => $application->representative_position,
            'identity_number' => $application->representative_identity_number,
            'representative_identity_issued_date' => $application->representative_identity_issued_date?->format('d/m/Y'),
            'representative_identity_issued_place' => $application->representative_identity_issued_place,
            'business_name' => $application->business_name,
            'tax_code' => $application->tax_code,
            'business_license_number' => $application->business_license_number,
            'business_code' => $application->business_code,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_holder_name' => $accountHolderName,
            'bank_account_snapshot' => trim(($bankName ?: 'Chưa cung cấp') . ' - ' . ($accountNumber ?: 'Chưa cung cấp') . ' - ' . ($accountHolderName ?: 'Chưa cung cấp')),
            'venue_name' => $application->venue_name,
            'venue_address' => $application->venue_address,
            'court_types_summary' => $application->courts->pluck('courtType.name')->filter()->unique()->implode(', '),
            'court_count_total' => $courtCount > 0 ? (string) $courtCount : 'Chưa cung cấp',
            'expected_opening_hours' => $application->expected_opening_hours,
            'platform_fee_amount' => 'Theo chính sách phí nền tảng hiện hành',
            'payment_due_rule' => 'Thanh toán đúng hạn theo kỳ phí đã đăng ký.',
            'overdue_lock_rule' => 'Quá hạn có thể bị hạn chế quyền vận hành cụm sân.',
            'refund_policy_summary' => 'Phí chưa sử dụng được quyết toán khi chấm dứt hợp tác.',
            'owner_signer_full_name' => $ownerName,
            'sportgo_signer_full_name' => 'Đại diện SportGo',
        ];
    }

    private function notifyAdmins(string $type, string $title, string $body, ?string $referenceId): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin', 'system_staff', 'partner_manager']))
            ->get()
            ->each(fn (User $admin) => $this->notifyUser($admin, $type, $title, $body, $referenceId));
    }

    private function notifyUser(User $user, string $type, string $title, string $body, ?string $referenceId): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'reference_type' => 'partner_application',
            'reference_id' => $referenceId,
            'data' => [],
            'is_read' => false,
        ]);
    }

    private function audit(
        string $action,
        object $entity,
        ?User $actor,
        string $actorType,
        ?array $oldValues,
        ?array $newValues,
        ?Request $request,
        ?string $reason = null
    ): void {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'module' => 'partner',
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => (string) $entity->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $actorType,
            'metadata' => [],
            'reason' => $reason,
            'severity' => 'info',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function applicationHistory(PartnerApplication $application, ?string $oldStatus, string $newStatus, ?User $actor, string $actorType, ?string $reason): void
    {
        PartnerApplicationStatusHistory::create([
            'partner_application_id' => $application->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor?->id,
            'actor_type' => $actorType,
            'reason' => $reason,
            'metadata' => [],
            'created_at' => now(),
        ]);
    }

    private function terminationHistory(PartnerTerminationRequest $termination, ?string $oldStatus, string $newStatus, ?User $actor, string $actorType, ?string $reason): void
    {
        PartnerTerminationStatusHistory::create([
            'partner_termination_request_id' => $termination->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor?->id,
            'actor_type' => $actorType,
            'reason' => $reason,
            'metadata' => [],
            'created_at' => now(),
        ]);
    }

    private function uniqueContractCode(): string
    {
        do {
            $code = 'HD-SG-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (PartnerContract::query()->where('contract_code', $code)->exists());

        return $code;
    }

    private function uniqueTerminationCode(string $prefix): string
    {
        do {
            $code = 'TERM-' . $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (PartnerTerminationRequest::query()->where('termination_code', $code)->exists());

        return $code;
    }

    private function uniqueSettlementCode(): string
    {
        do {
            $code = 'SETTLE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (PartnerSettlement::query()->where('settlement_code', $code)->exists());

        return $code;
    }

    private function uniqueVenueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue-cluster';
        $slug = $base;
        $suffix = 2;

        while (VenueCluster::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function terminationMailData(PartnerTerminationRequest $termination, PartnerContract $contract, PartnerSettlement $settlement, User $admin): array
    {
        $calculation = json_decode((string) $settlement->note, true) ?: [];

        return [
            'owner_name' => $this->applicationSignerName($contract->application, $contract->application?->user),
            'contract_code' => $contract->contract_code,
            'confirmed_at' => $this->timestamp($termination->approved_at),
            'admin_name' => ($admin->full_name ?: $admin->username) . ' - Đại diện SportGo',
            'total_paid' => $this->money($calculation['total_paid'] ?? 0),
            'months_used' => $calculation['months_used'] ?? 0,
            'months_remaining' => $calculation['months_remaining'] ?? 0,
            'refund_amount' => $this->money($settlement->final_payable_to_owner),
            'bank_account' => $this->bankSnapshot($contract->application),
            'revocation_date' => $this->timestamp($termination->transition_end_at),
        ];
    }

    private function settlementTableText(PartnerSettlement $settlement): string
    {
        $settlement->loadMissing('items');

        return $settlement->items
            ->map(fn (PartnerSettlementItem $item) => $item->description . ': ' . $this->money($item->amount))
            ->implode("\n");
    }

    private function bankSnapshot(?PartnerApplication $application): string
    {
        if (! $application) {
            return '';
        }

        return trim(($application->account_number ?: '-') . ' - ' . ($application->bank_name ?: '-') . ' - ' . ($application->account_holder_name ?: '-'));
    }

    private function timestamp(CarbonInterface|string|null $value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('d/m/Y H:i:s');
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' VND';
    }
}
