<?php

namespace App\Services\Partner;

use App\Mail\Partner\PartnerTerminationReceivedMail;
use App\Mail\Partner\PartnerUnilateralTerminationMail;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\DocumentSigningRequest;
use App\Models\GeneratedDocument;
use App\Models\Notification;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWallet;
use App\Models\OwnerWalletLedger;
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
use App\Models\UserWallet;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PlatformFeeServicePeriod;
use App\Models\PlatformFeeWalletHold;
use App\Models\VenuePlatformFeeProfile;
use App\Services\Bookings\OwnerBookingCancellationService;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerTerminationFlowService
{
    public const STATUS_DRAFT_PREVIEW = 'draft';
    public const STATUS_IN_PROGRESS = 'submitted';
    public const STATUS_FUTURE_BOOKINGS = 'reviewing';
    public const STATUS_WAITING_SETTLEMENT = 'settlement_processing';
    public const STATUS_WAITING_FINAL_SIGNATURE = 'pending_signature';
    public const STATUS_TERMINATING = 'transition_period';
    public const STATUS_TERMINATED = 'completed';
    public const STATUS_OWNER_CANCELLED = 'cancelled';
    public const STATUS_ADMIN_REJECTED = 'rejected';

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
        'pending_owner_confirmation',
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
        $activeRequestPayload = $activeRequest?->load($this->requestRelations());
        $latestClosedRequest = PartnerTerminationRequest::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', [self::STATUS_OWNER_CANCELLED, self::STATUS_ADMIN_REJECTED, self::STATUS_TERMINATED])
            ->latest('updated_at')
            ->first()
            ?->load($this->requestRelations());
        $summary = $this->financialSummary($cluster);

        return [
            'eligible' => $cluster->status === 'active' && $contract !== null && ($activeRequest === null || $activeRequest->status === self::STATUS_DRAFT_PREVIEW),
            'reason' => $this->eligibilityReason($cluster, $contract, $activeRequest),
            'cluster' => $cluster,
            'contract' => $contract,
            'active_request' => $activeRequestPayload,
            'latest_closed_request' => $latestClosedRequest,
            'summary' => $summary,
            'policies' => $this->futureBookingPolicies(),
            'warning' => 'Khi gửi yêu cầu chấm dứt, cụm sân sẽ bị khóa thao tác quản lý bình thường. Chủ sân chỉ còn quyền xử lý booking, hoàn tiền, yêu cầu rút tiền và theo dõi hồ sơ chấm dứt.',
        ];
    }

    public function previewOwnerRequest(User $owner, string|int $clusterId, array $data, Request $httpRequest): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($owner, $clusterId, $data, $httpRequest): PartnerTerminationRequest {
            $cluster = $this->ownedCluster($owner, $clusterId);
            $contract = $this->activeContractForCluster($cluster, $owner);

            if (! $contract) {
                throw ValidationException::withMessages([
                    'contract' => 'Cụm sân chưa có hợp đồng đang hiệu lực để gửi yêu cầu chấm dứt.',
                ]);
            }

            if ($cluster->status !== 'active') {
                throw ValidationException::withMessages([
                    'venue_cluster_id' => 'Chỉ cụm sân đang hoạt động mới được gửi yêu cầu chấm dứt.',
                ]);
            }

            $active = $this->activeRequestForCluster($cluster->id, true);
            if ($active && $active->status !== self::STATUS_DRAFT_PREVIEW) {
                throw ValidationException::withMessages([
                    'termination' => 'Cụm sân này đang có yêu cầu chấm dứt chưa hoàn tất.',
                ]);
            }

            $summary = $this->financialSummary($cluster);
            if ((int) $summary['future_booking_count'] > 0 && empty($data['future_booking_policy'])) {
                throw ValidationException::withMessages([
                    'future_booking_policy' => 'Vui lòng chọn phương án xử lý booking tương lai.',
                ]);
            }

            $termination = $active ?: new PartnerTerminationRequest();
            $this->fillTermination($termination, [
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
                'platform_fee_outstanding_amount' => $summary['platform_fee_outstanding_amount'],
                'platform_fee_prepaid_refund_amount' => $summary['platform_fee_prepaid_refund_amount'],
                'platform_fee_hold_amount' => $summary['platform_fee_hold_amount'],
                'platform_fee_settlement_status' => $summary['platform_fee_settlement_status'],
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

            $document = $this->generateOwnerRequestDocument(
                $termination->fresh(['contract.application.user', 'venueCluster']),
                $owner,
                'pending_owner_signature',
                $data,
                $summary
            );
            PartnerTerminationDocument::query()->create([
                'partner_termination_request_id' => $termination->id,
                'generated_document_id' => $document->id,
                'document_type' => 'owner_termination_request',
                'file_path' => $document->generated_file_path,
                'status' => 'pending_signature',
                'generated_by' => $owner->id,
                'generated_at' => now(),
            ]);

            $this->history($termination, $active?->status, self::STATUS_DRAFT_PREVIEW, $owner, 'owner', 'Chủ sân xem trước đơn yêu cầu chấm dứt hợp đồng.');

            return $termination->fresh($this->requestRelations());
        });
    }

    public function sendOwnerRequestOtp(PartnerTerminationRequest $termination, User $owner, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $this->assertOwner($termination, $owner);
        if ($termination->status !== self::STATUS_DRAFT_PREVIEW) {
            throw ValidationException::withMessages([
                'status' => 'Đơn yêu cầu không ở trạng thái chờ chủ sân ký.',
            ]);
        }

        return $this->signing->requestOtp(
            $this->latestOwnerRequestGeneratedDocument($termination),
            $owner,
            'owner',
            'owner_sign_partner_termination_request',
            'Tôi xác nhận đã đọc cảnh báo và đồng ý ký gửi yêu cầu chấm dứt hợp đồng đối tác SportGo.',
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
                    'status' => 'Đơn yêu cầu không còn ở trạng thái chờ ký gửi.',
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
                'signer_title' => $termination->contract?->application?->representative_position ?: 'Chủ sân',
                'signer_organization' => $termination->contract?->application?->business_name,
            ]);
            $this->signing->markSigned($verified, $signature);

            PartnerTerminationDocument::query()
                ->where('partner_termination_request_id', $termination->id)
                ->where('generated_document_id', $document->id)
                ->update(['status' => 'signed']);

            $summary = $this->financialSummary($termination->venueCluster);
            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'status' => self::STATUS_IN_PROGRESS,
                'platform_fee_cutoff_at' => $this->platformFeeCutoffAt($termination, $summary['future_bookings']),
                'future_booking_count' => $summary['future_booking_count'],
                'owner_balance_total' => $summary['owner_balance_total'],
                'future_online_booking_liability' => $summary['future_online_booking_liability'],
                'pending_refund_liability' => $summary['pending_refund_liability'],
                'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
                'withdrawable_amount' => $summary['withdrawable_amount'],
                'platform_fee_outstanding_amount' => $summary['platform_fee_outstanding_amount'],
                'platform_fee_accrued_amount' => $summary['platform_fee_accrued_amount'],
                'platform_fee_prepaid_refund_amount' => $summary['platform_fee_prepaid_refund_amount'],
                'platform_fee_hold_amount' => $summary['platform_fee_hold_amount'],
                'platform_fee_settlement_status' => $summary['platform_fee_settlement_status'],
                'future_booking_summary' => $summary['future_bookings'],
                'metadata' => array_merge($termination->metadata ?: [], [
                    'owner_signed_request_at' => now()->toIso8601String(),
                    'owner_signed_request_document_id' => $document->id,
                ]),
            ])->save();

            VenuePlatformFeeProfile::query()
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->update(['last_fee_cutoff_at' => $termination->platform_fee_cutoff_at]);
            $termination = $this->refreshAmounts($termination);

            $this->syncFutureBookingActions($termination, $summary['future_bookings']);
            $this->lockClusterForTermination($termination, $owner);
            $this->history($termination, $oldStatus, self::STATUS_IN_PROGRESS, $owner, 'owner', 'Chủ sân đã ký và gửi yêu cầu chấm dứt hợp đồng.');
            $this->notifyAfterOwnerSubmit($termination);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function showForOwner(PartnerTerminationRequest $termination, User $owner): PartnerTerminationRequest
    {
        $this->assertOwner($termination, $owner);
        $this->refreshAmounts($termination);

        return $this->withFinancialSummary($termination->fresh($this->requestRelations()));
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
            if (! $this->hasTerminationBookingActionsTable()) {
                throw ValidationException::withMessages([
                    'booking' => 'Chua co bang luu thao tac booking cham dut. Vui long chay migration truoc khi xu ly booking tuong lai.',
                ]);
            }

            $termination = PartnerTerminationRequest::query()
                ->with('venueCluster')
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu không còn cho phép xử lý booking tương lai.',
                ]);
            }

            $validActions = [
                self::POLICY_CANCEL_ALL,
                self::POLICY_SERVE_UNTIL_LAST,
                self::POLICY_MANUAL,
            ];
            if (! in_array($action, $validActions, true)) {
                throw ValidationException::withMessages(['action' => 'Phương án xử lý booking không hợp lệ.']);
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
                        $reason ?: 'Chủ sân chấm dứt hợp đồng, hủy booking tương lai và hoàn về số dư/khoản hoàn tiền user.',
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
                    'amount' => 'Số tiền rút vượt quá phần được phép rút khi đang chấm dứt hợp đồng.',
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

    public function previewOwnerCancellation(PartnerTerminationRequest $termination, User $owner, string $reason): GeneratedDocument
    {
        $this->assertOwner($termination, $owner);
        $this->assertCanOwnerCancel($termination);
        $termination->loadMissing(['owner', 'venueCluster', 'contract']);
        $originalDocument = $this->latestOwnerRequestGeneratedDocument($termination);

        return $this->documents->generateDocument(
            'termination_cancellation_request',
            $termination,
            [
                'document_place' => 'Hà Nội',
                'document_day' => now()->format('d'),
                'document_month' => now()->format('m'),
                'document_year' => now()->format('Y'),
                'owner_name' => $this->ownerSignerName($termination),
                'owner_email' => $owner->email,
                'owner_phone' => $owner->phone,
                'venue_name' => $termination->venueCluster?->name,
                'contract_code' => $termination->contract?->contract_code ?: $termination->contract?->contract_number,
                'termination_code' => $termination->termination_code,
                'original_document_code' => $originalDocument->document_code,
                'cancellation_reason' => $reason,
                'cancellation_requested_at' => now()->format('d/m/Y H:i'),
            ],
            $owner,
            [
                'title' => 'Đơn xác nhận hủy yêu cầu chấm dứt hợp tác ' . ($termination->venueCluster?->name ?: ''),
                'status' => 'pending_owner_signature',
                'partner_application_id' => $termination->partner_application_id,
                'partner_contract_id' => $termination->partner_contract_id,
                'partner_termination_request_id' => $termination->id,
                'owner_id' => $owner->id,
                'venue_cluster_id' => $termination->venue_cluster_id,
            ]
        );
    }

    public function sendOwnerCancelOtp(PartnerTerminationRequest $termination, User $owner, int $generatedDocumentId, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $this->assertOwner($termination, $owner);
        $this->assertCanOwnerCancel($termination);
        $document = $this->cancellationGeneratedDocument($termination, $generatedDocumentId);

        return $this->signing->requestOtp(
            $document,
            $owner,
            'owner',
            'owner_cancel_partner_termination_request',
            'Tôi xác nhận hủy yêu cầu chấm dứt hợp đồng và chấp nhận các xử lý đã phát sinh sẽ không tự động rollback.',
            $signatureImage,
            $request
        );
    }

    public function cancelOwnerRequest(PartnerTerminationRequest $termination, User $owner, int $signingRequestId, string $otp, string $reason, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $owner, $signingRequestId, $otp, $reason, $request): PartnerTerminationRequest {
            $this->assertOwner($termination, $owner);
            $this->assertCanOwnerCancel($termination);

            $signingRequest = DocumentSigningRequest::query()
                ->with('document')
                ->whereKey($signingRequestId)
                ->where('signer_side', 'owner')
                ->where('action', 'owner_cancel_partner_termination_request')
                ->firstOrFail();
            $document = $signingRequest->document;
            if (! $document
                || $document->document_type !== 'termination_cancellation_request'
                || (int) $document->partner_termination_request_id !== (int) $termination->id) {
                throw ValidationException::withMessages([
                    'document' => 'Văn bản hủy không thuộc hồ sơ chấm dứt này.',
                ]);
            }

            $verified = $this->signing->verifyOtp($signingRequest, $owner, $otp);
            $signature = $this->documents->signDocument($document, $owner, 'owner', $verified->signature_image, $request, [
                'signer_full_name' => $this->ownerSignerName($termination),
            ]);
            $this->signing->markSigned($verified, $signature);

            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'status' => self::STATUS_OWNER_CANCELLED,
                'owner_cancel_reason' => $reason,
                'owner_cancelled_at' => now(),
                'owner_cancelled_by' => $owner->id,
                'reactivation_billing_started_at' => now(),
            ])->save();

            VenuePlatformFeeProfile::query()
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->update(['fee_started_at' => now()]);

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

        return $this->withFinancialSummary($termination->fresh($this->requestRelations()));
    }

    public function previewUnilateralNotice(PartnerContract $contract, User $admin, array $data, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($contract, $admin, $data, $request): PartnerTerminationRequest {
            $contract = PartnerContract::query()
                ->with(['application.user', 'venueCluster'])
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($contract->status !== 'signed_active') {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể tạo công văn cho hợp đồng đang hiệu lực.',
                ]);
            }

            if ($this->activeRequestForCluster($contract->venue_cluster_id, true)) {
                throw ValidationException::withMessages([
                    'termination' => 'Cụm sân đang có hồ sơ chấm dứt chưa hoàn tất.',
                ]);
            }

            $cluster = $contract->venueCluster ?: VenueCluster::query()->findOrFail($contract->venue_cluster_id);
            $summary = $this->financialSummary($cluster);
            $effectiveDate = Carbon::parse($data['requested_effective_date'] ?? now()->addDays(30))->toDateString();
            $termination = new PartnerTerminationRequest();
            $this->fillTermination($termination, [
                'termination_code' => $this->uniqueTerminationCode('SPORTGO'),
                'partner_contract_id' => $contract->id,
                'partner_application_id' => $contract->partner_application_id,
                'owner_id' => $contract->owner_id,
                'venue_cluster_id' => $contract->venue_cluster_id,
                'termination_type' => 'unilateral_by_sportgo',
                'requested_by' => $admin->id,
                'requested_at' => now(),
                'reason' => $data['reason'],
                'detail_reason' => $data['detail_reason'] ?? null,
                'requested_effective_date' => $effectiveDate,
                'future_booking_policy' => $data['future_booking_policy'] ?? self::POLICY_MANUAL,
                'future_booking_count' => $summary['future_booking_count'],
                'owner_balance_total' => $summary['owner_balance_total'],
                'future_online_booking_liability' => $summary['future_online_booking_liability'],
                'pending_refund_liability' => $summary['pending_refund_liability'],
                'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
                'withdrawable_amount' => $summary['withdrawable_amount'],
                'platform_fee_outstanding_amount' => $summary['platform_fee_outstanding_amount'],
                'platform_fee_prepaid_refund_amount' => $summary['platform_fee_prepaid_refund_amount'],
                'platform_fee_hold_amount' => $summary['platform_fee_hold_amount'],
                'platform_fee_settlement_status' => $summary['platform_fee_settlement_status'],
                'future_booking_summary' => $summary['future_bookings'],
                'status' => self::STATUS_DRAFT_PREVIEW,
                'metadata' => [
                    'notice_preview_created_at' => now()->toIso8601String(),
                    'notice_preview_created_by' => $admin->id,
                    'notice_preview_ip' => $request->ip(),
                ],
            ])->save();

            $document = $this->generateUnilateralNoticeDocument($termination, $contract, $admin, $summary, $data);
            PartnerTerminationDocument::query()->create([
                'partner_termination_request_id' => $termination->id,
                'generated_document_id' => $document->id,
                'document_type' => 'unilateral_notice',
                'file_path' => $document->generated_file_path,
                'status' => 'pending_signature',
                'generated_by' => $admin->id,
                'generated_at' => now(),
            ]);

            $this->history($termination, null, self::STATUS_DRAFT_PREVIEW, $admin, 'admin', 'Tạo bản xem trước công văn chấm dứt. Công văn chưa được gửi cho chủ sân.');

            return $termination->fresh($this->requestRelations());
        });
    }

    public function sendUnilateralNoticeOtp(PartnerTerminationRequest $termination, User $admin, string $signatureImage, Request $request): DocumentSigningRequest
    {
        $this->assertUnilateralNoticeDraft($termination);
        $document = $this->latestUnilateralNoticeGeneratedDocument($termination);

        return $this->signing->requestOtp(
            $document,
            $admin,
            'sportgo',
            'admin_sign_unilateral_termination_notice',
            'Tôi xác nhận đã kiểm tra toàn bộ công văn và ký với vai trò đại diện SportGo được ủy quyền.',
            $signatureImage,
            $request
        );
    }

    public function signAndIssueUnilateralNotice(
        PartnerTerminationRequest $termination,
        User $admin,
        ?int $signingRequestId,
        ?string $otp,
        Request $request,
        ?string $signatureImage = null
    ): PartnerTerminationRequest {
        return DB::transaction(function () use ($termination, $admin, $signingRequestId, $otp, $request, $signatureImage): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()
                ->with(['contract.application.user', 'venueCluster', 'owner'])
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertUnilateralNoticeDraft($termination);

            $document = $this->latestUnilateralNoticeGeneratedDocument($termination);
            if (! $signatureImage) {
                throw ValidationException::withMessages([
                    'signature_image' => 'Admin cần ký trực tiếp bằng phiên đăng nhập hiện tại.',
                ]);
            }
            $verified = $this->signing->approveWithAuthenticatedSession(
                $document,
                $admin,
                'sportgo',
                'admin_sign_unilateral_termination_notice',
                'Tôi xác nhận đã kiểm tra toàn bộ công văn và ký với vai trò đại diện SportGo được ủy quyền.',
                $signatureImage,
                $request
            );
            $signature = $this->documents->signDocument($document, $admin, 'sportgo', $verified->signature_image, $request, [
                'signature_method' => 'drawn',
                'signer_full_name' => $admin->full_name ?: $admin->username,
                'signer_title' => 'Đại diện SportGo',
                'signer_organization' => 'SportGo',
            ]);
            $this->signing->markSigned($verified, $signature);

            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'status' => self::STATUS_IN_PROGRESS,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'metadata' => array_merge($termination->metadata ?: [], [
                    'notice_issued_at' => now()->toIso8601String(),
                    'notice_issued_by' => $admin->id,
                    'notice_issued_ip' => $request->ip(),
                    'owner_acknowledged_at' => null,
                ]),
            ])->save();

            VenueCluster::query()->whereKey($termination->venue_cluster_id)->update([
                'status' => 'locked',
                'status_reason' => 'SportGo đã gửi công văn chấm dứt hợp tác. Cụm sân tạm ngưng nhận booking mới trong khi xử lý booking và công nợ.',
                'locked_at' => now(),
                'locked_by' => $admin->id,
            ]);
            PartnerTerminationDocument::query()
                ->where('partner_termination_request_id', $termination->id)
                ->where('generated_document_id', $document->id)
                ->update(['status' => 'signed']);
            $this->history($termination, $oldStatus, self::STATUS_IN_PROGRESS, $admin, 'admin', 'SportGo ký và gửi công văn chấm dứt cho chủ sân.');
            $this->notifyOwnerAboutUnilateralNotice($termination);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function acknowledgeUnilateralNotice(PartnerTerminationRequest $termination, User $owner, Request $request): PartnerTerminationRequest
    {
        $updated = DB::transaction(function () use ($termination, $owner, $request): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($termination, $owner);
            if ($termination->termination_type !== 'unilateral_by_sportgo' || $termination->status !== self::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Công văn không ở trạng thái chờ chủ sân xác nhận đã nhận.']);
            }

            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'status' => self::STATUS_FUTURE_BOOKINGS,
                'metadata' => array_merge($termination->metadata ?: [], [
                    'owner_acknowledged_at' => now()->toIso8601String(),
                    'owner_acknowledged_by' => $owner->id,
                    'owner_acknowledged_ip' => $request->ip(),
                    'owner_acknowledged_user_agent' => (string) $request->userAgent(),
                ]),
            ])->save();
            $this->history($termination, $oldStatus, self::STATUS_FUTURE_BOOKINGS, $owner, 'owner', 'Chủ sân xác nhận đã nhận và đọc công văn chấm dứt.');
            $this->notifyAdminsAboutUnilateral($termination, 'Chủ sân đã nhận công văn', 'Chủ sân đã xác nhận nhận công văn và bắt đầu xử lý booking/công nợ.');

            return $termination;
        });

        return $this->refreshProgress($updated)->fresh($this->requestRelations());
    }

    public function requestUnilateralReconsideration(PartnerTerminationRequest $termination, User $owner, string $reason): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $owner, $reason): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            $this->assertOwner($termination, $owner);
            if ($termination->termination_type !== 'unilateral_by_sportgo' || ! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
                throw ValidationException::withMessages(['status' => 'Trạng thái hiện tại không cho phép gửi yêu cầu xem xét lại công văn.']);
            }

            $metadata = $termination->metadata ?: [];
            $reconsiderations = collect($metadata['reconsiderations'] ?? [])->push([
                'reason' => $reason,
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $owner->id,
                'status' => 'pending',
            ])->values()->all();
            $this->fillTermination($termination, [
                'metadata' => array_merge($metadata, [
                    'reconsideration_pending' => true,
                    'latest_reconsideration_reason' => $reason,
                    'reconsiderations' => $reconsiderations,
                ]),
            ])->save();
            $this->history($termination, $termination->status, $termination->status, $owner, 'owner', 'Chủ sân yêu cầu xem xét lại công văn: ' . $reason);
            $this->notifyAdminsAboutUnilateral($termination, 'Chủ sân yêu cầu xem xét lại công văn', $reason);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function resolveUnilateralReconsideration(PartnerTerminationRequest $termination, User $admin, string $note): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $admin, $note): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            $metadata = $termination->metadata ?: [];
            if ($termination->termination_type !== 'unilateral_by_sportgo' || empty($termination->workflow_state['reconsideration_pending'])) {
                throw ValidationException::withMessages(['status' => 'Không có yêu cầu xem xét lại đang chờ xử lý.']);
            }

            $reconsiderations = collect($metadata['reconsiderations'] ?? [])->map(function ($item) use ($admin, $note) {
                if (($item['status'] ?? null) === 'pending') {
                    return array_merge($item, [
                        'status' => 'notice_kept',
                        'resolved_at' => now()->toIso8601String(),
                        'resolved_by' => $admin->id,
                        'resolution_note' => $note,
                    ]);
                }

                return $item;
            })->values()->all();
            $this->fillTermination($termination, [
                'metadata' => array_merge($metadata, [
                    'reconsideration_pending' => false,
                    'reconsideration_resolution_note' => $note,
                    'reconsiderations' => $reconsiderations,
                ]),
            ])->save();
            $this->history($termination, $termination->status, $termination->status, $admin, 'admin', 'Admin giữ nguyên công văn sau khi xem xét: ' . $note);
            $this->notifyOwnerAboutUnilateralResolution($termination, 'SportGo đã xem xét phản hồi', 'Công văn được giữ nguyên. ' . $note);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function withdrawUnilateralNotice(PartnerTerminationRequest $termination, User $admin, string $reason): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $admin, $reason): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->with('contract')->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            if ($termination->termination_type !== 'unilateral_by_sportgo' || ! in_array($termination->status, [self::STATUS_DRAFT_PREVIEW, self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
                throw ValidationException::withMessages(['status' => 'Công văn không còn ở trạng thái cho phép thu hồi.']);
            }
            if ($termination->final_document_ready_at || $termination->final_document_admin_signed_at || $termination->final_document_owner_signed_at) {
                throw ValidationException::withMessages(['status' => 'Đã sinh hoặc ký biên bản cuối nên không thể thu hồi công văn.']);
            }

            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'status' => self::STATUS_OWNER_CANCELLED,
                'metadata' => array_merge($termination->metadata ?: [], [
                    'notice_withdrawn_at' => now()->toIso8601String(),
                    'notice_withdrawn_by' => $admin->id,
                    'notice_withdraw_reason' => $reason,
                    'reconsideration_pending' => false,
                ]),
            ])->save();
            VenueCluster::query()
                ->whereKey($termination->venue_cluster_id)
                ->where('status', 'locked')
                ->where('status_reason', 'like', '%công văn chấm dứt%')
                ->update([
                    'status' => 'active',
                    'status_reason' => null,
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            $termination->contract?->forceFill(['status' => 'signed_active'])->save();
            $this->history($termination, $oldStatus, self::STATUS_OWNER_CANCELLED, $admin, 'admin', 'SportGo thu hồi công văn: ' . $reason);
            $this->notifyOwnerAboutUnilateralResolution($termination, 'SportGo đã thu hồi công văn chấm dứt', $reason);

            return $termination->fresh($this->requestRelations());
        });
    }

    public function confirmOwnerRequest(PartnerTerminationRequest $termination, User $admin, Request $request): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $admin, $request): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()
                ->with(['venueCluster', 'contract.application.user'])
                ->whereKey($termination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu chấm dứt không ở trạng thái chờ admin xác nhận.',
                ]);
            }

            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'metadata' => array_merge($termination->metadata ?: [], [
                    'admin_confirmed_at' => now()->toIso8601String(),
                    'admin_confirmed_by' => $admin->id,
                    'admin_confirmed_ip' => $request->ip(),
                ]),
            ])->save();

            $this->history(
                $termination,
                $oldStatus,
                $termination->status,
                $admin,
                'admin',
                'Admin xác nhận yêu cầu chấm dứt đã được chủ sân ký.'
            );

            return $this->refreshProgress($termination)->fresh($this->requestRelations());
        });
    }

    public function markReadyForFinalDocument(PartnerTerminationRequest $termination, User $admin, ?string $note = null): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $admin, $note): PartnerTerminationRequest {
            $termination = PartnerTerminationRequest::query()->whereKey($termination->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $termination->status;
            $this->fillTermination($termination, [
                'manual_debt_resolved_at' => now(),
                'manual_debt_resolved_by' => $admin->id,
            ])->save();

            $this->generateFinalDocumentIfReady($termination, $admin, true);
            $this->history($termination, $oldStatus, $termination->fresh()->status, $admin, 'admin', $note ?: 'Admin xác nhận đủ điều kiện sinh văn bản chấm dứt cuối.');

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
                ? 'Tôi xác nhận đại diện SportGo ký biên bản chấm dứt hợp đồng cuối cùng.'
                : 'Tôi xác nhận đã đối soát và ký xác nhận biên bản chấm dứt hợp đồng cuối cùng.',
            $signatureImage,
            $request
        );
    }

    public function signFinalDocument(PartnerTerminationRequest $termination, User $signer, string $signerSide, ?int $signingRequestId, ?string $otp, Request $request, ?string $signatureImage = null): PartnerTerminationRequest
    {
        return DB::transaction(function () use ($termination, $signer, $signerSide, $signingRequestId, $otp, $request, $signatureImage): PartnerTerminationRequest {
            $this->assertFinalSigner($termination, $signer, $signerSide);
            $document = $this->latestFinalGeneratedDocument($termination);
            if ($signerSide === 'sportgo') {
                if (! $signatureImage) {
                    throw ValidationException::withMessages([
                        'signature_image' => 'Admin cần ký trực tiếp bằng phiên đăng nhập hiện tại.',
                    ]);
                }
                $verified = $this->signing->approveWithAuthenticatedSession(
                    $document,
                    $signer,
                    $signerSide,
                    'admin_sign_partner_final_termination_document',
                    'Tôi xác nhận đại diện SportGo đã kiểm tra và ký biên bản chấm dứt hợp đồng cuối cùng.',
                    $signatureImage,
                    $request
                );
            } else {
                $signingRequest = DocumentSigningRequest::query()
                    ->whereKey($signingRequestId)
                    ->where('generated_document_id', $document->id)
                    ->where('signer_side', $signerSide)
                    ->firstOrFail();
                $verified = $this->signing->verifyOtp($signingRequest, $signer, (string) $otp);
            }
            $signature = $this->documents->signDocument($document, $signer, $signerSide, $verified->signature_image, $request, [
                'signature_method' => 'drawn',
                'signer_full_name' => $signerSide === 'owner' ? $this->ownerSignerName($termination) : ($signer->full_name ?: $signer->username),
                'signer_title' => $signerSide === 'owner'
                    ? ($termination->contract?->application?->representative_position ?: 'Chủ sân')
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
            $this->fillTermination($termination, $updates)->save();

            $document = $document->fresh('signatures');
            if ($document->status === 'completed' || $document->signatures()->where('status', 'signed')->whereIn('signer_side', ['owner', 'sportgo'])->distinct('signer_side')->count('signer_side') >= 2) {
                $graceDays = $this->gracePeriodDays();
                $this->fillTermination($termination, [
                    'status' => self::STATUS_TERMINATING,
                    'effective_termination_date' => now(),
                    'final_document_completed_at' => now(),
                    'grace_period_days' => $graceDays,
                    'owner_access_view_until' => now()->addDays($graceDays),
                    'transition_end_at' => now()->addDays($graceDays),
                ])->save();
                PartnerContract::query()
                    ->whereKey($termination->partner_contract_id)
                    ->update([
                        'status' => 'terminated',
                        'terminated_at' => now(),
                    ]);
                PartnerTerminationDocument::query()
                    ->where('partner_termination_request_id', $termination->id)
                    ->where('generated_document_id', $document->id)
                    ->update(['status' => 'signed']);
            }

            $this->history($termination, $oldStatus, $termination->fresh()->status, $signer, $signerSide === 'owner' ? 'owner' : 'admin', 'Ký biên bản chấm dứt hợp đồng cuối cùng.');

            return $termination->fresh($this->requestRelations());
        });
    }

    public function manualResolveBooking(PartnerTerminationRequest $termination, Booking $booking, User $admin, ?string $note = null): PartnerTerminationBookingAction
    {
        return DB::transaction(function () use ($termination, $booking, $admin, $note): PartnerTerminationBookingAction {
            if (! $this->hasTerminationBookingActionsTable()) {
                throw ValidationException::withMessages([
                    'booking' => 'Chua co bang luu thao tac booking cham dut. Vui long chay migration truoc khi xu ly booking tuong lai.',
                ]);
            }

            if ((string) $booking->venue_cluster_id !== (string) $termination->venue_cluster_id) {
                throw ValidationException::withMessages(['booking_id' => 'Booking không thuộc cụm sân của yêu cầu chấm dứt.']);
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
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $payload = [
            'value' => (string) max(0, $graceDays),
            'description' => 'Số ngày chủ sân còn được xem hồ sơ sau khi biên bản chấm dứt cuối đã ký.',
            'updated_at' => now(),
            'created_at' => now(),
        ];

        if (Schema::hasColumn('system_settings', 'value_type')) {
            $payload['value_type'] = 'integer';
        }

        if (Schema::hasColumn('system_settings', 'type')) {
            $payload['type'] = 'integer';
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'partner_termination_view_grace_days'],
            $payload
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

            $accessDeadline = $termination->owner_access_view_until ?: $termination->transition_end_at;
            if ($termination->status === self::STATUS_TERMINATING && $accessDeadline && $accessDeadline->isPast()) {
                $this->revokeOwnerScope($termination);
                return $termination->fresh();
            }

            if ($termination->status === self::STATUS_WAITING_FINAL_SIGNATURE) {
                return $termination;
            }

            if (! $this->allFutureBookingsResolved($termination)) {
                $this->setStatusIfChanged($termination, self::STATUS_FUTURE_BOOKINGS, null, 'system', 'Đang xử lý booking tương lai.');
                return $termination->fresh();
            }

            if (! $this->readyForFinalDocument($termination)) {
                $this->setStatusIfChanged($termination, self::STATUS_WAITING_SETTLEMENT, null, 'system', 'Đã xử lý booking, đang chờ quyết toán/rút tiền cuối.');
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

            $transferredAmount = $this->transferRemainingOwnerBalanceToUserWallet($termination);

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
            $this->fillTermination($termination, [
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
                    'status' => 'locked',
                    'status_reason' => 'Hợp đồng đối tác đã chấm dứt hoàn tất theo hồ sơ ' . $termination->termination_code,
                    'locked_at' => now(),
                ]);

            VenueCourt::query()
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->update(['status' => 'inactive']);

            $note = 'Thu hồi quyền chủ sân sau thời gian cấu hình.';
            if ($transferredAmount > 0) {
                $note .= ' Số dư chưa rút đã được chuyển sang số dư người dùng của cùng tài khoản.';
            }
            $this->history($termination, $oldStatus, self::STATUS_TERMINATED, null, 'system', $note);
        });
    }

    private function transferRemainingOwnerBalanceToUserWallet(PartnerTerminationRequest $termination): float
    {
        $ownerWallet = OwnerWallet::query()
            ->where('owner_id', $termination->owner_id)
            ->where('venue_cluster_id', $termination->venue_cluster_id)
            ->lockForUpdate()
            ->first();

        if (! $ownerWallet) {
            return 0;
        }

        $ownerTransactionCode = 'OWT-'.substr(hash('sha256', "partner-termination:{$termination->id}:{$ownerWallet->id}"), 0, 32);
        $existingOwnerLedger = OwnerWalletLedger::query()
            ->where('transaction_code', $ownerTransactionCode)
            ->first();

        if ($existingOwnerLedger) {
            return (float) $existingOwnerLedger->amount;
        }

        $amount = round((float) $ownerWallet->available_balance, 2);
        if ($amount <= 0) {
            return 0;
        }

        $userWallet = UserWallet::query()->firstOrCreate(
            ['user_id' => $termination->owner_id],
            ['balance' => 0, 'locked_balance' => 0, 'status' => 'active'],
        );
        $userWallet = UserWallet::query()->whereKey($userWallet->id)->lockForUpdate()->firstOrFail();

        $ownerBalanceBefore = (float) $ownerWallet->available_balance;
        $ownerWallet->available_balance = 0;
        $ownerWallet->total_withdrawn = (float) $ownerWallet->total_withdrawn + $amount;
        $ownerWallet->save();

        $ownerLedger = OwnerWalletLedger::query()->create([
            'owner_wallet_id' => $ownerWallet->id,
            'owner_id' => $termination->owner_id,
            'venue_cluster_id' => $termination->venue_cluster_id,
            'type' => 'debit',
            'direction' => 'debit',
            'amount' => $amount,
            'balance_before' => $ownerBalanceBefore,
            'balance_after' => 0,
            'status' => 'completed',
            'reference_code' => $termination->termination_code,
            'reference_type' => 'partner_termination_balance_transfer',
            'reference_id' => $termination->id,
            'transaction_code' => $ownerTransactionCode,
            'description' => 'Chuyển số dư chủ sân chưa rút sang số dư người dùng khi hết thời gian xem hồ sơ chấm dứt.',
            'note' => 'Tài khoản vẫn giữ nguyên tiền dưới vai trò người dùng SportGo.',
            'metadata' => [
                'source' => 'partner_termination_grace_expired',
                'termination_code' => $termination->termination_code,
                'destination_user_wallet_id' => $userWallet->id,
            ],
        ]);

        $userBalanceBefore = (float) $userWallet->balance;
        $userBalanceAfter = $userBalanceBefore + $amount;
        $userWallet->balance = $userBalanceAfter;
        $userWallet->save();

        $userLedgerId = DB::table('user_wallet_ledgers')->insertGetId([
            'user_wallet_id' => $userWallet->id,
            'transaction_code' => 'UWT-'.substr(hash('sha256', "partner-termination:{$termination->id}:{$userWallet->id}"), 0, 32),
            'type' => 'adjustment',
            'direction' => 'credit',
            'amount' => $amount,
            'balance_before' => $userBalanceBefore,
            'balance_after' => $userBalanceAfter,
            'reference_type' => 'partner_termination_balance_transfer',
            'reference_id' => $termination->id,
            'status' => 'completed',
            'note' => 'Nhận số dư chủ sân chưa rút sau khi hoàn tất chấm dứt hợp tác.',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->fillTermination($termination, [
            'owner_balance_total' => round((float) $ownerWallet->pending_withdrawal_balance, 2),
            'withdrawable_amount' => 0,
            'metadata' => array_merge($termination->metadata ?: [], [
                'remaining_balance_transfer' => [
                    'amount' => $amount,
                    'owner_wallet_ledger_id' => $ownerLedger->id,
                    'user_wallet_id' => $userWallet->id,
                    'user_wallet_ledger_id' => $userLedgerId,
                    'transferred_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();

        return $amount;
    }

    public function refreshAmounts(PartnerTerminationRequest $termination): PartnerTerminationRequest
    {
        $termination->loadMissing('venueCluster');
        if (! $termination->venueCluster) {
            return $termination;
        }

        $summary = $this->financialSummary($termination->venueCluster);
        $this->fillTermination($termination, [
            'future_booking_count' => $summary['future_booking_count'],
            'owner_balance_total' => $summary['owner_balance_total'],
            'future_online_booking_liability' => $summary['future_online_booking_liability'],
            'pending_refund_liability' => $summary['pending_refund_liability'],
            'pending_withdrawal_amount' => $summary['pending_withdrawal_amount'],
            'withdrawable_amount' => $summary['withdrawable_amount'],
            'platform_fee_outstanding_amount' => $summary['platform_fee_outstanding_amount'],
            'platform_fee_accrued_amount' => $summary['platform_fee_accrued_amount'],
            'platform_fee_prepaid_refund_amount' => $summary['platform_fee_prepaid_refund_amount'],
            'platform_fee_hold_amount' => $summary['platform_fee_hold_amount'],
            'platform_fee_settlement_status' => $summary['platform_fee_settlement_status'],
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

        $outstandingPlatformFee = (float) VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereRaw('amount_paid < amount_due')
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) AS remaining')
            ->value('remaining');
        $platformFeeHold = (float) PlatformFeeWalletHold::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->sum('amount');
        $activeTermination = PartnerTerminationRequest::query()
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
            ->latest()
            ->first();
        $cutoff = $activeTermination?->platform_fee_cutoff_at ?: now();
        $prepaidRefund = $this->platformFeePrepaidRefund($cluster->id, Carbon::parse($cutoff));
        $netPlatformFeeDebt = max($outstandingPlatformFee - $prepaidRefund, 0);
        $netPlatformFeeRefund = max($prepaidRefund - $outstandingPlatformFee, 0);

        $withdrawable = max(
            $ownerBalanceTotal - $futureLiability - $pendingRefundLiability - $pendingWithdrawalAmount - $netPlatformFeeDebt,
            0,
        ) + $netPlatformFeeRefund;

        return [
            'owner_balance_total' => round($ownerBalanceTotal, 2),
            'future_online_booking_liability' => round($futureLiability, 2),
            'pending_refund_liability' => round($pendingRefundLiability, 2),
            'pending_withdrawal_amount' => round($pendingWithdrawalAmount, 2),
            'platform_fee_outstanding_amount' => round($outstandingPlatformFee, 2),
            'platform_fee_accrued_amount' => 0.0,
            'platform_fee_prepaid_refund_amount' => round($prepaidRefund, 2),
            'platform_fee_hold_amount' => round($platformFeeHold, 2),
            'platform_fee_settlement_status' => $netPlatformFeeDebt <= 0.01 ? 'settled' : 'pending',
            'withdrawable_amount' => round($withdrawable, 2),
            'future_booking_count' => count($futureBookings),
            'future_bookings' => $futureBookings,
        ];
    }

    public function requestRelations(): array
    {
        $relations = [
            'owner:id,full_name,username,email,phone',
            'venueCluster:id,owner_id,name,status,address,status_reason,locked_at',
            'contract.application.user',
            'documents.generatedDocument.signatures.signer',
            'documents.generatedDocument.signingRequests',
            'generatedDocuments.signatures.signer',
            'generatedDocuments.signingRequests',
            'statusHistories.changedBy:id,full_name,username,email',
        ];

        if ($this->hasTerminationBookingActionsTable()) {
            $relations[] = 'bookingActions.booking.customer';
            $relations[] = 'bookingActions.booking.payments';
            $relations[] = 'bookingActions.processedBy:id,full_name,username,email';
        }

        return $relations;
    }

    private function platformFeeCutoffAt(PartnerTerminationRequest $termination, array $futureBookings): Carbon
    {
        if ($termination->future_booking_policy === self::POLICY_CANCEL_ALL) {
            return now();
        }

        $lastBooking = collect($futureBookings)
            ->filter(fn (array $booking): bool => ! empty($booking['booking_date']))
            ->sortByDesc(fn (array $booking): string => $booking['booking_date'].' '.($booking['end_time'] ?: '23:59:59'))
            ->first();

        if ($lastBooking) {
            return Carbon::parse($lastBooking['booking_date'].' '.($lastBooking['end_time'] ?: '23:59:59'));
        }

        return $termination->requested_effective_date
            ? Carbon::parse($termination->requested_effective_date)->endOfDay()
            : now();
    }

    private function platformFeePrepaidRefund(string|int $clusterId, Carbon $cutoff): float
    {
        return round((float) PlatformFeeServicePeriod::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', '!=', 'voided')
            ->whereDate('period_end', '>', $cutoff->toDateString())
            ->whereHas('ledger', fn ($query) => $query->where('status', 'paid'))
            ->get()
            ->sum(function (PlatformFeeServicePeriod $period) use ($cutoff): float {
                $start = Carbon::parse($period->period_start)->startOfDay();
                $end = Carbon::parse($period->period_end)->startOfDay();
                $refundStart = $cutoff->copy()->addDay()->startOfDay()->max($start);
                if ($refundStart->gt($end)) {
                    return 0.0;
                }

                $totalDays = max($start->diffInDays($end) + 1, 1);
                $refundDays = $refundStart->diffInDays($end) + 1;

                return (float) $period->net_amount * $refundDays / $totalDays;
            }), 2);
    }

    private function fillTermination(PartnerTerminationRequest $termination, array $attributes): PartnerTerminationRequest
    {
        return $termination->forceFill($this->existingTerminationAttributes($attributes));
    }

    private function withFinancialSummary(PartnerTerminationRequest $termination): PartnerTerminationRequest
    {
        $termination->loadMissing('venueCluster');
        if ($termination->venueCluster) {
            $termination->setAttribute('financial_summary', $this->financialSummary($termination->venueCluster));
        }

        return $termination;
    }

    private function existingTerminationAttributes(array $attributes): array
    {
        $columns = $this->terminationColumns();

        return array_filter(
            $attributes,
            fn (string $column): bool => isset($columns[$column]),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function terminationColumns(): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('partner_termination_requests')
                ? array_flip(Schema::getColumnListing('partner_termination_requests'))
                : [];
        }

        return $columns;
    }

    private function hasTerminationBookingActionsTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasTable('partner_termination_booking_actions');
        }

        return $exists;
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
            return 'Cụm sân không ở trạng thái đang hoạt động.';
        }
        if (! $contract) {
            return 'Không có hợp đồng đang hiệu lực cho cụm sân.';
        }
        if ($activeRequest && $activeRequest->status !== self::STATUS_DRAFT_PREVIEW) {
            return 'Đã có yêu cầu chấm dứt đang xử lý cho cụm sân này.';
        }

        return null;
    }

    private function futureBookingPolicies(): array
    {
        return [
            ['value' => self::POLICY_CANCEL_ALL, 'label' => 'Hủy toàn bộ booking tương lai và hoàn về số dư/khoản hoàn tiền user'],
            ['value' => self::POLICY_SERVE_UNTIL_LAST, 'label' => 'Không hủy, tiếp tục phục vụ đến booking cuối cùng'],
            ['value' => self::POLICY_MANUAL, 'label' => 'Xử lý thủ công từng booking'],
        ];
    }

    private function generateOwnerRequestDocument(
        PartnerTerminationRequest $termination,
        User $owner,
        string $status = 'generated',
        array $previewData = [],
        ?array $previewSummary = null
    ): GeneratedDocument
    {
        $termination->loadMissing(['contract.application.user', 'venueCluster']);
        $financial = $previewSummary ?: [
            'future_booking_count' => $termination->future_booking_count,
            'future_online_booking_liability' => $termination->future_online_booking_liability,
            'owner_balance_total' => $termination->owner_balance_total,
            'pending_refund_liability' => $termination->pending_refund_liability,
            'pending_withdrawal_amount' => $termination->pending_withdrawal_amount,
            'withdrawable_amount' => $termination->withdrawable_amount,
            'future_bookings' => $termination->future_booking_summary ?: [],
        ];
        $futureBookings = $financial['future_bookings'] ?? [];
        $policy = $previewData['future_booking_policy'] ?? $termination->future_booking_policy;
        $effectiveDate = $previewData['requested_effective_date'] ?? $termination->requested_effective_date;
        $effectiveDate = $effectiveDate ? Carbon::parse($effectiveDate)->format('d/m/Y') : null;
        $futureBookingCount = (int) ($financial['future_booking_count'] ?? 0);
        $pendingRefund = (float) ($financial['pending_refund_liability'] ?? 0);
        $pendingWithdrawal = (float) ($financial['pending_withdrawal_amount'] ?? 0);
        $openComplaints = Schema::hasTable('complaints')
            ? DB::table('complaints')
                ->where('venue_cluster_id', $termination->venue_cluster_id)
                ->whereNotIn('status', ['resolved', 'closed', 'rejected', 'cancelled'])
                ->count()
            : 0;
        $ownerName = $this->ownerSignerName($termination);
        $ownerPhone = $termination->contract?->application?->applicant_phone ?: $owner->phone;

        return $this->documents->generateDocument('termination_request', $termination, [
            'termination_code' => $termination->termination_code,
            'requested_at' => $this->timestamp($termination->requested_at),
            'requested_by' => $ownerName,
            'owner_full_name' => $ownerName,
            'owner_signer_full_name' => $ownerName,
            'owner_phone' => $ownerPhone,
            'owner_email' => $termination->contract?->application?->applicant_email ?: $owner->email,
            'contract_code' => $termination->contract?->contract_code,
            'contract_signed_at' => $termination->contract?->sportgo_signed_at?->format('d/m/Y')
                ?: $termination->contract?->owner_signed_at?->format('d/m/Y'),
            'venue_name' => $termination->venueCluster?->name,
            'venue_cluster_code' => $termination->venueCluster?->venue_code ?: $termination->venueCluster?->code,
            'venue_address' => $termination->venueCluster?->address,
            'venue_status_label' => 'Đang hoạt động; hệ thống khóa nhận booking mới sau khi đơn được ký gửi',
            'termination_type' => 'Chủ sân đề nghị chấm dứt hợp đồng',
            'termination_reason' => trim($termination->reason . ($termination->detail_reason ? "\nChi tiết: " . $termination->detail_reason : '')),
            'reason' => $termination->reason,
            'detail_reason' => $previewData['detail_reason'] ?? $termination->detail_reason,
            'requested_effective_date' => $effectiveDate,
            'requested_stop_booking_at' => 'Ngay sau khi chủ sân ký gửi đơn',
            'termination_coordinator' => $ownerName . ($ownerPhone ? ' - ' . $ownerPhone : ''),
            'future_booking_policy' => $this->policyLabel($policy),
            'future_booking_count' => (string) $futureBookingCount,
            'booking_status_summary' => $futureBookingCount > 0
                ? $futureBookingCount . ' booking tương lai; phương án: ' . $this->policyLabel($policy)
                : 'Không có booking tương lai cần xử lý',
            'refund_status_summary' => $pendingRefund > 0
                ? 'Đang xử lý ' . $this->money($pendingRefund)
                : 'Không có khoản hoàn tiền đang chờ',
            'withdrawal_status_summary' => $pendingWithdrawal > 0
                ? 'Đang xử lý ' . $this->money($pendingWithdrawal)
                : 'Không có yêu cầu rút tiền đang chờ',
            'complaint_status_summary' => $openComplaints > 0
                ? $openComplaints . ' khiếu nại đang mở'
                : 'Không có khiếu nại đang mở',
            'future_online_booking_liability' => $this->money($financial['future_online_booking_liability'] ?? 0),
            'owner_balance_total' => $this->money($financial['owner_balance_total'] ?? 0),
            'pending_refund_liability' => $this->money($pendingRefund),
            'pending_withdrawal_amount' => $this->money($pendingWithdrawal),
            'withdrawable_amount' => $this->money($financial['withdrawable_amount'] ?? 0),
            'temporary_hold_amount' => $this->money($financial['future_online_booking_liability'] ?? 0),
            'future_bookings_summary' => collect($futureBookings)->map(fn ($booking) => ($booking['booking_code'] ?? '-') . ' - ' . ($booking['booking_date'] ?? '-') . ' - ' . $this->money($booking['paid_online_amount'] ?? 0))->implode("\n"),
            'attachments' => collect($previewData['attachments'] ?? $termination->owner_attachments ?: [])->map(fn ($item) => is_array($item) ? ($item['name'] ?? json_encode($item)) : (string) $item)->implode(', '),
            'owner_bank_account_snapshot' => $this->bankSnapshot($termination),
            'owner_signed_at' => null,
        ], $owner, [
            'status' => $status,
            'partner_application_id' => $termination->partner_application_id,
            'partner_contract_id' => $termination->partner_contract_id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $termination->owner_id,
            'venue_cluster_id' => $termination->venue_cluster_id,
            'title' => 'Đơn đề nghị chấm dứt hợp đồng hợp tác đối tác SportGo ' . ($termination->venueCluster?->name ?? ''),
        ]);
    }

    private function generateFinalDocumentIfReady(PartnerTerminationRequest $termination, ?User $actor, bool $adminOverride = false): GeneratedDocument
    {
        $termination = $termination->fresh(['contract.application.user', 'venueCluster']);
        $existing = PartnerTerminationDocument::query()
            ->with('generatedDocument')
            ->where('partner_termination_request_id', $termination->id)
            ->whereIn('document_type', ['settlement_minutes', 'final_termination_file'])
            ->latest()
            ->first();

        if ($existing?->generatedDocument) {
            if ($termination->status !== self::STATUS_WAITING_FINAL_SIGNATURE) {
                $this->setStatusIfChanged($termination, self::STATUS_WAITING_FINAL_SIGNATURE, $actor, $actor ? 'admin' : 'system', 'Văn bản chấm dứt cuối đã sẵn sàng ký.');
            }

            return $existing->generatedDocument;
        }

        if (! $this->readyForFinalDocument($termination, $adminOverride)) {
            throw ValidationException::withMessages([
                'termination' => 'Chưa đủ điều kiện sinh biên bản chấm dứt cuối.',
            ]);
        }

        $document = $this->documents->generateDocument('settlement_minutes', $termination, $this->finalDocumentRenderData($termination), $actor, [
            'status' => 'pending_sportgo_signature',
            'partner_application_id' => $termination->partner_application_id,
            'partner_contract_id' => $termination->partner_contract_id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $termination->owner_id,
            'venue_cluster_id' => $termination->venue_cluster_id,
            'title' => 'Biên bản chấm dứt hợp đồng hợp tác đối tác SportGo ' . ($termination->termination_code ?? ''),
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
        $this->fillTermination($termination, [
            'status' => self::STATUS_WAITING_FINAL_SIGNATURE,
            'final_document_generated_at' => now(),
            'final_document_ready_at' => now(),
        ])->save();
        $this->history($termination, $oldStatus, self::STATUS_WAITING_FINAL_SIGNATURE, $actor, $actor ? 'admin' : 'system', 'Sinh biên bản chấm dứt hợp đồng cuối.');

        return $document;
    }

    private function finalDocumentRenderData(PartnerTerminationRequest $termination): array
    {
        $relations = ['contract.application.user', 'venueCluster'];
        if ($this->hasTerminationBookingActionsTable()) {
            $relations[] = 'bookingActions.booking';
        }

        $termination->loadMissing($relations);
        $summary = $this->financialSummary($termination->venueCluster);
        $ownerName = $this->ownerSignerName($termination);
        $openComplaintCount = Complaint::query()
            ->where('venue_cluster_id', $termination->venue_cluster_id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->count();
        $bookingResult = $this->hasTerminationBookingActionsTable()
            ? $termination->bookingActions
                ->map(fn (PartnerTerminationBookingAction $action) => ($action->booking?->booking_code ?? '-') . ': ' . $this->bookingActionLabel($action->action) . ' / ' . $action->status)
                ->implode("\n")
            : '';

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
            'booking_status_summary' => $summary['future_booking_count'] > 0
                ? $summary['future_booking_count'] . ' booking còn hiệu lực đang được xử lý.'
                : 'Không có booking còn hiệu lực.',
            'completed_booking_reconciliation_summary' => 'Không còn booking chờ xử lý trong điều kiện sinh biên bản cuối.',
            'refund_status_summary' => $summary['pending_refund_liability'] > 0
                ? 'Đang xử lý refund: ' . $this->money($summary['pending_refund_liability'])
                : 'Không có yêu cầu hoàn/hủy đang xử lý.',
            'complaint_status_summary' => $openComplaintCount > 0
                ? $openComplaintCount . ' khiếu nại đang mở trên hệ thống.'
                : 'Không có khiếu nại đang mở.',
            'withdrawal_status_summary' => $summary['pending_withdrawal_amount'] > 0
                ? 'Đang xử lý withdrawal: ' . $this->money($summary['pending_withdrawal_amount'])
                : 'Không có yêu cầu rút tiền đang chờ.',
            'booking_resolution_result' => $bookingResult ?: 'Không còn booking tương lai bắt buộc xử lý.',
            'refund_result' => 'Refund đang treo: ' . $this->money($summary['pending_refund_liability']),
            'withdrawal_result' => 'Withdrawal đang treo: ' . $this->money($summary['pending_withdrawal_amount']),
            'owner_wallet_available_amount' => $this->money($summary['owner_balance_total']),
            'future_online_booking_liability' => $this->money($summary['future_online_booking_liability']),
            'pending_refund_liability' => $this->money($summary['pending_refund_liability']),
            'pending_withdrawal_amount' => $this->money($summary['pending_withdrawal_amount']),
            'final_payable_to_owner' => $this->money($summary['withdrawable_amount']),
            'final_receivable_from_owner' => $this->money(0),
            'platform_fee_remaining_refund_amount' => $this->money(0),
            'unpaid_platform_fee_amount' => $this->money(0),
            'adjustment_amount' => $this->money(0),
            'voucher_adjustment_amount' => $this->money(0),
            'compensation_amount' => $this->money(0),
            'settlement_amount_in_words' => (float) $summary['withdrawable_amount'] > 0
                ? 'Theo số tiền bằng số: ' . $this->money($summary['withdrawable_amount'])
                : 'Không đồng',
            'settlement_obligor' => (float) $summary['withdrawable_amount'] > 0 ? 'SportGo' : 'Không phát sinh',
            'settlement_receiver' => (float) $summary['withdrawable_amount'] > 0
                ? ($termination->contract?->application?->business_name ?: $ownerName)
                : 'Không phát sinh',
            'settlement_payment_method' => (float) $summary['withdrawable_amount'] > 0 ? 'Chuyển khoản' : 'Không phát sinh',
            'settlement_deadline' => $this->timestamp(now()->addDays($this->gracePeriodDays())),
            'document_copy_count' => '02',
            'each_party_copy_count' => '01',
            'settlement_items' => $bookingResult,
            'effective_termination_date' => $this->timestamp(now()),
            'owner_access_revocation_date' => $this->timestamp(now()->addDays($this->gracePeriodDays())),
            'grace_period_days' => (string) $this->gracePeriodDays(),
            'bank_account' => $this->bankSnapshot($termination),
        ];
    }

    private function syncFutureBookingActions(PartnerTerminationRequest $termination, array $futureBookings): void
    {
        if (! $this->hasTerminationBookingActionsTable()) {
            return;
        }

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
        $actions = $termination && $this->hasTerminationBookingActionsTable()
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

        if (! $this->hasTerminationBookingActionsTable()) {
            return false;
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

    private function readyForFinalDocument(PartnerTerminationRequest $termination, bool $allowManualBalanceResolution = false): bool
    {
        $termination->loadMissing('venueCluster');
        $summary = $this->financialSummary($termination->venueCluster);

        if (! $this->allFutureBookingsResolved($termination)) {
            return false;
        }

        if ((float) $summary['pending_refund_liability'] > 0 || (float) $summary['pending_withdrawal_amount'] > 0) {
            return false;
        }

        if ((float) $summary['platform_fee_outstanding_amount']
            > (float) $summary['platform_fee_prepaid_refund_amount'] + 0.01) {
            return false;
        }

        return (float) $summary['owner_balance_total'] <= 0.01 || $allowManualBalanceResolution || $termination->manual_debt_resolved_at !== null;
    }

    private function latestOwnerRequestGeneratedDocument(PartnerTerminationRequest $termination): GeneratedDocument
    {
        $document = $termination->documents()
            ->with('generatedDocument')
            ->where('document_type', 'owner_termination_request')
            ->latest()
            ->first()?->generatedDocument;

        if (! $document) {
            throw ValidationException::withMessages(['document' => 'Không tìm thấy đơn yêu cầu chấm dứt để ký.']);
        }

        return $document;
    }

    private function cancellationGeneratedDocument(PartnerTerminationRequest $termination, int $documentId): GeneratedDocument
    {
        $document = GeneratedDocument::query()
            ->whereKey($documentId)
            ->where('partner_termination_request_id', $termination->id)
            ->where('document_type', 'termination_cancellation_request')
            ->whereNull('locked_at')
            ->latest()
            ->first();

        if (! $document) {
            throw ValidationException::withMessages([
                'document' => 'Không tìm thấy bản xem trước hủy yêu cầu hoặc văn bản đã được ký.',
            ]);
        }

        return $document;
    }

    private function latestUnilateralNoticeGeneratedDocument(PartnerTerminationRequest $termination): GeneratedDocument
    {
        $document = $termination->documents()
            ->with('generatedDocument.signatures')
            ->whereIn('document_type', ['unilateral_notice', 'unilateral_termination_notice'])
            ->latest()
            ->first()?->generatedDocument;

        if (! $document) {
            throw ValidationException::withMessages(['document' => 'Không tìm thấy công văn chấm dứt để ký.']);
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
            throw ValidationException::withMessages(['document' => 'Chưa có biên bản chấm dứt cuối để ký.']);
        }

        return $document;
    }

    private function lockClusterForTermination(PartnerTerminationRequest $termination, User $owner): void
    {
        VenueCluster::query()
            ->whereKey($termination->venue_cluster_id)
            ->update([
                'status' => 'locked',
                'status_reason' => 'Chủ sân đã ký gửi yêu cầu chấm dứt hợp đồng đối tác. Cụm sân tạm ngưng nhận booking mới.',
                'locked_at' => now(),
                'locked_by' => $owner->id,
            ]);
    }

    private function unlockClusterAfterOwnerCancel(PartnerTerminationRequest $termination): void
    {
        VenueCluster::query()
            ->whereKey($termination->venue_cluster_id)
            ->where('status', 'locked')
            ->where('status_reason', 'like', '%chấm dứt%')
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
            abort(403, 'Bạn không có quyền thao tác hồ sơ chấm dứt này.');
        }
    }

    private function assertUnilateralNoticeDraft(PartnerTerminationRequest $termination): void
    {
        if ($termination->termination_type !== 'unilateral_by_sportgo') {
            throw ValidationException::withMessages(['termination' => 'Hồ sơ này không phải công văn chấm dứt từ SportGo.']);
        }

        if ($termination->status !== self::STATUS_DRAFT_PREVIEW) {
            throw ValidationException::withMessages(['status' => 'Công văn không còn ở trạng thái chờ SportGo ký.']);
        }
    }

    private function assertFinalSigner(PartnerTerminationRequest $termination, User $signer, string $signerSide): void
    {
        if (! in_array($signerSide, ['owner', 'sportgo'], true)) {
            abort(403);
        }

        if ($termination->status !== self::STATUS_WAITING_FINAL_SIGNATURE) {
            throw ValidationException::withMessages([
                'status' => 'Hồ sơ chưa ở trạng thái chờ ký biên bản chấm dứt cuối.',
            ]);
        }

        if ($signerSide === 'owner') {
            $this->assertOwner($termination, $signer);
            if (! $this->finalDocumentHasSignature($termination, 'sportgo')) {
                throw ValidationException::withMessages([
                    'status' => 'SportGo cần ký biên bản chấm dứt cuối trước chủ sân.',
                ]);
            }
        }

        if ($signerSide === 'sportgo' && $this->finalDocumentHasSignature($termination, 'sportgo')) {
            throw ValidationException::withMessages([
                'status' => 'SportGo đã ký biên bản chấm dứt cuối.',
            ]);
        }
    }

    private function finalDocumentHasSignature(PartnerTerminationRequest $termination, string $signerSide): bool
    {
        return PartnerTerminationDocument::query()
            ->where('partner_termination_request_id', $termination->id)
            ->whereIn('document_type', ['settlement_minutes', 'final_termination_file'])
            ->whereHas('generatedDocument.signatures', fn ($query) => $query
                ->where('signer_side', $signerSide)
                ->where('status', 'signed'))
            ->exists();
    }

    private function assertCanOwnerCancel(PartnerTerminationRequest $termination): void
    {
        if ($termination->admin_locked_owner_cancel) {
            throw ValidationException::withMessages(['status' => 'Admin đã khóa quyền hủy yêu cầu này.']);
        }

        if ($termination->final_document_ready_at || $termination->final_document_admin_signed_at || $termination->final_document_owner_signed_at) {
            throw ValidationException::withMessages(['status' => 'Yêu cầu đã vào bước ký biên bản chấm dứt cuối, không thể hủy.']);
        }

        if (! in_array($termination->status, [self::STATUS_IN_PROGRESS, self::STATUS_FUTURE_BOOKINGS, self::STATUS_WAITING_SETTLEMENT], true)) {
            throw ValidationException::withMessages(['status' => 'Trạng thái hiện tại không cho phép chủ sân hủy yêu cầu.']);
        }

        if ($this->hasTerminationBookingActionsTable()) {
            $termination->loadMissing('bookingActions');
            $hasIrreversible = $termination->bookingActions
                ->contains(fn (PartnerTerminationBookingAction $action): bool => $action->action === self::POLICY_CANCEL_ALL && $action->status === 'resolved');
            if ($hasIrreversible) {
                throw ValidationException::withMessages(['booking' => 'Đã có booking bị hủy/hoàn tiền, cần admin xử lý thủ công nếu muốn dừng quy trình.']);
            }
        }
    }

    private function setStatusIfChanged(PartnerTerminationRequest $termination, string $status, ?User $actor, string $actorType, ?string $reason): void
    {
        if ($termination->status === $status) {
            return;
        }

        $old = $termination->status;
        $this->fillTermination($termination, ['status' => $status])->save();
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
                    'title' => 'Có yêu cầu chấm dứt hợp đồng đối tác',
                    'body' => ($termination->venueCluster?->name ?: $termination->termination_code) . ' vừa gửi yêu cầu chấm dứt hợp đồng.',
                    'reference_type' => 'partner_termination_request',
                    'reference_id' => $termination->id,
                    'data' => [
                        'termination_code' => $termination->termination_code,
                        'venue_cluster_id' => $termination->venue_cluster_id,
                    ],
                ]);
            });
    }

    private function notifyOwnerAboutUnilateralNotice(PartnerTerminationRequest $termination): void
    {
        $termination->loadMissing(['owner', 'contract.application.user', 'venueCluster']);
        $owner = $termination->owner ?: $termination->contract?->application?->user;
        if (! $owner) {
            return;
        }

        Notification::query()->create([
            'user_id' => $owner->id,
            'type' => 'partner_unilateral_termination_notice',
            'title' => 'Cần xác nhận công văn chấm dứt hợp tác',
            'body' => 'SportGo đã gửi công văn cho ' . ($termination->venueCluster?->name ?: 'cụm sân') . '. Vui lòng đọc file và xác nhận đã nhận.',
            'reference_type' => 'partner_termination_request',
            'reference_id' => $termination->id,
            'data' => [
                'termination_code' => $termination->termination_code,
                'action_url' => '/owner/termination-requests/' . $termination->id,
                'action_label' => 'Xem và xác nhận công văn',
            ],
        ]);

        $this->mail->queue($owner, new PartnerUnilateralTerminationMail([
            'owner_name' => $this->ownerSignerName($termination),
            'contract_code' => $termination->contract?->contract_code,
            'issued_at' => $this->timestamp(now()),
            'reason' => $termination->reason,
            'revocation_date' => $this->timestamp($termination->requested_effective_date),
            'refund_amount' => $this->money($termination->withdrawable_amount),
            'status_url' => url('/owner/termination-requests/' . $termination->id),
        ]));
    }

    private function notifyOwnerAboutUnilateralResolution(PartnerTerminationRequest $termination, string $title, string $body): void
    {
        $ownerId = $termination->owner_id;
        if (! $ownerId) {
            return;
        }

        Notification::query()->create([
            'user_id' => $ownerId,
            'type' => 'partner_unilateral_termination_resolution',
            'title' => $title,
            'body' => $body,
            'reference_type' => 'partner_termination_request',
            'reference_id' => $termination->id,
            'data' => [
                'termination_code' => $termination->termination_code,
                'action_url' => '/owner/termination-requests/' . $termination->id,
                'action_label' => 'Xem hồ sơ',
            ],
        ]);
    }

    private function notifyAdminsAboutUnilateral(PartnerTerminationRequest $termination, string $title, string $body): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['super_admin', 'admin', 'system_staff', 'partner_manager']))
            ->pluck('id')
            ->each(function (string|int $adminId) use ($termination, $title, $body): void {
                Notification::query()->create([
                    'user_id' => $adminId,
                    'type' => 'partner_unilateral_termination_action',
                    'title' => $title,
                    'body' => $body,
                    'reference_type' => 'partner_termination_request',
                    'reference_id' => $termination->id,
                    'data' => [
                        'termination_code' => $termination->termination_code,
                        'partner_application_id' => $termination->partner_application_id,
                        'action_url' => '/admin/partners/' . $termination->partner_application_id . '?tab=settlement',
                        'action_label' => 'Mở hồ sơ chấm dứt',
                    ],
                ]);
            });
    }

    private function generateUnilateralNoticeDocument(
        PartnerTerminationRequest $termination,
        PartnerContract $contract,
        User $admin,
        array $summary,
        array $noticeData = []
    ): GeneratedDocument {
        $contract->loadMissing(['application.user', 'venueCluster']);
        $application = $contract->application;
        $cluster = $contract->venueCluster;
        $ownerName = $this->ownerSignerName($termination->loadMissing(['owner', 'contract.application.user']));
        $businessName = $application?->business_name ?: $ownerName;
        $effectiveDate = $this->timestamp($termination->requested_effective_date);
        $detailReason = $noticeData['detail_reason'] ?? $termination->detail_reason;
        $futureBookingPolicy = $noticeData['future_booking_policy'] ?? $termination->future_booking_policy ?? self::POLICY_MANUAL;
        $futureBookingSummary = collect($summary['future_bookings'] ?? [])
            ->map(fn ($booking) => ($booking['booking_code'] ?? '-') . ' - ' . ($booking['booking_date'] ?? '-') . ' - ' . $this->money($booking['paid_online_amount'] ?? 0))
            ->implode("\n");

        return $this->documents->generateDocument('unilateral_termination_notice', $termination, [
            'document_number' => 'CV-' . $termination->termination_code,
            'notice_code' => 'CV-' . $termination->termination_code,
            'issue_date' => $this->timestamp(now()),
            'issued_at' => $this->timestamp(now()),
            'issuer_side' => 'SportGo',
            'receiver_name' => $businessName,
            'venue_owner_name' => $ownerName,
            'business_name' => $application?->business_name,
            'representative_name' => $ownerName,
            'owner_full_name' => $ownerName,
            'owner_signer_full_name' => $ownerName,
            'party_b_name' => $businessName,
            'party_b_id' => $application?->tax_code ?: $application?->representative_identity_number,
            'party_b_address' => $application?->business_address ?: $application?->venue_address,
            'owner_phone' => $application?->applicant_phone ?: $application?->venue_phone ?: $application?->user?->phone,
            'owner_email' => $application?->applicant_email ?: $application?->venue_email ?: $application?->user?->email,
            'contract_code' => $contract->contract_code,
            'venue_name' => $cluster?->name ?: $application?->venue_name,
            'venue_code' => $cluster?->slug ?: $termination->termination_code,
            'venue_address' => $cluster?->address ?: $application?->venue_address,
            'legal_basis_text' => 'Theo điều khoản chấm dứt hợp tác trong hợp đồng đã ký và dữ liệu vận hành được lưu trên SportGo.',
            'termination_reason' => trim($termination->reason . ($detailReason ? "\nChi tiết: " . $detailReason : '')),
            'detail_reason' => $detailReason,
            'future_booking_policy' => $futureBookingPolicy,
            'effective_date' => $effectiveDate,
            'effective_termination_date' => $effectiveDate,
            'transition_end_at' => $effectiveDate,
            'required_actions' => 'Xác nhận đã nhận công văn; ' . $this->policyLabel($futureBookingPolicy) . '; hoàn tất refund, withdrawal và đối soát trước khi lập biên bản cuối.',
            'settlement_deadline' => $effectiveDate,
            'contract_signed_at' => $this->timestamp($contract->owner_signed_at ?: $contract->sportgo_signed_at ?: $contract->effective_from),
            'issuer_representative_name' => $admin->full_name ?: $admin->username,
            'future_booking_count' => (string) ($summary['future_booking_count'] ?? 0),
            'future_bookings_summary' => $futureBookingSummary ?: 'Không có booking tương lai tại thời điểm tạo công văn.',
            'owner_balance_total' => $this->money($summary['owner_balance_total'] ?? 0),
            'future_online_booking_liability' => $this->money($summary['future_online_booking_liability'] ?? 0),
            'pending_refund_liability' => $this->money($summary['pending_refund_liability'] ?? 0),
            'pending_withdrawal_amount' => $this->money($summary['pending_withdrawal_amount'] ?? 0),
            'withdrawable_amount' => $this->money($summary['withdrawable_amount'] ?? 0),
        ], $admin, [
            'status' => 'pending_sportgo_signature',
            'partner_application_id' => $contract->partner_application_id,
            'partner_contract_id' => $contract->id,
            'partner_termination_request_id' => $termination->id,
            'owner_id' => $contract->owner_id,
            'venue_cluster_id' => $contract->venue_cluster_id,
            'title' => 'Công văn chấm dứt hợp tác ' . ($cluster?->name ?: $termination->termination_code),
        ]);
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
            ?: 'Chủ sân';
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
            self::POLICY_CANCEL_ALL => 'Hủy và hoàn về số dư/khoản hoàn tiền user',
            self::POLICY_SERVE_UNTIL_LAST => 'Giữ lại phục vụ đến booking cuối',
            self::POLICY_MANUAL => 'Admin/chủ sân xử lý thủ công',
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
