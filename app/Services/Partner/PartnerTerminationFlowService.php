<?php

namespace App\Services\Partner;

use App\Mail\Partner\PartnerTerminationReceivedMail;
use App\Models\Booking;
use App\Models\DocumentSigningRequest;
use App\Models\GeneratedDocument;
use App\Models\Notification;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWithdrawalRequest;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationBookingAction;
use App\Models\PartnerTerminationDocument;
use App\Models\PartnerTerminationRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\Bookings\OwnerBookingCancellationService;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerTerminationFlowService
{
    public const STATUS_DRAFT_PREVIEW = 'draft_preview';
    public const STATUS_IN_PROGRESS = 'cancellation_in_progress';
    public const STATUS_FUTURE_BOOKINGS = 'future_bookings_processing';
    public const STATUS_WAITING_SETTLEMENT = 'waiting_final_settlement';
    public const STATUS_WAITING_FINAL_SIGNATURE = 'waiting_final_document_signature';
    public const STATUS_TERMINATING = 'terminating';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_OWNER_CANCELLED = 'owner_cancelled_request';
    public const STATUS_ADMIN_REJECTED = 'admin_rejected';

    public const POLICY_CANCEL_ALL = 'cancel_all_refund_to_user_balance';
    public const POLICY_SERVE_UNTIL_LAST = 'serve_until_last_booking';
    public const POLICY_MANUAL = 'manual_per_booking';

    private const ACTIVE_REQUEST_STATUSES = [
        self::STATUS_DRAFT_PREVIEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FUTURE_BOOKINGS,
        self::STATUS_WAITING_SETTLEMENT,
        self::STATUS_WAITING_FINAL_SIGNATURE,
        self::STATUS_TERMINATING,
        'submitted',
        'reviewing',
        'transition_period',
        'approved',
        'pending_signature',
        'settlement_processing',
    ];

    private const OPEN_BOOKING_STATUSES = [
        'pending_approval',
        'pending_payment',
        'confirmed',
        'checked_in',
    ];

    private const RESOLVED_BOOKING_STATUSES = [
        'completed',
        'cancelled',
        'expired',
        'rejected',
    ];

    private const PENDING_REFUND_STATUSES = [
        'pending_confirmation',
        'processing',
        'pending_owner_confirmation',
        'owner_confirmed',
        'admin_processing',
    ];

    private const PENDING_WITHDRAWAL_STATUSES = [
        'pending',
        'reviewing',
        'approved',
    ];

    public function __construct(
        private readonly PartnerDocumentService $documents,
        private readonly PartnerDocumentSigningService $signing,
        private readonly OwnerBookingCancellationService $bookingCancellation,
        private readonly OwnerWalletService $wallets,
        private readonly PartnerMailDispatcher $mail,
    ) {
    }

    public function eligibility(User $owner, string|int $clusterId): array
    {
        $cluster = $this->ownedCluster($owner, $clusterId);
        $contract = $this->activeContractForCluster($cluster, $owner);
        $activeRequest = $this->activeRequestForCluster($cluster->id, true);
        $summary = $this->financialSummary($cluster);

        return [
            'eligible' => $cluster->status === 'active' && $contract !== null && ($activeRequest === null || $activeRequest->status === self::STATUS_DRAFT_PREVIEW),
            'reason' => $this->eligibilityReason($cluster, $contract, $activeRequest),
            'cluster' => $cluster,
            'contract' => $contract,
            'active_request' => $activeRequest?->load(['documents.generatedDocument', 'bookingActions.booking']),
            'summary' => $summary,
            'policies' => $this->futureBookingPolicies(),
            'warning' => 'Khi gui yeu cau cham dut, cum san se bi khoa thao tac quan ly binh thuong. Chu san chi con quyen xu ly booking, hoan tien, yeu cau rut tien va theo doi ho so cham dut.',
        ];
    }

    public function previewOwnerRequest(User $owner, string|int $clusterId, array $data, Request $httpRequest): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($owner, $clusterId, $data, $httpRequest): PartnerTerminationRequest {
            $cluster = $this->ownedCluster($owner, $clusterId);
            $contract = $this->activeContractForCluster($cluster, $owner);

            if (! $contract) {
                throw ValidationException::withMessages([
                    'contract' => 'Cum san chua co hop dong dang hieu luc de gui yeu cau cham dut.',
                ]);
            }

            if ($cluster->status !== 'active') {
                throw ValidationException::withMessages([
                    'venue_cluster_id' => 'Chi cum san dang hoat dong moi duoc gui yeu cau cham dut.',
                ]);
            }

            $active = $this->activeRequestForCluster($cluster->id, true);
            if ($active && $active->status !== self::STATUS_DRAFT_PREVIEW) {
                throw ValidationException::withMessages([
                    'termination' => 'Cum san nay dang co yeu cau cham dut chua hoan tat.',
                ]);
            }

            $summary = $this->financialSummary($cluster);
            if ((int) $summary['future_booking_count'] > 0 && empty($data['future_booking_policy'])) {
                throw ValidationException::withMessages([
                    'future_booking_policy' => 'Vui long chon phuong an xu ly booking tuong lai.',
                ]);
            }

            $termination = $active ?: new PartnerTerminationRequest();
            $termination->fill([
                'termination_code' => $termination->termination_code ?: $this->uniqueTerminationCode('OWNER'),
                'partner_contract_id' => $contract->id,
                'partner_application_id' => $contract->partner_application_id,
                'owner_id' => $owner->id,
                'venue_cluster_id' => $cluster->id,
                'termination_type' => 'unilateral_by_owner',
                'requested_by' => $owner->id,
                'requested_at' => now(),
                'reason' => $data['reason'],
                'detail_reason' => $data['detail_reason'] ?? null,
                'requested_effective_date' => $data['requested_effective_date'] ?? null,
                'future_booking_policy' => $data['future_booking_policy'] ?? null,
                'future_booking_policy_confirmed_at' => ! empty($data['future_booking_policy']) ? now() : null,
                'owner_warning_accepted_at' => now(),
                'future_booking_count' => $summary['future_booking_count'],
                'owner_balance_total' => $summary['owner_balance_total'],
                'future_online_booking_liability' => $summary['future_online_booking_liability'],
                'pending_refund_liability' => $summary['pending_refund_liability'],
                'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
                'withdrawable_amount' => $summary['withdrawable_amount'],
                'future_booking_summary' => $summary['future_bookings'],
                'owner_attachments' => $data['attachments'] ?? [],
                'grace_period_days' => $this->gracePeriodDays(),
                'status' => self::STATUS_DRAFT_PREVIEW,
                'metadata' => [
                    'ip_address' => $httpRequest->ip(),
                    'user_agent' => $httpRequest->userAgent(),
                    'previewed_at' => now()->toIso8601String(),
                ],
            ]);
            $termination->save();

            $document = $this->generateOwnerRequestDocument($termination->fresh(['contract.application.user', 'venueCluster']), $owner, 'pending_owner_signature');
            PartnerTerminationDocument::query()->create([
                'partner_termination_request_id' => $termination->id,
                'generated_document_id' => $document->id,
                'document_type' => 'owner_termination_request',
                'file_path' => $document->generated_file_path,
                'status' => 'pending_signature',
                'generated_by' => $owner->id,
                'generated_at' => now(),
            ]);

            $this->history($termination, $active?->status, self::STATUS_DRAFT_PREVIEW, $owner, 'owner', 'Owner preview don yeu cau cham dut hop dong.');

            return $termination->fresh([
                'contract.application.user',
                'venueCluster',
                'documents.generatedDocument.signatures',
                'bookingActions.booking.customer',
            ]);
        });
    }

    public function sendOwnerRequestOtp(PartnerTerminationRequest $termination, User $owner, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $this->assertOwner($termination, $owner);
        if ($termination->status !== self::STATUS_DRAFT_PREVIEW) {
            throw ValidationException::withMessages([
                'status' => 'Don yeu cau khong o trang thai cho owner ky.',
            ]);
        }

        return $this->signing->requestOtp(
            $this->latestOwnerRequestGeneratedDocument($termination),
            $owner,
            'owner',
            'owner_sign_partner_termination_request',
            'Toi xac nhan da doc canh bao va dong y ky gui yeu cau cham dut hop dong doi tac SportGo.',
            $signatureImage,
            $request
        );
    }

    public function submitOwnerRequest(PartnerTerminationRequest $termination, User $owner, int $signingRequestId, string $otp, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $owner, $signingRequestId, $otp, $request): PartnerTerminationRequest {
            $this->assertOwner($termination, $owner);
            $termination = PartnerTerminationRequest::query()
                ->with(['contract.application.user', 'venueCluster'])
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($termination->status !== self::STATUS_DRAFT_PREVIEW) {
                throw ValidationException::withMessages([
                    'status' => 'Don yeu cau khong con o trang thai cho ky gui.',
                ]);
            }

            $document = $this->latestOwnerRequestGeneratedDocument($termination);
            $signingRequest = DocumentSigningRequest::query()
                ->whereKey($signingRequestId)
                ->where('generated_document_id', $document->id)
                ->where('signer_side', 'owner')
                ->where('action', 'owner_sign_partner_termination_request')
                ->firstOrFail();

            $verified = $this->signing->verifyOtp($signingRequest, $owner, $otp);
            $signature = $this->documents->signDocument($document, $owner, 'owner', $verified->signature_image, $request, [
                'signer_full_name' => $this->ownerSignerName($termination),
                'signer_title' => $termination->contract?->application?->representative_position ?: 'Chu san',
                'signer_organization' => $termination->contract?->application?->business_name,
            ]);
            $this->signing->markSigned($verified, $signature);

            PartnerTerminationDocument::query()
                ->where('partner_termination_request_id', $termination->id)
                ->where('generated_document_id', $document->id)
                ->update(['status' => 'signed']);

            $summary = $this->financialSummary($termination->venueCluster);
            $oldStatus = $termination->status;
            $termination->forceFill([
                'status' => self::STATUS_IN_PROGRESS,
                'future_booking_count' => $summary['future_booking_count'],
                'owner_balance_total' => $summary['owner_balance_total'],
                'future_online_booking_liability' => $summary['future_online_booking_liability'],
                'pending_refund_liability' => $summary['pending_refund_liability'],
                'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
                'withdrawable_amount' => $summary['withdrawable_amount'],
                'future_booking_summary' => $summary['future_bookings'],
                'metadata' => array_merge($termination->metadata ?: [], [
                    'owner_signed_request_at' => now()->toIso8601String(),
                    'owner_signed_request_document_id' => $document->id,
                ]),
            ])->save();

            $this->syncFutureBookingActions($termination, $summary['future_bookings']);
            $this->lockClusterForTermination($termination, $owner);
            $this->history($termination, $oldStatus, self::STATUS_IN_PROGRESS, $owner, 'owner', 'Owner da ky va gui yeu cau cham dut hop dong.');
            $this->notifyAfterOwnerSubmit($termination);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function showForOwner(PartnerTerminationRequest $termination, User $owner): PartnerTerminationRequest
    {
        $this->assertOwner($termination, $owner);
        $this->refreshAmounts($termination);

        return $termination->fresh($this->requestRelations());
    }

    public function futureBookings(PartnerTerminationRequest $termination, User $owner): array
    {
        $this->assertOwner($termination, $owner);

        return [
            'data' => $this->futureBookingsPayload($termination->venue_cluster_id, $termination),
        ];
    }

    public function bulkBookingAction(PartnerTerminationRequest $termination, User $owner, array $bookingIds, string $action, ?string $reason = null): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $owner, $bookingIds, $action, $reason): PartnerTerminationRequest {
            $this->assertOwner($termination, $owner);
            $termination = PartnerTerminationRequest::query()
                ->with('venueCluster')
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Yeu cau khong con cho phep xu ly booking tuong lai.',
                ]);
            }

            $validActions = [
                self::POLICY_CANCEL_ALL,
                self::POLICY_SERVE_UNTIL_LAST,
                self::POLICY_MANUAL,
            ];
            if (! in_array($action, $validActions, true)) {
                throw ValidationException::withMessages(['action' => 'Phuong an xu ly booking khong hop le.']);
            }

            $bookings = $this->futureBookingsQuery($termination->venue_cluster_id)
                ->whereIn('id', $bookingIds)
                ->with(['payments', 'items'])
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $bookingAction = PartnerTerminationBookingAction::query()->updateOrCreate(
                    [
                        'partner_termination_request_id' => $termination->id,
                        'booking_id' => $booking->id,
                    ],
                    [
                        'action' => $action,
                        'paid_online_amount' => $this->paidOnlineAmount($booking),
                        'reason' => $reason,
                        'metadata' => [
                            'selected_by' => $owner->id,
                            'selected_at' => now()->toIso8601String(),
                        ],
                    ]
                );

                if ($action === self::POLICY_CANCEL_ALL) {
                    $result = $this->bookingCancellation->cancelBooking(
                        $booking,
                        $owner,
                        $reason ?: 'Chu san cham dut hop dong, huy booking tuong lai va hoan ve so du/khoan hoan tien user.',
                    );

                    $refundId = collect($result['refunds'] ?? [])->pluck('id')->filter()->first();
                    $bookingAction->forceFill([
                        'status' => 'resolved',
                        'refund_id' => $refundId,
                        'processed_by' => $owner->id,
                        'processed_at' => now(),
                        'metadata' => array_merge($bookingAction->metadata ?: [], [
                            'result' => 'booking_cancelled_refund_created',
                            'refunds' => $result['refunds'] ?? [],
                        ]),
                    ])->save();
                    continue;
                }

                $bookingAction->forceFill([
                    'status' => $action === self::POLICY_SERVE_UNTIL_LAST ? 'waiting_service_completion' : 'pending_manual_resolution',
                    'processed_by' => $owner->id,
                    'processed_at' => now(),
                ])->save();
            }

            $this->refreshProgress($termination);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function createWithdrawal(PartnerTerminationRequest $termination, User $owner, array $data): OwnerWithdrawalRequest
    {
        return DB::transaction(function () use ($termination, $owner, $data): OwnerWithdrawalRequest {
            $this->assertOwner($termination, $owner);
            $termination = $this->refreshAmounts($termination);

            $wallet = OwnerWallet::query()
                ->whereKey($data['owner_wallet_id'])
                ->where('owner_id', $owner->id)
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->lockForUpdate()
                ->firstOrFail();

            $bankAccount = OwnerBankAccount::query()
                ->whereKey($data['owner_bank_account_id'])
                ->where('owner_id', $owner->id)
                ->where('status', 'active')
                ->firstOrFail();

            $amount = round((float) $data['amount'], 2);
            $allowed = min((float) $wallet->available_balance, (float) $termination->withdrawable_amount);
            if ($amount <= 0 || $amount > $allowed + 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'So tien rut vuot qua phan duoc phep rut khi dang cham dut hop dong.',
                ]);
            }

            $withdrawal = OwnerWithdrawalRequest::query()->create([
                'request_code' => $this->uniqueWithdrawalCode(),
                'source' => 'partner_termination_settlement',
                'partner_termination_request_id' => $termination->id,
                'auto_created' => false,
                'owner_id' => $owner->id,
                'owner_wallet_id' => $wallet->id,
                'owner_bank_account_id' => $bankAccount->id,
                'amount' => $amount,
                'status' => 'pending',
                'owner_note' => $data['owner_note'] ?? null,
                'metadata' => [
                    'source' => 'partner_termination_owner_withdrawal',
                    'termination_code' => $termination->termination_code,
                    'withdrawable_amount_at_request' => (float) $termination->withdrawable_amount,
                ],
                'requested_at' => now(),
            ]);

            $this->wallets->holdWithdrawal($withdrawal, [
                'source' => 'partner_termination_owner_request',
                'partner_termination_request_id' => $termination->id,
            ]);

            $this->refreshProgress($termination);

            return $withdrawal->fresh(['wallet.venueCluster', 'bankAccount']);
        });
    }

    public function sendOwnerCancelOtp(PartnerTerminationRequest $termination, User $owner, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $this->assertOwner($termination, $owner);
        $this->assertCanOwnerCancel($termination);

        return $this->signing->requestOtp(
            $this->latestOwnerRequestGeneratedDocument($termination),
            $owner,
            'owner',
            'owner_cancel_partner_termination_request',
            'Toi xac nhan huy yeu cau cham dut hop dong va chap nhan cac xu ly da phat sinh se khong tu dong rollback.',
            $signatureImage,
            $request
        );
    }

    public function cancelOwnerRequest(PartnerTerminationRequest $termination, User $owner, int $signingRequestId, string $otp, string $reason, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $owner, $signingRequestId, $otp, $reason, $request): PartnerTerminationRequest {
            $this->assertOwner($termination, $owner);
            $this->assertCanOwnerCancel($termination);

            $document = $this->latestOwnerRequestGeneratedDocument($termination);
            $signingRequest = DocumentSigningRequest::query()
                ->whereKey($signingRequestId)
                ->where('generated_document_id', $document->id)
                ->where('signer_side', 'owner')
                ->where('action', 'owner_cancel_partner_termination_request')
                ->firstOrFail();

            $verified = $this->signing->verifyOtp($signingRequest, $owner, $otp);
            $signature = $this->documents->signDocument($document, $owner, 'owner', $verified->signature_image, $request, [
                'signature_method' => 'typed_confirm',
                'signer_full_name' => $this->ownerSignerName($termination),
            ]);
            $this->signing->markSigned($verified, $signature);

            $oldStatus = $termination->status;
            $termination->forceFill([
                'status' => self::STATUS_OWNER_CANCELLED,
                'owner_cancel_reason' => $reason,
                'owner_cancelled_at' => now(),
                'owner_cancelled_by' => $owner->id,
            ])->save();

            $this->unlockClusterAfterOwnerCancel($termination);
            $this->history($termination, $oldStatus, self::STATUS_OWNER_CANCELLED, $owner, 'owner', $reason);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function adminIndex(array $filters = [])
    {
        return PartnerTerminationRequest::query()
            ->with(['owner:id,full_name,username,email,phone', 'venueCluster:id,name,status,address', 'contract', 'documents.generatedDocument'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['venue_cluster_id'] ?? null, fn ($query, $id) => $query->where('venue_cluster_id', $id))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $like = '%' . $search . '%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('termination_code', 'like', $like)
                        ->orWhere('reason', 'like', $like)
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                            ->where('full_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like))
                        ->orWhereHas('venueCluster', fn ($clusterQuery) => $clusterQuery->where('name', 'like', $like));
                });
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function adminShow(PartnerTerminationRequest $termination): PartnerTerminationRequest
    {
        $this->refreshAmounts($termination);

        return $termination->fresh($this->requestRelations());
    }

    public function markReadyForFinalDocument(PartnerTerminationRequest $termination, User $admin, ?string $note = null): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $admin, $note): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $termination->status;
            $termination->forceFill([
                'manual_debt_resolved_at' => now(),
                'manual_debt_resolved_by' => $admin->id,
            ])->save();

            $this->generateFinalDocumentIfReady($termination, $admin, false);
            $this->history($termination, $oldStatus, $termination->fresh()->status, $admin, 'admin', $note ?: 'Admin xac nhan du dieu kien sinh van ban cham dut cuoi.');

            return $termination->fresh($this->requestRelations());
        });
    }

    public function previewFinalDocument(PartnerTerminationRequest $termination, User $admin): GeneratedDocument
    {
        return DB::transaction(function () use ($termination, $admin): GeneratedDocument {
            return $this->generateFinalDocumentIfReady($termination, $admin, false);
        });
    }

    public function sendFinalDocumentOtp(PartnerTerminationRequest $termination, User $signer, string $signerSide, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $document = $this->latestFinalGeneratedDocument($termination);
        $this->assertFinalSigner($termination, $signer, $signerSide);

        return $this->signing->requestOtp(
            $document,
            $signer,
            $signerSide,
            $signerSide === 'sportgo' ? 'admin_sign_partner_final_termination_document' : 'owner_sign_partner_final_termination_document',
            $signerSide === 'sportgo'
                ? 'Toi xac nhan dai dien SportGo ky bien ban cham dut hop dong cuoi cung.'
                : 'Toi xac nhan da doi soat va ky xac nhan bien ban cham dut hop dong cuoi cung.',
            $signatureImage,
            $request
        );
    }

    public function signFinalDocument(PartnerTerminationRequest $termination, User $signer, string $signerSide, int $signingRequestId, string $otp, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $signer, $signerSide, $signingRequestId, $otp, $request): PartnerTerminationRequest {
            $this->assertFinalSigner($termination, $signer, $signerSide);
            $document = $this->latestFinalGeneratedDocument($termination);
            $signingRequest = DocumentSigningRequest::query()
                ->whereKey($signingRequestId)
                ->where('generated_document_id', $document->id)
                ->where('signer_side', $signerSide)
                ->firstOrFail();

            $verified = $this->signing->verifyOtp($signingRequest, $signer, $otp);
            $signature = $this->documents->signDocument($document, $signer, $signerSide, $verified->signature_image, $request, [
                'signer_full_name' => $signerSide === 'owner' ? $this->ownerSignerName($termination) : ($signer->full_name ?: $signer->username),
                'signer_title' => $signerSide === 'owner'
                    ? ($termination->contract?->application?->representative_position ?: 'Chu san')
                    : 'Dai dien SportGo',
                'signer_organization' => $signerSide === 'owner'
                    ? $termination->contract?->application?->business_name
                    : 'SportGo',
            ]);
            $this->signing->markSigned($verified, $signature);

            $oldStatus = $termination->status;
            $updates = $signerSide === 'sportgo'
                ? ['final_document_admin_signed_at' => now()]
                : ['final_document_owner_signed_at' => now()];
            $termination->forceFill($updates)->save();

            $document = $document->fresh('signatures');
            if ($document->status === 'completed' || $document->signatures()->where('status', 'signed')->whereIn('signer_side', ['owner', 'sportgo'])->distinct('signer_side')->count('signer_side') >= 2) {
                $graceDays = $this->gracePeriodDays();
                $termination->forceFill([
                    'status' => self::STATUS_TERMINATING,
                    'effective_termination_date' => now(),
                    'final_document_completed_at' => now(),
                    'grace_period_days' => $graceDays,
                    'owner_access_view_until' => now()->addDays($graceDays),
                    'transition_end_at' => now()->addDays($graceDays),
                ])->save();
                PartnerContract::query()
                    ->whereKey($termination->partner_contract_id)
                    ->update(['status' => 'terminating']);
                PartnerTerminationDocument::query()
                    ->where('partner_termination_request_id', $termination->id)
                    ->where('generated_document_id', $document->id)
                    ->update(['status' => 'signed']);
            }

            $this->history($termination, $oldStatus, $termination->fresh()->status, $signer, $signerSide === 'owner' ? 'owner' : 'admin', 'Ky bien ban cham dut hop dong cuoi cung.');

            return $termination->fresh($this->requestRelations());
        });
    }

    public function manualResolveBooking(PartnerTerminationRequest $termination, Booking $booking, User $admin, ?string $note = null): PartnerTerminationBookingAction
    {
        return DB::transaction(function () use ($termination, $booking, $admin, $note): PartnerTerminationBookingAction {
            if ((string) $booking->venue_cluster_id !== (string) $termination->venue_cluster_id) {
                throw ValidationException::withMessages(['booking_id' => 'Booking khong thuoc cum san cua yeu cau cham dut.']);
            }

            $action = PartnerTerminationBookingAction::query()->updateOrCreate(
                [
                    'partner_termination_request_id' => $termination->id,
                    'booking_id' => $booking->id,
                ],
                [
                    'action' => self::POLICY_MANUAL,
                    'paid_online_amount' => $this->paidOnlineAmount($booking),
                    'reason' => $note,
                ]
            );

            $action->forceFill([
                'status' => 'resolved',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'metadata' => array_merge($action->metadata ?: [], [
                    'manual_resolved_by_admin' => $admin->id,
                    'manual_resolved_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $this->refreshProgress($termination);

            return $action->fresh(['booking.customer', 'processedBy']);
        });
    }

    public function updateSettings(int $graceDays): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'partner_termination_view_grace_days'],
            [
                'value' => (string) max(0, $graceDays),
                'value_type' => 'integer',
                'description' => 'So ngay chu san con duoc xem ho so sau khi bien ban cham dut cuoi da ky.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function refreshProgress(PartnerTerminationRequest $termination): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()
                ->with(['venueCluster', 'contract'])
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($termination->status, [
                self::STATUS_IN_PROGRESS,
                self::STATUS_FUTURE_BOOKINGS,
                self::STATUS_WAITING_SETTLEMENT,
                self::STATUS_WAITING_FINAL_SIGNATURE,
                self::STATUS_TERMINATING,
            ], true)) {
                return $termination;
            }

            $termination = $this->refreshAmounts($termination);

            if ($termination->status === self::STATUS_TERMINATING && $termination->owner_access_view_until && $termination->owner_access_view_until->isPast()) {
                $this->revokeOwnerScope($termination);
                return $termination->fresh();
            }

            if ($termination->status === self::STATUS_WAITING_FINAL_SIGNATURE) {
                return $termination;
            }

            if (! $this->allFutureBookingsResolved($termination)) {
                $this->setStatusIfChanged($termination, self::STATUS_FUTURE_BOOKINGS, null, 'system', 'Dang xu ly booking tuong lai.');
                return $termination->fresh();
            }

            if (! $this->readyForFinalDocument($termination)) {
                $this->setStatusIfChanged($termination, self::STATUS_WAITING_SETTLEMENT, null, 'system', 'Da xu ly booking, dang cho quyet toan/rut tien cuoi.');
                return $termination->fresh();
            }

            $this->generateFinalDocumentIfReady($termination, null, true);

            return $termination->fresh();
        });
    }

    public function revokeOwnerScope(PartnerTerminationRequest $termination): void
    {
        DB::transaction(function () use ($termination): void {
            $termination = PartnerTerminationRequest::query()
                ->with(['contract.application.user'])
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($termination->owner_access_revoked_at) {
                return;
            }

            $roleId = Role::query()->where('name', 'venue_owner')->value('id');
            if ($roleId && $termination->venue_cluster_id) {
                UserRole::query()
                    ->where('user_id', $termination->owner_id)
                    ->where('role_id', $roleId)
                    ->where('scope_type', 'venue')
                    ->where('scope_id', $termination->venue_cluster_id)
                    ->delete();
            }

            $oldStatus = $termination->status;
            $termination->forceFill([
                'status' => self::STATUS_TERMINATED,
                'owner_access_revoked_at' => now(),
            ])->save();

            $termination->contract?->forceFill([
                'status' => 'terminated',
                'terminated_at' => now(),
            ])->save();

            $termination->contract?->application?->forceFill([
                'terminated_at' => now(),
            ])->save();

            VenueCluster::query()
                ->whereKey($termination->venue_cluster_id)
                ->update([
                    'status' => 'partner_terminated',
                    'status_reason' => 'Hop dong doi tac da cham dut hoan tat theo ho so ' . $termination->termination_code,
                    'locked_at' => now(),
                ]);

            VenueCourt::query()
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->update(['status' => 'inactive']);

            $this->history($termination, $oldStatus, self::STATUS_TERMINATED, null, 'system', 'Thu hoi quyen owner sau thoi gian cau hinh.');
        });
    }

    public function refreshAmounts(PartnerTerminationRequest $termination): PartnerTerminationRequest
    {
        $termination->loadMissing('venueCluster');
        if (! $termination->venueCluster) {
            return $termination;
        }

        $summary = $this->financialSummary($termination->venueCluster);
        $termination->forceFill([
            'future_booking_count' => $summary['future_booking_count'],
            'owner_balance_total' => $summary['owner_balance_total'],
            'future_online_booking_liability' => $summary['future_online_booking_liability'],
            'pending_refund_liability' => $summary['pending_refund_liability'],
            'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
            'withdrawable_amount' => $summary['withdrawable_amount'],
            'future_booking_summary' => $summary['future_bookings'],
        ])->save();

        return $termination->fresh();
    }

    public function financialSummary(VenueCluster $cluster): array
    {
        $futureBookings = $this->futureBookingsPayload($cluster->id);
        $futureLiability = collect($futureBookings)->sum('paid_online_amount');
        $wallets = OwnerWallet::query()
            ->where('owner_id', $cluster->owner_id)
            ->where('venue_cluster_id', $cluster->id)
            ->get();
        $ownerBalanceTotal = (float) $wallets->sum(fn (OwnerWallet $wallet): float => (float) $wallet->available_balance + (float) $wallet->pending_withdrawal_balance);
        $pendingRefundLiability = (float) Refund::query()
            ->whereHas('booking', fn ($query) => $query->where('venue_cluster_id', $cluster->id))
            ->whereIn('status', self::PENDING_REFUND_STATUSES)
            ->sum('amount');
        $pendingWithdrawalAmount = (float) OwnerWithdrawalRequest::query()
            ->whereIn('owner_wallet_id', $wallets->pluck('id')->all())
            ->whereIn('status', self::PENDING_WITHDRAWAL_STATUSES)
            ->sum('amount');

        $withdrawable = max($ownerBalanceTotal - $futureLiability - $pendingRefundLiability - $pendingWithdrawalAmount, 0);

        return [
            'owner_balance_total' => round($ownerBalanceTotal, 2),
            'future_online_booking_liability' => round($futureLiability, 2),
            'pending_refund_liability' => round($pendingRefundLiability, 2),
            'pending_withdrawal_amount' => round($pendingWithdrawalAmount, 2),
            'withdrawable_amount' => round($withdrawable, 2),
            'future_booking_count' => count($futureBookings),
            'future_bookings' => $futureBookings,
        ];
    }

    public function requestRelations(): array
    {
        return [
            'owner:id,full_name,username,email,phone',
            'venueCluster:id,owner_id,name,status,address,status_reason,locked_at',
            'contract.application.user',
            'documents.generatedDocument.signatures.signer',
            'documents.generatedDocument.signingRequests',
            'bookingActions.booking.customer',
            'bookingActions.booking.payments',
            'bookingActions.processedBy:id,full_name,username,email',
            'statusHistories.changedBy:id,full_name,username,email',
        ];
    }

    private function ownedCluster(User $owner, string|int $clusterId): VenueCluster
    {
        return VenueCluster::query()
            ->whereKey($clusterId)
            ->where('owner_id', $owner->id)
            ->firstOrFail();
    }

    private function activeContractForCluster(VenueCluster $cluster, User $owner): ?PartnerContract
    {
        return PartnerContract::query()
            ->with(['application.user', 'generatedDocument'])
            ->where('venue_cluster_id', $cluster->id)
            ->where('owner_id', $owner->id)
            ->where('status', 'signed_active')
            ->latest()
            ->first();
    }

    private function activeRequestForCluster(string|int $clusterId, bool $includeDraft = true): ?PartnerTerminationRequest
    {
        $statuses = $includeDraft ? self::ACTIVE_REQUEST_STATUSES : array_values(array_diff(self::ACTIVE_REQUEST_STATUSES, [self::STATUS_DRAFT_PREVIEW]));

        return PartnerTerminationRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', $statuses)
            ->latest()
            ->first();
    }

    private function eligibilityReason(VenueCluster $cluster, ?PartnerContract $contract, ?PartnerTerminationRequest $activeRequest): ?string
    {
        if ($cluster->status !== 'active') {
            return 'Cum san khong o trang thai active.';
        }
        if (! $contract) {
            return 'Khong co hop dong active cho cum san.';
        }
        if ($activeRequest && $activeRequest->status !== self::STATUS_DRAFT_PREVIEW) {
            return 'Da co yeu cau cham dut dang xu ly cho cum san nay.';
        }

        return null;
    }

    private function futureBookingPolicies(): array
    {
        return [
            ['value' => self::POLICY_CANCEL_ALL, 'label' => 'Huy toan bo booking tuong lai va hoan ve so du/khoan hoan tien user'],
            ['value' => self::POLICY_SERVE_UNTIL_LAST, 'label' => 'Khong huy, tiep tuc phuc vu den booking cuoi cung'],
            ['value' => self::POLICY_MANUAL, 'label' => 'Xu ly thu cong tung booking'],
        ];
    }

    private function generateOwnerRequestDocument(PartnerTerminationRequest $termination, User $owner, string $status = 'generated'): GeneratedDocument
    {
        $termination->loadMissing(['contract.application.user', 'venueCluster']);
        $summary = $termination->future_booking_summary ?: [];

        return $this->documents->generateDocument('termination_request', $termination, [
            'termination_code' => $termination->termination_code,
            'requested_at' => $this->timestamp($termination->requested_at),
            'requested_by' => $this->ownerSignerName($termination),
            'owner_full_name' => $this->ownerSignerName($termination),
            'owner_signer_full_name' => $this->ownerSignerName($termination),
            'owner_phone' => $termination->contract?->application?->applicant_phone ?: $owner->phone,
            'owner_email' => $termination->contract?->application?->applicant_email ?: $owner->email,
            'contract_code' => $termination->contract?->contract_code,
            'venue_name' => $termination->venueCluster?->name,
            'venue_address' => $termination->venueCluster?->address,
            'termination_type' => 'Chu san de nghi cham dut hop dong',
            'termination_reason' => $termination->reason,
            'reason' => $termination->reason,
            'detail_reason' => $termination->detail_reason,
            'requested_effective_date' => $termination->requested_effective_date?->format('d/m/Y'),
            'future_booking_policy' => $this->policyLabel($termination->future_booking_policy),
            'future_booking_count' => (string) $termination->future_booking_count,
            'future_online_booking_liability' => $this->money($termination->future_online_booking_liability),
            'owner_balance_total' => $this->money($termination->owner_balance_total),
            'withdrawable_amount' => $this->money($termination->withdrawable_amount),
            'temporary_hold_amount' => $this->money($termination->future_online_booking_liability),
            'future_bookings_summary' => collect($summary)->map(fn ($booking) => ($booking['booking_code'] ?? '-') . ' - ' . ($booking['booking_date'] ?? '-') . ' - ' . $this->money($booking['paid_online_amount'] ?? 0))->implode("\n"),
            'attachments' => collect($termination->owner_attachments ?: [])->map(fn ($item) => is_array($item) ? ($item['name'] ?? json_encode($item)) : (string) $item)->implode(', '),
            'owner_bank_account_snapshot' => $this->bankSnapshot($termination),
            'owner_signed_at' => $this->timestamp(now()),
        ], $owner, [
            'status' => $status,
            'partner_application_id' => $termination->partner_application_id,
            'partner_contract_id' => $termination->partner_contract_id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $termination->owner_id,
            'venue_cluster_id' => $termination->venue_cluster_id,
            'title' => 'Don de nghi cham dut hop dong hop tac doi tac SportGo ' . ($termination->venueCluster?->name ?? ''),
        ]);
    }

    private function generateFinalDocumentIfReady(PartnerTerminationRequest $termination, ?User $actor, bool $adminOverride = false): GeneratedDocument
    {
        $termination = $termination->fresh(['contract.application.user', 'venueCluster']);
        if (! $adminOverride && ! $this->readyForFinalDocument($termination)) {
            throw ValidationException::withMessages([
                'termination' => 'Chua du dieu kien sinh bien ban cham dut cuoi.',
            ]);
        }

        $existing = PartnerTerminationDocument::query()
            ->with('generatedDocument')
            ->where('partner_termination_request_id', $termination->id)
            ->whereIn('document_type', ['settlement_minutes', 'final_termination_file'])
            ->latest()
            ->first();

        if ($existing?->generatedDocument) {
            if ($termination->status !== self::STATUS_WAITING_FINAL_SIGNATURE) {
                $this->setStatusIfChanged($termination, self::STATUS_WAITING_FINAL_SIGNATURE, $actor, $actor ? 'admin' : 'system', 'Van ban cham dut cuoi da san sang ky.');
            }

            return $existing->generatedDocument;
        }

        $document = $this->documents->generateDocument('settlement_minutes', $termination, $this->finalDocumentRenderData($termination), $actor, [
            'status' => 'pending_sportgo_signature',
            'partner_application_id' => $termination->partner_application_id,
            'partner_contract_id' => $termination->partner_contract_id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $termination->owner_id,
            'venue_cluster_id' => $termination->venue_cluster_id,
            'title' => 'Bien ban cham dut hop dong hop tac doi tac SportGo ' . ($termination->termination_code ?? ''),
        ]);

        PartnerTerminationDocument::query()->create([
            'partner_termination_request_id' => $termination->id,
            'generated_document_id' => $document->id,
            'document_type' => 'settlement_minutes',
            'file_path' => $document->generated_file_path,
            'status' => 'pending_signature',
            'generated_by' => $actor?->id,
            'generated_at' => now(),
        ]);

        $oldStatus = $termination->status;
        $termination->forceFill([
            'status' => self::STATUS_WAITING_FINAL_SIGNATURE,
            'final_document_generated_at' => now(),
            'final_document_ready_at' => now(),
        ])->save();
        $this->history($termination, $oldStatus, self::STATUS_WAITING_FINAL_SIGNATURE, $actor, $actor ? 'admin' : 'system', 'Sinh bien ban cham dut hop dong cuoi.');

        return $document;
    }

    private function finalDocumentRenderData(PartnerTerminationRequest $termination): array
    {
        $termination->loadMissing(['contract.application.user', 'venueCluster', 'bookingActions.booking']);
        $summary = $this->financialSummary($termination->venueCluster);
        $ownerName = $this->ownerSignerName($termination);
        $bookingResult = $termination->bookingActions
            ->map(fn (PartnerTerminationBookingAction $action) => ($action->booking?->booking_code ?? '-') . ': ' . $this->bookingActionLabel($action->action) . ' / ' . $action->status)
            ->implode("\n");

        return [
            'settlement_code' => 'BB-CD-' . $termination->termination_code,
            'settlement_date' => $this->timestamp(now()),
            'contract_code' => $termination->contract?->contract_code,
            'termination_request_code' => $termination->termination_code,
            'termination_reason' => $termination->reason,
            'owner_full_name' => $ownerName,
            'owner_signer_full_name' => $ownerName,
            'representative_name' => $ownerName,
            'business_name' => $termination->contract?->application?->business_name,
            'party_b_name' => $termination->contract?->application?->business_name ?: $ownerName,
            'venue_name' => $termination->venueCluster?->name,
            'venue_address' => $termination->venueCluster?->address,
            'total_paid' => $this->money($summary['owner_balance_total']),
            'future_booking_count' => (string) $summary['future_booking_count'],
            'booking_resolution_result' => $bookingResult ?: 'Khong con booking tuong lai bat buoc xu ly.',
            'refund_result' => 'Refund dang treo: ' . $this->money($summary['pending_refund_liability']),
            'withdrawal_result' => 'Withdrawal dang treo: ' . $this->money($summary['pending_withdrawal_amount']),
            'owner_wallet_available_amount' => $this->money($summary['owner_balance_total']),
            'future_online_booking_liability' => $this->money($summary['future_online_booking_liability']),
            'pending_refund_liability' => $this->money($summary['pending_refund_liability']),
            'pending_withdrawal_amount' => $this->money($summary['pending_withdrawal_amount']),
            'final_payable_to_owner' => $this->money($summary['withdrawable_amount']),
            'final_receivable_from_owner' => $this->money(0),
            'settlement_items' => $bookingResult,
            'effective_termination_date' => $this->timestamp(now()),
            'owner_access_revocation_date' => $this->timestamp(now()->addDays($this->gracePeriodDays())),
            'grace_period_days' => (string) $this->gracePeriodDays(),
            'bank_account' => $this->bankSnapshot($termination),
        ];
    }

    private function syncFutureBookingActions(PartnerTerminationRequest $termination, array $futureBookings): void
    {
        foreach ($futureBookings as $booking) {
            PartnerTerminationBookingAction::query()->firstOrCreate(
                [
                    'partner_termination_request_id' => $termination->id,
                    'booking_id' => $booking['id'],
                ],
                [
                    'action' => $termination->future_booking_policy ?: self::POLICY_MANUAL,
                    'status' => $termination->future_booking_policy === self::POLICY_SERVE_UNTIL_LAST
                        ? 'waiting_service_completion'
                        : 'pending',
                    'paid_online_amount' => $booking['paid_online_amount'] ?? 0,
                    'metadata' => ['created_from_owner_submit' => true],
                ]
            );
        }
    }

    private function futureBookingsQuery(string|int $clusterId): Builder
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        return Booking::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', self::OPEN_BOOKING_STATUSES)
            ->where(function ($query) use ($today, $time): void {
                $query->whereDate('booking_date', '>', $today)
                    ->orWhere(function ($todayQuery) use ($today, $time): void {
                        $todayQuery->whereDate('booking_date', $today)
                            ->where('start_time', '>=', $time);
                    });
            });
    }

    private function futureBookingsPayload(string|int $clusterId, ?PartnerTerminationRequest $termination = null): array
    {
        $actions = $termination
            ? $termination->bookingActions()->get()->keyBy('booking_id')
            : collect();

        return $this->futureBookingsQuery($clusterId)
            ->with(['customer:id,full_name,username,email,phone', 'venueCourt:id,name,court_type_id', 'venueCourt.courtType:id,name', 'payments'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Booking $booking) use ($actions): array {
                $action = $actions->get($booking->id);

                return [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'customer' => $booking->customer,
                    'venue_court' => $booking->venueCourt,
                    'booking_date' => $booking->booking_date?->toDateString(),
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'total_price' => (float) $booking->total_price,
                    'paid_online_amount' => $this->paidOnlineAmount($booking),
                    'status' => $booking->status,
                    'payment_option' => $booking->payment_option,
                    'current_action' => $action?->action,
                    'action_status' => $action?->status,
                ];
            })
            ->values()
            ->all();
    }

    private function paidOnlineAmount(Booking $booking): float
    {
        $booking->loadMissing('payments');

        return round((float) $booking->payments
            ->where('status', 'paid')
            ->filter(fn (Payment $payment): bool => $payment->method !== 'cash')
            ->sum('amount'), 2);
    }

    private function allFutureBookingsResolved(PartnerTerminationRequest $termination): bool
    {
        $futureBookings = $this->futureBookingsQuery($termination->venue_cluster_id)
            ->with('payments')
            ->get();

        if ($futureBookings->isEmpty()) {
            return true;
        }

        $actions = $termination->bookingActions()->get()->keyBy('booking_id');

        return $futureBookings->every(function (Booking $booking) use ($actions): bool {
            if (in_array($booking->status, self::RESOLVED_BOOKING_STATUSES, true)) {
                return true;
            }

            $action = $actions->get($booking->id);
            if (! $action) {
                return false;
            }

            if ($action->status === 'resolved') {
                return true;
            }

            return $action->action === self::POLICY_SERVE_UNTIL_LAST && $booking->status === 'completed';
        });
    }

    private function readyForFinalDocument(PartnerTerminationRequest $termination): bool
    {
        $termination = $this->refreshAmounts($termination);

        if (! $this->allFutureBookingsResolved($termination)) {
            return false;
        }

        if ((float) $termination->pending_refund_liability > 0 || (float) $termination->pending_withdrawal_amount > 0) {
            return false;
        }

        return (float) $termination->owner_balance_total <= 0.01 || $termination->manual_debt_resolved_at !== null;
    }

    private function latestOwnerRequestGeneratedDocument(PartnerTerminationRequest $termination): GeneratedDocument
    {
        $document = $termination->documents()
            ->with('generatedDocument')
            ->where('document_type', 'owner_termination_request')
            ->latest()
            ->first()?->generatedDocument;

        if (! $document) {
            throw ValidationException::withMessages(['document' => 'Khong tim thay don yeu cau cham dut de ky.']);
        }

        return $document;
    }

    private function latestFinalGeneratedDocument(PartnerTerminationRequest $termination): GeneratedDocument
    {
        $document = $termination->documents()
            ->with('generatedDocument.signatures')
            ->whereIn('document_type', ['settlement_minutes', 'final_termination_file'])
            ->latest()
            ->first()?->generatedDocument;

        if (! $document) {
            throw ValidationException::withMessages(['document' => 'Chua co bien ban cham dut cuoi de ky.']);
        }

        return $document;
    }

    private function lockClusterForTermination(PartnerTerminationRequest $termination, User $owner): void
    {
        VenueCluster::query()
            ->whereKey($termination->venue_cluster_id)
            ->update([
                'status' => 'termination_processing',
                'status_reason' => 'Chu san da ky gui yeu cau cham dut hop dong doi tac. Cum san tam ngung nhan booking moi.',
                'locked_at' => now(),
                'locked_by' => $owner->id,
            ]);

        $termination->contract?->forceFill(['status' => 'termination_requested'])->save();
    }

    private function unlockClusterAfterOwnerCancel(PartnerTerminationRequest $termination): void
    {
        VenueCluster::query()
            ->whereKey($termination->venue_cluster_id)
            ->whereIn('status', ['termination_processing', 'termination_locked'])
            ->update([
                'status' => 'active',
                'status_reason' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);

        $termination->contract?->forceFill(['status' => 'signed_active'])->save();
    }

    private function assertOwner(PartnerTerminationRequest $termination, User $owner): void
    {
        if ((string) $termination->owner_id !== (string) $owner->id) {
            abort(403, 'Ban khong co quyen thao tac ho so cham dut nay.');
        }
    }

    private function assertFinalSigner(PartnerTerminationRequest $termination, User $signer, string $signerSide): void
    {
        if (! in_array($signerSide, ['owner', 'sportgo'], true)) {
            abort(403);
        }

        if ($signerSide === 'owner') {
            $this->assertOwner($termination, $signer);
        }
    }

    private function assertCanOwnerCancel(PartnerTerminationRequest $termination): void
    {
        $termination->loadMissing('bookingActions');
        if ($termination->admin_locked_owner_cancel) {
            throw ValidationException::withMessages(['status' => 'Admin da khoa quyen huy yeu cau nay.']);
        }

        if ($termination->final_document_ready_at || $termination->final_document_admin_signed_at || $termination->final_document_owner_signed_at) {
            throw ValidationException::withMessages(['status' => 'Yeu cau da vao buoc ky bien ban cham dut cuoi, khong the huy.']);
        }

        if (! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
            throw ValidationException::withMessages(['status' => 'Trang thai hien tai khong cho phep owner huy yeu cau.']);
        }

        $hasIrreversible = $termination->bookingActions
            ->contains(fn (PartnerTerminationBookingAction $action): bool => $action->action === self::POLICY_CANCEL_ALL && $action->status === 'resolved');
        if ($hasIrreversible) {
            throw ValidationException::withMessages(['booking' => 'Da co booking bi huy/hoan tien, can admin xu ly thu cong neu muon dung quy trinh.']);
        }
    }

    private function setStatusIfChanged(PartnerTerminationRequest $termination, string $status, ?User $actor, string $actorType, ?string $reason): void
    {
        if ($termination->status === $status) {
            return;
        }

        $old = $termination->status;
        $termination->forceFill(['status' => $status])->save();
        $this->history($termination, $old, $status, $actor, $actorType, $reason);
    }

    private function history(PartnerTerminationRequest $termination, ?string $oldStatus, string $newStatus, ?User $actor, string $actorType, ?string $reason): void
    {
        $termination->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor?->id,
            'actor_type' => $actorType,
            'reason' => $reason,
            'metadata' => [],
            'created_at' => now(),
        ]);
    }

    private function notifyAfterOwnerSubmit(PartnerTerminationRequest $termination): void
    {
        $owner = $termination->owner;
        if ($owner) {
            $this->mail->queue($owner, new PartnerTerminationReceivedMail([
                'owner_name' => $this->ownerSignerName($termination),
                'contract_code' => $termination->contract?->contract_code,
                'requested_at' => $this->timestamp($termination->requested_at),
                'reason' => $termination->reason,
                'status_url' => url('/owner/termination-requests/' . $termination->id),
            ]));
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin', 'system_staff', 'partner_manager']))
            ->pluck('id')
            ->each(function (string|int $adminId) use ($termination): void {
                Notification::query()->create([
                    'user_id' => $adminId,
                    'type' => 'partner_termination_requested',
                    'title' => 'Co yeu cau cham dut hop dong doi tac',
                    'body' => ($termination->venueCluster?->name ?: $termination->termination_code) . ' vua gui yeu cau cham dut hop dong.',
                    'reference_type' => 'partner_termination_request',
                    'reference_id' => $termination->id,
                    'data' => [
                        'termination_code' => $termination->termination_code,
                        'venue_cluster_id' => $termination->venue_cluster_id,
                    ],
                ]);
            });
    }

    private function gracePeriodDays(): int
    {
        return max(0, SystemSetting::integer('partner_termination_view_grace_days', 14));
    }

    private function uniqueTerminationCode(string $prefix): string
    {
        do {
            $code = 'TERM-' . $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (PartnerTerminationRequest::query()->where('termination_code', $code)->exists());

        return $code;
    }

    private function uniqueWithdrawalCode(): string
    {
        do {
            $code = 'WR-' . now()->format('ymd') . '-' . Str::upper(Str::random(8));
        } while (OwnerWithdrawalRequest::query()->where('request_code', $code)->exists());

        return $code;
    }

    private function ownerSignerName(PartnerTerminationRequest $termination): string
    {
        return $termination->contract?->application?->representative_name
            ?: $termination->contract?->application?->applicant_full_name
            ?: $termination->owner?->full_name
            ?: $termination->owner?->username
            ?: $termination->owner?->email
            ?: 'Chu san';
    }

    private function bankSnapshot(PartnerTerminationRequest $termination): string
    {
        $application = $termination->contract?->application;

        return trim(($application?->account_number ?: '-') . ' - ' . ($application?->bank_name ?: '-') . ' - ' . ($application?->account_holder_name ?: '-'));
    }

    private function policyLabel(?string $policy): string
    {
        return collect($this->futureBookingPolicies())->firstWhere('value', $policy)['label'] ?? ($policy ?: '-');
    }

    private function bookingActionLabel(?string $action): string
    {
        return match ($action) {
            self::POLICY_CANCEL_ALL => 'Huy va hoan ve so du/khoan hoan tien user',
            self::POLICY_SERVE_UNTIL_LAST => 'Giu lai phuc vu den booking cuoi',
            self::POLICY_MANUAL => 'Admin/owner xu ly thu cong',
            default => $action ?: '-',
        };
    }

    private function timestamp(mixed $value): string
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
