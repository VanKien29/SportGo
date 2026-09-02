<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Partner\VenueChangeRequestApprovedMail;
use App\Mail\Partner\VenueChangeRequestRejectedMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\GeneratedDocument;
use App\Models\Notification;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueCourtApprovalRequest;
use App\Models\VenueAccessRestriction;
use App\Models\VenueLocationChangeRequest;
use App\Models\VenuePlatformFeeLedger;
use App\Models\VenueUnlockRequest;
use App\Services\Partner\PartnerDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Mailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VenueClusterController extends Controller
{
    public function __construct(private readonly PartnerDocumentService $documents)
    {
    }

    // ─────────────────────────────────────────────────────────────────
    // Danh sách cụm sân toàn hệ thống (filter theo status)
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('options')) {
            $optionsQuery = VenueCluster::query()
                ->select(['id', 'name', 'status', 'owner_id', 'created_at'])
                ->with('owner:id,full_name,username,email')
                ->withCount(['venueCourts as court_count'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = '%'.$request->input('search').'%';
                    $query->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('name', 'like', $search)
                            ->orWhere('address', 'like', $search)
                            ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                                ->where('full_name', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('email', 'like', $search));
                    });
                })
                ->when($request->filled('owner_id'), fn ($query) => $query->where('owner_id', $request->input('owner_id')))
                ->latest();

            $toOption = fn (VenueCluster $cluster): array => [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'status' => $cluster->status,
                    'court_count' => (int) $cluster->court_count,
                    'owner_id' => $cluster->owner_id,
                    'owner' => $cluster->owner ? [
                        'id' => $cluster->owner->id,
                        'full_name' => $cluster->owner->full_name,
                        'username' => $cluster->owner->username,
                        'email' => $cluster->owner->email,
                    ] : null,
                ];

            if ($request->boolean('paginate')) {
                $perPage = max(10, min($request->integer('per_page', 20), 50));
                $paginator = $optionsQuery->paginate($perPage);

                return response()->json([
                    'data' => $paginator->getCollection()->map($toOption)->values(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ]);
            }

            $query = $optionsQuery->get()->map($toOption);

            return response()->json(['data' => $query]);
        }

        $query = VenueCluster::query()
            ->with([
                'owner:id,full_name,username,email,phone',
                'venueCourts:id,venue_cluster_id,court_type_id,name,status',
                'venueCourts.courtType:id,name',
                'latestPlatformFeeLedger',
                'media',
            ])
            ->withCount([
                'approvalRequests as pending_approval_count' => fn($q) => $q->where('status', 'pending'),
                'locationChangeRequests as pending_location_count' => fn($q) => $q->where('status', 'pending'),
                'informationChangeRequests as pending_information_count' => fn($q) => $q->where('status', 'pending'),
                'unlockRequests as pending_unlock_count' => fn($q) => $q->where('status', 'pending'),
            ]);

        // Filter trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Tìm kiếm tên / địa chỉ
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('address', 'like', $search);
            });
        }

        // Filter theo owner
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        $clusters = $query->latest()->get()->map(fn (VenueCluster $c) => $this->listPayload($c));

        return response()->json(['data' => $clusters]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Chi tiết cụm sân (kèm đủ các tab-data)
    // ─────────────────────────────────────────────────────────────────
    public function show(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::with([
            'owner:id,full_name,username,email,phone',
            'venueCourts.courtType:id,name',
            'bookingConfig',
            'lockedBy:id,full_name,username',
            'media',
        ])->findOrFail($id);

        // Bookings của cụm sân (20 gần nhất)
        $bookings = Booking::query()
            ->where('venue_cluster_id', $id)
            ->with(['customer:id,full_name,username,phone', 'venueCourt:id,name', 'payments', 'ownerApprovedBy:id,full_name,username'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'id'           => $b->id,
                'booking_code' => $b->booking_code,
                'customer'     => $b->customer ? [
                    'id'        => $b->customer->id,
                    'full_name' => $b->customer->full_name,
                    'phone'     => $b->customer->phone,
                ] : null,
                'venue_court'  => $b->venueCourt ? ['id' => $b->venueCourt->id, 'name' => $b->venueCourt->name] : null,
                'booking_date' => $b->booking_date,
                'start_time'   => $b->start_time,
                'end_time'     => $b->end_time,
                'total_price'  => $b->total_price,
                'payment_option' => $b->payment_option,
                'effective_payment_option' => $b->effective_payment_option ?: $b->payment_option,
                'required_payment_amount' => $b->required_payment_amount,
                'paid_amount' => (float) $b->payments->where('status', 'paid')->sum('amount'),
                'status'       => $b->status,
                'approval_deadline_at' => $b->approval_deadline_at,
                'payment_deadline_at' => $b->payment_deadline_at,
                'owner_approved_at' => $b->owner_approved_at,
                'owner_approved_by' => $b->ownerApprovedBy ? [
                    'id' => $b->ownerApprovedBy->id,
                    'full_name' => $b->ownerApprovedBy->full_name,
                    'username' => $b->ownerApprovedBy->username,
                ] : null,
                'payment_fallback_at' => $b->payment_fallback_at,
                'payment_fallback_reason' => $b->payment_fallback_reason,
                'created_at'   => $b->created_at,
            ]);

        // Phí nền tảng
        $fees = VenuePlatformFeeLedger::query()
            ->where('venue_cluster_id', $id)
            ->with('tier:id,name')
            ->latest('period_start')
            ->limit(20)
            ->get()
            ->map(fn ($f) => [
                'id'                   => $f->id,
                'tier'                 => $f->tier ? ['id' => $f->tier->id, 'name' => $f->tier->name] : null,
                'court_count'          => $f->court_count,
                'period_start'         => $f->period_start,
                'period_end'           => $f->period_end,
                'due_date'             => $f->due_date,
                'amount_due'           => $f->amount_due,
                'amount_paid'          => $f->amount_paid,
                'status'               => $f->status,
                'payment_proof_status' => $f->payment_proof_status,
                'paid_at'              => $f->paid_at,
            ]);

        // Lịch sử khóa (audit_logs) - chỉ lấy các hành động khóa / mở khóa
        $lockHistory = [];
        if (Schema::hasTable('audit_logs')) {
            $lockHistory = AuditLog::query()
                ->where('entity_type', 'venue_clusters')
                ->where('entity_id', $id)
                ->whereIn('action', [
                    'venue_cluster.locked',
                    'venue_cluster.unlocked',
                ])
                ->with('actor:id,full_name,username')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn ($log) => [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'actor'      => $log->actor ? ['id' => $log->actor->id, 'full_name' => $log->actor->full_name] : null,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'reason'     => $log->reason ?? ($log->new_values['status_reason'] ?? null),
                    'created_at' => $log->created_at,
                ]);
        }

        // Yêu cầu quy mô
        $approvalRequests = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $id)
            ->with([
                'courtType:id,name',
                'requestedBy:id,full_name,username',
                'reviewedBy:id,full_name,username',
                'generatedDocument.signatures',
            ])
            ->latest()
            ->get()
            ->map(fn ($r) => $this->approvalPayload($r));

        // Yêu cầu thay đổi vị trí
        $locationChangeRequests = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $id)
            ->with([
                'requestedBy:id,full_name,username',
                'reviewedBy:id,full_name,username',
                'generatedDocument.signatures',
            ])
            ->latest()
            ->get()
            ->map(fn ($r) => $this->locationChangePayload($r));

        // Yêu cầu mở khóa
        $unlockRequests = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $id)
            ->with([
                'requestedBy:id,full_name,username',
                'reviewedBy:id,full_name,username',
            ])
            ->latest()
            ->get()
            ->map(fn ($r) => $this->unlockRequestPayload($r));

        // Yêu cầu thay đổi thông tin cụm sân
        $informationChangeRequests = \App\Models\VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $id)
            ->with([
                'requestedBy:id,full_name,username',
                'reviewedBy:id,full_name,username',
            ])
            ->latest()
            ->get()
            ->map(fn ($r) => $this->informationChangePayload($r));

        return response()->json([
            'data' => [
                'cluster'                  => $this->detailPayload($cluster),
                'bookings'                 => $bookings,
                'fees'                     => $fees,
                'lock_history'             => $lockHistory,
                'approval_requests'        => $approvalRequests,
                'location_change_requests' => $locationChangeRequests,
                'unlock_requests'          => $unlockRequests,
                'information_change_requests' => $informationChangeRequests,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Khóa cụm sân
    // ─────────────────────────────────────────────────────────────────
    public function lock(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
            'locked_until'  => ['nullable', 'date', 'after:now'],
        ], [
            'status_reason.required' => 'Vui lòng nhập lý do khóa.',
            'locked_until.after'     => 'Thời hạn khóa phải lớn hơn thời điểm hiện tại.',
        ]);

        /** @var \App\Models\User $actor */
        $actor   = $request->user();
        $cluster = VenueCluster::findOrFail($id);

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đã ở trạng thái bị khóa.'], 422);
        }

        $oldValues = $this->lockSnapshot($cluster);

        $cluster->forceFill([
            'status'        => 'locked',
            'status_reason' => $data['status_reason'],
            'locked_at'     => now(),
            'locked_until'  => $data['locked_until'] ?? null,
            'locked_by'     => $actor->id,
        ])->save();

        // Keep a durable source for a manual lock so reconciliation can
        // distinguish it from a policy restriction.
        VenueAccessRestriction::query()->updateOrCreate(
            [
                'venue_cluster_id' => $cluster->id,
                'restriction_type' => 'admin_manual',
                'status' => 'active',
            ],
            [
                'access_mode' => 'blocked',
                'reason' => $data['status_reason'],
                'starts_at' => $cluster->locked_at ?? now(),
                'ends_at' => $cluster->locked_until,
                'created_by' => $actor->id,
            ],
        );

        \App\Models\Report::resolvePendingReportsForTarget($cluster, 'venue_locked', $actor, $data['status_reason']);

        $this->audit($request, $actor, 'venue_cluster.locked', $cluster, $oldValues, $this->lockSnapshot($cluster));

        return response()->json([
            'message' => 'Khóa cụm sân thành công.',
            'cluster' => $this->detailPayload($cluster->fresh(['owner', 'venueCourts.courtType', 'lockedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Mở khóa cụm sân
    // ─────────────────────────────────────────────────────────────────
    public function unlock(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor   = $request->user();
        $cluster = VenueCluster::findOrFail($id);

        $activePolicyRestriction = VenueAccessRestriction::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->where('restriction_type', '!=', 'admin_manual')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByRaw("CASE WHEN access_mode = 'blocked' THEN 0 ELSE 1 END")
            ->first();

        if ($activePolicyRestriction) {
            return response()->json([
                'message' => 'Cum san van con han che theo chinh sach. Vui long xu ly nguyen nhan truoc khi mo khoa.',
            ], 422);
        }

        if ($cluster->status !== 'locked') {
            return response()->json(['message' => 'Cụm sân không ở trạng thái bị khóa.'], 422);
        }

        $oldValues = $this->lockSnapshot($cluster);

        VenueAccessRestriction::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('restriction_type', 'admin_manual')
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);

        $cluster->forceFill([
            'status'        => 'active',
            'status_reason' => null,
            'locked_at'     => null,
            'locked_until'  => null,
            'locked_by'     => null,
        ])->save();

        $this->audit($request, $actor, 'venue_cluster.unlocked', $cluster, $oldValues, $this->lockSnapshot($cluster));

        return response()->json([
            'message' => 'Mở khóa cụm sân thành công.',
            'cluster' => $this->detailPayload($cluster->fresh(['owner', 'venueCourts.courtType', 'lockedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Cập nhật tiện ích cụm sân
    // ─────────────────────────────────────────────────────────────────
    public function updateAmenities(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amenities' => ['required', 'array'],
            'amenities.*' => ['required', 'string', 'max:255'],
        ], [
            'amenities.required' => 'Danh sách tiện ích không được để trống.',
            'amenities.array' => 'Tiện ích phải là một danh sách.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();
        $cluster = VenueCluster::findOrFail($id);

        $oldAmenities = $cluster->amenities ?? [];
        $amenityNames = $data['amenities'];

        // Find matching active amenities
        $activeAmenities = \App\Models\Amenity::whereIn('name', $amenityNames)
            ->where('status', 'active')
            ->get();

        $syncData = [];
        foreach ($activeAmenities as $amenity) {
            $syncData[$amenity->id] = [
                'is_visible' => true,
                'description' => null,
            ];
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($cluster, $data, $syncData) {
            $cluster->forceFill([
                'amenities' => $data['amenities'],
            ])->save();

            $cluster->amenityCatalog()->sync($syncData);
        });

        $this->audit(
            $request,
            $actor,
            'venue_cluster.amenities_updated',
            $cluster,
            ['amenities' => $oldAmenities],
            ['amenities' => $data['amenities']]
        );

        return response()->json([
            'message' => 'Cập nhật tiện ích cụm sân thành công.',
            'cluster' => $this->detailPayload($cluster->fresh(['owner', 'venueCourts.courtType', 'lockedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Duyệt yêu cầu mở rộng / thu hẹp quy mô
    // ─────────────────────────────────────────────────────────────────
    public function approveRequest(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        // Tạo sân con mới khi duyệt yêu cầu mở rộng
        $cluster = VenueCluster::with(['owner', 'venueCourts.courtType'])->findOrFail($clusterId);
        $approvalRequest->loadMissing(['courtType', 'requestedBy']);
        $approvalRequest->forceFill([
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => null,
        ]);
        $appendix = $this->generateVenueChangeAppendixDocument(
            'venue_scale_appendix',
            $cluster,
            $approvalRequest,
            $actor,
            $this->scaleAppendixRenderData($cluster, $approvalRequest)
        );

        $approvalRequest->forceFill([
            'status'                  => 'approved_pending_appendix',
            'status_reason'           => null,
        ])->save();

        $this->audit($request, $actor, 'venue_court_approval.appendix_created', $approvalRequest, ['status' => 'pending'], ['status' => 'approved_pending_appendix', 'appendix_document_id' => $appendix->id]);
        $approvalRequest->loadMissing(['venueCluster.owner', 'courtType']);
        $this->sendVenueChangeMail($approvalRequest->venueCluster, new VenueChangeRequestApprovedMail([
            'request_type' => 'Mở rộng quy mô sân',
            'cluster_name' => $approvalRequest->venueCluster?->name,
            'summary' => $this->scaleChangeSummary($approvalRequest),
            'reviewed_at' => optional($approvalRequest->reviewed_at)->format('H:i d/m/Y'),
        ]), $approvalRequest->id);

        return response()->json([
            'message' => 'Duyệt yêu cầu thành công. Hệ thống đã tạo phụ lục hợp đồng và đang chờ SportGo ký trước.',
            'request' => $this->approvalPayload($approvalRequest->fresh(['courtType', 'requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
            'appendix_document' => $this->generatedDocumentPayload($appendix),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Từ chối yêu cầu mở rộng / thu hẹp quy mô
    // ─────────────────────────────────────────────────────────────────
    public function rejectRequest(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status_reason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $approvalRequest->forceFill([
            'status'        => 'rejected',
            'reviewed_by'   => $actor->id,
            'reviewed_at'   => now(),
            'status_reason' => $data['status_reason'],
        ])->save();

        $this->audit($request, $actor, 'venue_court_approval.rejected', $approvalRequest, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $data['status_reason']]);
        $approvalRequest->loadMissing(['venueCluster.owner', 'courtType']);
        $this->sendVenueChangeMail($approvalRequest->venueCluster, new VenueChangeRequestRejectedMail([
            'request_type' => 'Mở rộng quy mô sân',
            'cluster_name' => $approvalRequest->venueCluster?->name,
            'summary' => $this->scaleChangeSummary($approvalRequest),
            'reason' => $data['status_reason'],
            'reviewed_at' => optional($approvalRequest->reviewed_at)->format('H:i d/m/Y'),
        ]), $approvalRequest->id);

        return response()->json([
            'message' => 'Đã từ chối yêu cầu.',
            'request' => $this->approvalPayload($approvalRequest->fresh(['courtType', 'requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Duyệt yêu cầu thay đổi vị trí
    // ─────────────────────────────────────────────────────────────────
    public function requestSupplementForScale(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status_reason.required' => 'Vui lòng nhập nội dung giấy tờ/thông tin cần bổ sung.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $approvalRequest->forceFill([
            'status' => 'need_supplement',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => $data['status_reason'],
        ])->save();

        $this->audit($request, $actor, 'venue_court_approval.need_supplement', $approvalRequest, ['status' => 'pending'], ['status' => 'need_supplement', 'reason' => $data['status_reason']]);
        $approvalRequest->loadMissing(['venueCluster.owner', 'courtType']);
        $this->sendVenueChangeMail($approvalRequest->venueCluster, new VenueChangeRequestRejectedMail([
            'request_type' => 'Mở rộng quy mô sân',
            'cluster_name' => $approvalRequest->venueCluster?->name,
            'summary' => $this->scaleChangeSummary($approvalRequest),
            'reason' => $data['status_reason'],
            'reviewed_at' => optional($approvalRequest->reviewed_at)->format('H:i d/m/Y'),
            'status_label' => 'Cần bổ sung hồ sơ',
            'message' => 'SportGo cần bạn bổ sung thêm giấy tờ hoặc thông tin cho yêu cầu này. Vui lòng kiểm tra lý do và gửi lại yêu cầu mới sau khi đã chuẩn bị đủ hồ sơ.',
        ]), $approvalRequest->id);

        return response()->json([
            'message' => 'Đã yêu cầu chủ sân bổ sung hồ sơ.',
            'request' => $this->approvalPayload($approvalRequest->fresh(['courtType', 'requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
        ]);
    }

    public function approveLocationChange(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($locationRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $cluster = VenueCluster::with(['owner', 'venueCourts.courtType'])->findOrFail($clusterId);
        $locationRequest->loadMissing(['requestedBy']);

        $oldValues = [
            'address'   => $cluster->address,
            'province'  => $cluster->province,
            'ward'      => $cluster->ward,
            'latitude'  => $cluster->latitude,
            'longitude' => $cluster->longitude,
            'map_url'   => $cluster->map_url,
        ];

        // Cập nhật vị trí cluster từ snapshot
        $locationRequest->forceFill([
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => null,
        ]);
        $appendix = $this->generateVenueChangeAppendixDocument(
            'venue_location_appendix',
            $cluster,
            $locationRequest,
            $actor,
            $this->locationAppendixRenderData($cluster, $locationRequest)
        );

        $locationRequest->forceFill([
            'status'      => 'approved_pending_appendix',
            'status_reason' => null,
        ])->save();

        $this->audit(
            $request, $actor,
            'venue_cluster.location_change_appendix_created',
            $cluster,
            $oldValues,
            [
                'status' => 'approved_pending_appendix',
                'appendix_document_id' => $appendix->id,
            ]
        );
        $cluster->loadMissing('owner');
        $this->sendVenueChangeMail($cluster, new VenueChangeRequestApprovedMail([
            'request_type' => 'Thay đổi vị trí sân',
            'cluster_name' => $cluster->name,
            'summary' => trim($locationRequest->new_address . ', ' . $locationRequest->new_ward . ', ' . $locationRequest->new_province, ', '),
            'reviewed_at' => optional($locationRequest->reviewed_at)->format('H:i d/m/Y'),
        ]), $locationRequest->id);

        return response()->json([
            'message' => 'Duyệt yêu cầu thành công. Hệ thống đã tạo phụ lục thay đổi vị trí và đang chờ SportGo ký trước.',
            'request' => $this->locationChangePayload($locationRequest->fresh(['requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
            'appendix_document' => $this->generatedDocumentPayload($appendix),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Từ chối yêu cầu thay đổi vị trí
    // ─────────────────────────────────────────────────────────────────
    public function rejectLocationChange(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status_reason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($locationRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $locationRequest->forceFill([
            'status'        => 'rejected',
            'reviewed_by'   => $actor->id,
            'reviewed_at'   => now(),
            'status_reason' => $data['status_reason'],
        ])->save();

        $this->audit(
            $request, $actor,
            'venue_cluster.location_change_rejected',
            VenueCluster::findOrFail($clusterId),
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $data['status_reason']]
        );
        $cluster = VenueCluster::with('owner')->findOrFail($clusterId);
        $this->sendVenueChangeMail($cluster, new VenueChangeRequestRejectedMail([
            'request_type' => 'Thay đổi vị trí sân',
            'cluster_name' => $cluster->name,
            'summary' => trim($locationRequest->new_address . ', ' . $locationRequest->new_ward . ', ' . $locationRequest->new_province, ', '),
            'reason' => $data['status_reason'],
            'reviewed_at' => optional($locationRequest->reviewed_at)->format('H:i d/m/Y'),
        ]), $locationRequest->id);

        return response()->json([
            'message' => 'Đã từ chối yêu cầu.',
            'request' => $this->locationChangePayload($locationRequest->fresh(['requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Duyệt yêu cầu mở khóa cụm sân
    // ─────────────────────────────────────────────────────────────────
    public function requestSupplementForLocationChange(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status_reason.required' => 'Vui lòng nhập nội dung giấy tờ/thông tin cần bổ sung.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($locationRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $locationRequest->forceFill([
            'status' => 'need_supplement',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => $data['status_reason'],
        ])->save();

        $cluster = VenueCluster::with('owner')->findOrFail($clusterId);
        $this->audit($request, $actor, 'venue_cluster.location_change_need_supplement', $cluster, ['status' => 'pending'], ['status' => 'need_supplement', 'reason' => $data['status_reason']]);
        $this->sendVenueChangeMail($cluster, new VenueChangeRequestRejectedMail([
            'request_type' => 'Thay đổi vị trí sân',
            'cluster_name' => $cluster->name,
            'summary' => trim($locationRequest->new_address . ', ' . $locationRequest->new_ward . ', ' . $locationRequest->new_province, ', '),
            'reason' => $data['status_reason'],
            'reviewed_at' => optional($locationRequest->reviewed_at)->format('H:i d/m/Y'),
            'status_label' => 'Cần bổ sung hồ sơ',
            'message' => 'SportGo cần bạn bổ sung thêm giấy tờ hoặc thông tin cho yêu cầu thay đổi vị trí. Vui lòng kiểm tra lý do và gửi lại yêu cầu mới sau khi đã chuẩn bị đủ hồ sơ.',
        ]), $locationRequest->id);

        return response()->json([
            'message' => 'Đã yêu cầu chủ sân bổ sung hồ sơ.',
            'request' => $this->locationChangePayload($locationRequest->fresh(['requestedBy', 'reviewedBy', 'generatedDocument.signatures'])),
        ]);
    }

    public function approveUnlockRequest(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();
        $cluster = VenueCluster::findOrFail($clusterId);

        $unlockRequest = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($unlockRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $oldClusterValues = $this->lockSnapshot($cluster);

        $unlockRequest->forceFill([
            'status'      => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'admin_note'  => $request->input('admin_note'),
        ])->save();

        // Mở khóa cụm sân
        $cluster->forceFill([
            'status'        => 'active',
            'status_reason' => null,
            'locked_at'     => null,
            'locked_until'  => null,
            'locked_by'     => null,
        ])->save();

        $this->audit($request, $actor, 'venue_cluster.unlock_request_approved', $cluster, $oldClusterValues, $this->lockSnapshot($cluster));

        // Gửi notification cho owner
        $this->notifyOwner($cluster, 'Yêu cầu mở khóa đã được duyệt', 'Cụm sân "' . $cluster->name . '" đã được mở khóa. Bạn có thể tiếp tục vận hành bình thường.', $unlockRequest);

        return response()->json([
            'message' => 'Đã duyệt yêu cầu mở khóa. Cụm sân đã được kích hoạt lại.',
            'data'    => $this->unlockRequestPayload($unlockRequest->fresh(['requestedBy', 'reviewedBy'])),
            'cluster' => $this->detailPayload($cluster->fresh(['owner', 'venueCourts.courtType', 'lockedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Từ chối yêu cầu mở khóa cụm sân
    // ─────────────────────────────────────────────────────────────────
    public function rejectUnlockRequest(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ], [
            'admin_note.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();
        $cluster = VenueCluster::findOrFail($clusterId);

        $unlockRequest = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($unlockRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $unlockRequest->forceFill([
            'status'      => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'admin_note'  => $data['admin_note'],
        ])->save();

        $this->audit($request, $actor, 'venue_cluster.unlock_request_rejected', $cluster, ['status' => 'pending'], ['status' => 'rejected', 'admin_note' => $data['admin_note']]);

        // Gửi notification cho owner
        $this->notifyOwner($cluster, 'Yêu cầu mở khóa bị từ chối', 'Yêu cầu mở khóa cụm sân "' . $cluster->name . '" đã bị từ chối. Lý do: ' . $data['admin_note'], $unlockRequest);

        return response()->json([
            'message' => 'Đã từ chối yêu cầu mở khóa.',
            'data'    => $this->unlockRequestPayload($unlockRequest->fresh(['requestedBy', 'reviewedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function sendVenueChangeMail(?VenueCluster $cluster, Mailable $mail, ?string $referenceId = null): void
    {
        if (! $cluster) {
            return;
        }

        $cluster->loadMissing('owner');
        $owner = $cluster->owner;

        if (! $owner?->email) {
            Log::warning('Venue change request mail skipped: owner has no email.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
            ]);
            return;
        }

        try {
            Mail::to($owner->email)->send($mail);
        } catch (\Throwable $exception) {
            Log::error('Venue change request mail failed.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
                'owner_id' => $owner->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function listPayload(VenueCluster $c): array
    {
        $courts      = $c->venueCourts ?? collect();
        $courtTypes  = $courts->map(fn ($ct) => $ct->courtType?->name)->filter()->unique()->values();

        return [
            'id'           => $c->id,
            'name'         => $c->name,
            'slug'         => $c->slug,
            'province'     => $c->province,
            'ward'         => $c->ward,
            'address'      => $c->address,
            'status'       => $c->status,
            'status_reason' => $c->status_reason,
            'locked_at'    => $c->locked_at,
            'locked_until' => $c->locked_until,
            'rating_avg'   => $c->rating_avg,
            'rating_count' => $c->rating_count,
            'court_count'  => $courts->count(),
            'court_types'  => $courtTypes,
            'fee_status'   => $c->latestPlatformFeeLedger?->status ?? 'no_fee',
            'has_pending_requests' => (($c->pending_approval_count ?? 0) + 
                                       ($c->pending_location_count ?? 0) + 
                                       ($c->pending_information_count ?? 0) + 
                                       ($c->pending_unlock_count ?? 0)) > 0,
            'image_path'   => $c->media->first()?->file_path ?? null,
            'owner'        => $c->owner ? [
                'id'        => $c->owner->id,
                'full_name' => $c->owner->full_name,
                'username'  => $c->owner->username,
                'email'     => $c->owner->email,
            ] : null,
            'created_at'   => $c->created_at,
        ];
    }

    private function detailPayload(VenueCluster $c): array
    {
        $courts = $c->venueCourts ?? collect();
        $contract = $this->activePartnerContractForCluster($c);
        $application = $contract?->application ?: $this->partnerApplicationForCluster($c);

        return array_merge($this->listPayload($c), [
            'description'   => $c->description,
            'phone_contact' => $c->phone_contact,
            'map_url'       => $c->map_url,
            'latitude'      => $c->latitude,
            'longitude'     => $c->longitude,
            'amenities'     => $c->amenities,
            'locked_by'     => $c->lockedBy ? [
                'id'        => $c->lockedBy->id,
                'full_name' => $c->lockedBy->full_name,
            ] : null,
            'courts'        => $courts->map(fn ($court) => [
                'id'         => $court->id,
                'name'       => $court->name,
                'status'     => $court->status,
                'court_type' => $court->courtType ? ['id' => $court->courtType->id, 'name' => $court->courtType->name] : null,
                'sort_order' => $court->sort_order,
            ])->values(),
            'images'        => $c->media->map(fn ($m) => [
                'id'        => $m->id,
                'file_path' => $m->file_path,
                'file_name' => $m->file_name,
            ])->values(),
            'partner_application' => $application ? [
                'id' => $application->id,
                'business_name' => $application->business_name,
                'representative_name' => $application->representative_name ?: $application->applicant_full_name,
                'status' => $application->status,
                'reviewed_at' => $application->reviewed_at,
            ] : null,
            'active_contract' => $contract ? [
                'id' => $contract->id,
                'contract_code' => $contract->contract_code,
                'status' => $contract->status,
                'owner_signed_at' => $contract->owner_signed_at,
                'sportgo_signed_at' => $contract->sportgo_signed_at,
                'effective_from' => $contract->effective_from,
                'effective_to' => $contract->effective_to,
            ] : null,
        ]);
    }

    private function approvalPayload(VenueCourtApprovalRequest $r): array
    {
        $appendix = $this->appendixDocumentForRequest($r, 'venue_scale_appendix');

        return [
            'id'                      => $r->id,
            'name'                    => $r->name,
            'change_type'             => $r->change_type ?: 'add',
            'requested_courts'         => $r->requested_courts ?: [],
            'removed_court_ids'        => $r->removed_court_ids ?: [],
            'scale_summary'           => $this->scaleChangeSummary($r),
            'status'                  => $r->status,
            'status_reason'           => $r->status_reason,
            'evidence_image'          => $r->evidence_image,
            'evidence_image_url'      => $r->evidence_image ? asset('storage/' . $r->evidence_image) : null,
            'supplementary_documents' => $r->supplementary_documents ?: [],
            'signature_image'         => $r->signature_image,
            'signature_image_url'     => $r->signature_image ? asset('storage/' . $r->signature_image) : null,
            'signature_hash'          => $r->signature_hash,
            'signed_at'               => $r->signed_at,
            'generated_document'       => $this->documentPayload($r->generatedDocument),
            'appendix_document'        => $this->documentPayload($appendix),
            'court_type'              => $r->courtType ? ['id' => $r->courtType->id, 'name' => $r->courtType->name] : null,
            'requested_by'            => $r->requestedBy ? ['id' => $r->requestedBy->id, 'full_name' => $r->requestedBy->full_name] : null,
            'reviewed_by'             => $r->reviewedBy ? ['id' => $r->reviewedBy->id, 'full_name' => $r->reviewedBy->full_name] : null,
            'approved_venue_court_id' => $r->approved_venue_court_id,
            'reviewed_at'             => $r->reviewed_at,
            'created_at'              => $r->created_at,
        ];
    }

    private function locationChangePayload(VenueLocationChangeRequest $r): array
    {
        $appendix = $this->appendixDocumentForRequest($r, 'venue_location_appendix');

        return [
            'id'            => $r->id,
            'status'        => $r->status,
            'note'          => $r->note,
            'status_reason' => $r->status_reason,
            'new_address'   => $r->new_address,
            'new_province'  => $r->new_province,
            'new_province_code' => $r->new_province_code,
            'new_ward'      => $r->new_ward,
            'new_ward_code' => $r->new_ward_code,
            'new_latitude'  => $r->new_latitude,
            'new_longitude' => $r->new_longitude,
            'new_map_url'   => $r->new_map_url,
            'supplementary_documents' => $r->supplementary_documents ?: [],
            'signature_image' => $r->signature_image,
            'signature_image_url' => $r->signature_image ? asset('storage/' . $r->signature_image) : null,
            'signature_hash' => $r->signature_hash,
            'signed_at' => $r->signed_at,
            'generated_document' => $this->documentPayload($r->generatedDocument),
            'appendix_document' => $this->documentPayload($appendix),
            'requested_by'  => $r->requestedBy ? ['id' => $r->requestedBy->id, 'full_name' => $r->requestedBy->full_name] : null,
            'reviewed_by'   => $r->reviewedBy ? ['id' => $r->reviewedBy->id, 'full_name' => $r->reviewedBy->full_name] : null,
            'reviewed_at'   => $r->reviewed_at,
            'created_at'    => $r->created_at,
        ];
    }

    private function documentPayload($document): ?array
    {
        if (! $document) {
            return null;
        }

        return [
            'id' => $document->id,
            'document_code' => $document->document_code,
            'document_type' => $document->document_type,
            'document_version' => $document->document_version,
            'partner_application_id' => $document->partner_application_id,
            'partner_contract_id' => $document->partner_contract_id,
            'owner_id' => $document->owner_id,
            'venue_cluster_id' => $document->venue_cluster_id,
            'title' => $document->title,
            'status' => $document->status,
            'file_hash' => $document->file_hash,
            'generated_at' => $document->generated_at,
            'download_url' => url('/api/files/documents/' . $document->id . '/download'),
        ];
    }

    private function generatedDocumentPayload($document): ?array
    {
        return $this->documentPayload($document);
    }

    private function appendixDocumentForRequest($requestModel, string $documentType): ?GeneratedDocument
    {
        return GeneratedDocument::query()
            ->with('signatures')
            ->where('document_type', $documentType)
            ->where('reference_type', $requestModel::class)
            ->where('reference_id', (string) $requestModel->getKey())
            ->latest('document_version')
            ->latest('generated_at')
            ->first();
    }

    private function generateVenueChangeAppendixDocument(
        string $documentType,
        VenueCluster $cluster,
        $requestModel,
        $actor,
        array $renderData
    ): GeneratedDocument {
        $contract = $this->activePartnerContractForCluster($cluster);
        $application = $contract?->application ?: $this->partnerApplicationForCluster($cluster);

        $titlePrefix = $documentType === 'venue_scale_appendix'
            ? 'Phụ lục hợp đồng thay đổi quy mô sân '
            : 'Phụ lục hợp đồng thay đổi vị trí cụm sân ';

        return $this->documents->generateDocument($documentType, $requestModel, $renderData, $actor, [
            'reference_type' => $requestModel::class,
            'reference_id' => (string) $requestModel->getKey(),
            'owner_id' => $cluster->owner_id,
            'venue_cluster_id' => $cluster->id,
            'partner_application_id' => $application?->id,
            'partner_contract_id' => $contract?->id,
            'entity_type' => VenueCluster::class,
            'entity_id' => $cluster->id,
            'status' => 'pending_sportgo_signature',
            'title' => $titlePrefix . $cluster->name,
        ]);
    }

    private function scaleAppendixRenderData(VenueCluster $cluster, VenueCourtApprovalRequest $approvalRequest): array
    {
        $application = $this->partnerApplicationForCluster($cluster);
        $contract = $this->activePartnerContractForCluster($cluster);
        $owner = $cluster->owner;
        $ownerName = $application?->representative_name
            ?: $application?->applicant_full_name
            ?: $owner?->full_name
            ?: $owner?->username;
        $scaleSummary = $this->scaleCourtSummaries($approvalRequest);
        $changeType = $approvalRequest->change_type ?: 'add';

        $currentCourtCount = $cluster->venueCourts?->count() ?? VenueCourt::query()
            ->where('venue_cluster_id', $cluster->id)
            ->count();
        $currentCourts = ($cluster->venueCourts ?: collect())->loadMissing('courtType:id,name');
        $currentCourtTypesSummary = $currentCourts
            ->map(fn (VenueCourt $court): ?string => $court->courtType?->name)
            ->filter()
            ->unique()
            ->implode('; ');
        $requestDocument = $approvalRequest->generatedDocument()->first();
        $appendixSequence = $this->nextAppendixSequence($cluster, $contract, $approvalRequest);

        return [
            'appendix_sequence' => $appendixSequence,
            'appendix_number' => $this->toRomanNumeral($appendixSequence),
            'owner_full_name' => $ownerName,
            'owner_signer_name' => $ownerName,
            'owner_signer_full_name' => $ownerName,
            'business_name' => $application?->business_name ?: $ownerName,
            'identity_number' => $application?->representative_identity_number,
            'tax_code' => $application?->tax_code,
            'business_license_number' => $application?->business_license_number ?: $application?->business_code,
            'owner_phone' => $application?->applicant_phone ?: $application?->venue_phone ?: $owner?->phone,
            'owner_email' => $application?->applicant_email ?: $application?->venue_email ?: $owner?->email,
            'owner_address' => $application?->business_address ?: $application?->applicant_address ?: $cluster->address,
            'venue_name' => $cluster->name,
            'cluster_name' => $cluster->name,
            'venue_address' => $cluster->address,
            'venue_province' => $cluster->province,
            'venue_ward' => $cluster->ward,
            'venue_latitude' => $cluster->latitude,
            'venue_longitude' => $cluster->longitude,
            'venue_map_url' => $cluster->map_url,
            'request_id' => $approvalRequest->id,
            'request_code' => $requestDocument?->document_code ?: $approvalRequest->id,
            'contract_code' => $contract?->contract_code,
            'contract_signed_at' => $this->formatDateTime($this->contractTimelineAt($contract)),
            'current_court_count' => $currentCourtCount,
            'current_court_types_summary' => $currentCourtTypesSummary,
            'current_courts_summary' => $currentCourts
                ->map(fn (VenueCourt $court): string => trim($court->name . ' - ' . ($court->courtType?->name ?? '')))
                ->filter()
                ->implode('; '),
            'change_action' => $this->scaleChangeAction($changeType),
            'change_court_count' => $this->scaleChangeCount($changeType, $scaleSummary),
            'new_court_count' => $scaleSummary['new_count'],
            'removed_court_count' => $scaleSummary['removed_count'],
            'requested_court_type_name' => $approvalRequest->courtType?->name,
            'requested_court_names' => $scaleSummary['new'] ?: $approvalRequest->name,
            'new_courts_summary' => $scaleSummary['new'],
            'removed_courts_summary' => $scaleSummary['removed'],
            'requested_court_rows' => $scaleSummary['requested_rows'],
            'removed_court_rows' => $scaleSummary['removed_rows'],
            'court_change_rows' => $scaleSummary['rows'],
            'reason' => $approvalRequest->status_reason,
            'booking_impact' => 'Rà soát booking còn hiệu lực, cấu hình giá và lịch vận hành trước khi cập nhật quy mô.',
            'submitted_at' => $this->formatDateTime($approvalRequest->created_at),
            'reviewed_at' => $this->formatDateTime($approvalRequest->reviewed_at),
            'expected_effective_date' => now()->format('d/m/Y'),
            'attachment_list' => $this->documentNames($approvalRequest->supplementary_documents),
            'evidence_present' => filled($approvalRequest->evidence_image),
        ];
    }

    private function locationAppendixRenderData(VenueCluster $cluster, VenueLocationChangeRequest $locationRequest): array
    {
        $application = $this->partnerApplicationForCluster($cluster);
        $contract = $this->activePartnerContractForCluster($cluster);
        $owner = $cluster->owner;
        $ownerName = $application?->representative_name
            ?: $application?->applicant_full_name
            ?: $owner?->full_name
            ?: $owner?->username;
        $requestDocument = $locationRequest->generatedDocument()->first();
        $appendixSequence = $this->nextAppendixSequence($cluster, $contract, $locationRequest);

        return [
            'appendix_sequence' => $appendixSequence,
            'appendix_number' => $this->toRomanNumeral($appendixSequence),
            'owner_full_name' => $ownerName,
            'owner_signer_name' => $ownerName,
            'owner_signer_full_name' => $ownerName,
            'business_name' => $application?->business_name ?: $ownerName,
            'identity_number' => $application?->representative_identity_number,
            'tax_code' => $application?->tax_code,
            'business_license_number' => $application?->business_license_number ?: $application?->business_code,
            'owner_phone' => $application?->applicant_phone ?: $application?->venue_phone ?: $owner?->phone,
            'owner_email' => $application?->applicant_email ?: $application?->venue_email ?: $owner?->email,
            'owner_address' => $application?->business_address ?: $application?->applicant_address ?: $cluster->address,
            'venue_name' => $cluster->name,
            'cluster_name' => $cluster->name,
            'request_id' => $locationRequest->id,
            'request_code' => $requestDocument?->document_code ?: $locationRequest->id,
            'current_address' => $cluster->address,
            'current_province' => $cluster->province,
            'current_ward' => $cluster->ward,
            'current_latitude' => $cluster->latitude,
            'current_longitude' => $cluster->longitude,
            'current_map_url' => $cluster->map_url,
            'venue_phone' => $cluster->phone_contact,
            'venue_manager_name' => $ownerName,
            'new_address' => $locationRequest->new_address,
            'new_province' => $locationRequest->new_province,
            'new_ward' => $locationRequest->new_ward,
            'new_latitude' => $locationRequest->new_latitude,
            'new_longitude' => $locationRequest->new_longitude,
            'new_map_url' => $locationRequest->new_map_url,
            'contract_code' => $contract?->contract_code,
            'contract_signed_at' => $this->formatDateTime($this->contractTimelineAt($contract)),
            'reason' => $locationRequest->note ?: $locationRequest->status_reason,
            'submitted_at' => $this->formatDateTime($locationRequest->created_at),
            'reviewed_at' => $this->formatDateTime($locationRequest->reviewed_at),
            'expected_effective_date' => now()->format('d/m/Y'),
            'attachment_list' => $this->documentNames($locationRequest->supplementary_documents),
        ];
    }

    private function scaleCourtSummaries(VenueCourtApprovalRequest $approvalRequest): array
    {
        $requestedCourts = collect($approvalRequest->requested_courts ?: []);

        if ($requestedCourts->isEmpty() && ! in_array($approvalRequest->change_type, ['remove'], true)) {
            $requestedCourts = collect([[
                'court_type_id' => $approvalRequest->court_type_id,
                'name' => $approvalRequest->name,
            ]]);
        }

        $typeNames = \App\Models\CourtType::query()
            ->whereIn('id', $requestedCourts->pluck('court_type_id')->filter()->unique())
            ->pluck('name', 'id');

        $newCourtsSummary = $requestedCourts
            ->map(function (array $court) use ($typeNames): string {
                $name = trim((string) ($court['name'] ?? ''));
                $typeName = $typeNames[(int) ($court['court_type_id'] ?? 0)] ?? null;

                return trim($name . ($typeName ? ' - ' . $typeName : ''));
            })
            ->filter()
            ->implode('; ');
        $effectiveDate = $approvalRequest->reviewed_at
            ? $approvalRequest->reviewed_at->format('d/m/Y')
            : now()->format('d/m/Y');
        $requestedRows = $requestedCourts
            ->map(fn (array $court): array => [
                'name' => trim((string) ($court['name'] ?? '')),
                'court_type_id' => (int) ($court['court_type_id'] ?? 0),
                'court_type_name' => $typeNames[(int) ($court['court_type_id'] ?? 0)] ?? null,
                'change' => 'Tăng/thêm sân',
                'effective_date' => $effectiveDate,
                'status' => 'Dự kiến hoạt động sau phê duyệt',
                'note' => 'Theo đơn yêu cầu đã ký',
            ])
            ->values()
            ->all();

        $removedCourts = VenueCourt::query()
            ->with('courtType:id,name')
            ->whereIn('id', $approvalRequest->removed_court_ids ?: [])
            ->get();

        $removedCourtsSummary = $removedCourts
            ->map(fn (VenueCourt $court): string => trim($court->name . ' - ' . ($court->courtType?->name ?? '')))
            ->filter()
            ->implode('; ');
        $removedRows = $removedCourts
            ->map(fn (VenueCourt $court): array => [
                'id' => $court->id,
                'name' => $court->name,
                'court_type_id' => $court->court_type_id,
                'court_type_name' => $court->courtType?->name,
                'change' => 'Giảm/ngừng khai thác',
                'effective_date' => $effectiveDate,
                'status' => 'Dự kiến ngừng khai thác',
                'note' => 'Theo đơn yêu cầu đã ký',
            ])
            ->values()
            ->all();

        return [
            'new' => $newCourtsSummary,
            'removed' => $removedCourtsSummary,
            'new_count' => $requestedCourts->count(),
            'removed_count' => $removedCourts->count(),
            'requested_rows' => $requestedRows,
            'removed_rows' => $removedRows,
            'rows' => array_values(array_merge($requestedRows, $removedRows)),
        ];
    }

    private function scaleChangeAction(string $changeType): string
    {
        return match ($changeType) {
            'remove' => 'Giam quy mo/xoa bot san con',
            'mixed' => 'Dieu chinh quy mo san',
            default => 'Mo rong quy mo/them san con',
        };
    }

    private function scaleChangeCount(string $changeType, array $summary): string
    {
        return match ($changeType) {
            'remove' => '-' . $summary['removed_count'],
            'mixed' => '+' . $summary['new_count'] . ' / -' . $summary['removed_count'],
            default => (string) $summary['new_count'],
        };
    }

    private function scaleChangeSummary(VenueCourtApprovalRequest $approvalRequest): string
    {
        $summary = $this->scaleCourtSummaries($approvalRequest);
        $parts = [];

        if ($summary['new'] !== '') {
            $parts[] = 'Them: ' . $summary['new'];
        }

        if ($summary['removed'] !== '') {
            $parts[] = 'Xoa bot: ' . $summary['removed'];
        }

        return implode(' | ', $parts) ?: ($approvalRequest->name . ' - ' . ($approvalRequest->courtType?->name ?? 'Loai san'));
    }

    private function nextAppendixSequence(VenueCluster $cluster, ?PartnerContract $contract, $requestModel): int
    {
        $query = GeneratedDocument::query()
            ->whereIn('document_type', ['venue_scale_appendix', 'venue_location_appendix'])
            ->whereNotIn('status', ['draft_preview', 'cancelled', 'voided']);

        if ($contract?->id) {
            $query->where('partner_contract_id', $contract->id);
        } else {
            $query->where('venue_cluster_id', $cluster->id);
        }

        if ($requestModel?->getKey()) {
            $query->where(function ($nested) use ($requestModel): void {
                $nested->where('reference_type', '!=', $requestModel::class)
                    ->orWhere('reference_id', '!=', (string) $requestModel->getKey());
            });
        }

        return max(1, $query->count() + 1);
    }

    private function toRomanNumeral(int $number): string
    {
        $number = max(1, min(3999, $number));
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }

    private function activePartnerContractForCluster(VenueCluster $cluster): ?PartnerContract
    {
        return PartnerContract::query()
            ->with(['application.user'])
            ->where('venue_cluster_id', $cluster->id)
            ->whereIn('status', ['signed_active', 'completed', 'active', 'generated', 'draft', 'pending_owner_signature', 'pending_sportgo_signature'])
            ->orderByRaw('COALESCE(sportgo_signed_at, owner_signed_at, effective_from, created_at) DESC')
            ->first();
    }

    private function contractTimelineAt(?PartnerContract $contract)
    {
        return $contract?->sportgo_signed_at
            ?: $contract?->owner_signed_at
            ?: $contract?->effective_from
            ?: $contract?->created_at;
    }

    private function partnerApplicationForCluster(VenueCluster $cluster): ?PartnerApplication
    {
        return PartnerApplication::query()
            ->with('user')
            ->where('approved_venue_cluster_id', $cluster->id)
            ->latest('reviewed_at')
            ->latest('created_at')
            ->first();
    }

    private function documentNames($documents): string
    {
        return collect($documents ?: [])
            ->map(fn ($document) => is_array($document)
                ? ($document['file_name'] ?? $document['name'] ?? $document['original_name'] ?? null)
                : null)
            ->filter()
            ->implode('; ');
    }

    private function formatDateTime($value): ?string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('H:i d/m/Y') : null;
    }

    private function lockSnapshot(VenueCluster $c): array
    {
        return [
            'status'        => $c->status,
            'status_reason' => $c->status_reason,
            'locked_at'     => $c->locked_at,
            'locked_until'  => $c->locked_until,
            'locked_by'     => $c->locked_by,
        ];
    }

    private function audit(Request $request, $actor, string $action, $entity, array $oldValues, array $newValues): void
    {
        if (! class_exists(AuditLog::class) || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $entityTable = match (true) {
            $entity instanceof VenueCluster              => 'venue_clusters',
            $entity instanceof VenueCourtApprovalRequest => 'venue_court_approval_requests',
            default                                      => class_basename($entity),
        };

        AuditLog::create([
            'actor_id'    => $actor->id,
            'action'      => $action,
            'entity_type' => $entityTable,
            'entity_id'   => $entity->id,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'context'     => 'admin',
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function notifyOwner(VenueCluster $cluster, string $title, string $body, VenueUnlockRequest $request): void
    {
        if ($cluster->owner_id) {
            Notification::create([
                'user_id'        => $cluster->owner_id,
                'type'           => 'venue_cluster_unlock_appeal',
                'title'          => $title,
                'body'           => $body,
                'reference_type' => 'venue_unlock_request',
                'reference_id'   => $request->id,
            ]);
        }
    }

    private function unlockRequestPayload(VenueUnlockRequest $r): array
    {
        return [
            'id'               => $r->id,
            'venue_cluster_id' => $r->venue_cluster_id,
            'status'           => $r->status,
            'reason'           => $r->reason,
            'admin_note'       => $r->admin_note,
            'requested_by'     => $r->requestedBy ? ['id' => $r->requestedBy->id, 'full_name' => $r->requestedBy->full_name] : null,
            'reviewed_by'      => $r->reviewedBy ? ['id' => $r->reviewedBy->id, 'full_name' => $r->reviewedBy->full_name] : null,
            'reviewed_at'      => $r->reviewed_at,
            'created_at'       => $r->created_at,
        ];
    }

    public function approveInformationChange(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $infoRequest = \App\Models\VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($infoRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $cluster = VenueCluster::findOrFail($clusterId);

        $oldValues = [
            'name'          => $cluster->name,
            'phone_contact' => $cluster->phone_contact,
            'description'   => $cluster->description,
        ];

        // 1. Áp dụng thông tin mới
        $cluster->forceFill([
            'name'          => $infoRequest->new_name,
            'phone_contact' => $infoRequest->new_phone_contact,
            'description'   => $infoRequest->new_description,
            'slug'          => \Illuminate\Support\Str::slug($infoRequest->new_name) . '-' . substr($clusterId, 0, 8),
        ])->save();

        // 2. Áp dụng hình ảnh mới nếu có
        if (is_array($infoRequest->new_images) && count($infoRequest->new_images) > 0) {
            // Xóa ảnh cũ
            $oldMedia = \App\Models\Media::where('mediable_type', VenueCluster::class)
                ->where('mediable_id', $clusterId)
                ->get();
            foreach ($oldMedia as $m) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($m->file_path);
                $m->delete();
            }

            // Tạo các bản ghi media mới từ ảnh tạm
            foreach ($infoRequest->new_images as $tempPath) {
                $fileName = basename($tempPath);
                $newPath = 'clusters/' . $fileName;
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($tempPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->move($tempPath, $newPath);

                    \App\Models\Media::create([
                        'mediable_type' => VenueCluster::class,
                        'mediable_id' => $clusterId,
                        'collection' => 'gallery',
                        'file_name' => $fileName,
                        'file_path' => $newPath,
                        'mime_type' => \Illuminate\Support\Facades\Storage::disk('public')->mimeType($newPath) ?: 'image/jpeg',
                        'file_size' => \Illuminate\Support\Facades\Storage::disk('public')->size($newPath) ?: 0,
                    ]);
                }
            }
        }

        $infoRequest->forceFill([
            'status'      => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => null,
        ])->save();

        $this->audit(
            $request, $actor,
            'venue_cluster.information_changed',
            $cluster,
            $oldValues,
            [
                'name'          => $infoRequest->new_name,
                'phone_contact' => $infoRequest->new_phone_contact,
                'description'   => $infoRequest->new_description,
            ]
        );

        return response()->json([
            'message' => 'Duyệt yêu cầu thành công. Thông tin cụm sân đã được cập nhật.',
            'request' => $this->informationChangePayload($infoRequest->fresh(['requestedBy', 'reviewedBy'])),
        ]);
    }

    public function rejectInformationChange(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $data = $request->validate([
            'status_reason' => ['required', 'string', 'max:2000'],
        ], [
            'status_reason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $infoRequest = \App\Models\VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($infoRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu này đã được xử lý.'], 422);
        }

        $infoRequest->forceFill([
            'status'        => 'rejected',
            'reviewed_by'   => $actor->id,
            'reviewed_at'   => now(),
            'status_reason' => $data['status_reason'],
        ])->save();

        $this->audit(
            $request, $actor,
            'venue_cluster.information_change_rejected',
            VenueCluster::findOrFail($clusterId),
            [],
            ['reason' => $data['status_reason']]
        );

        return response()->json([
            'message' => 'Đã từ chối yêu cầu chỉnh sửa thông tin.',
            'request' => $this->informationChangePayload($infoRequest->fresh(['requestedBy', 'reviewedBy'])),
        ]);
    }

    private function informationChangePayload(\App\Models\VenueInformationChangeRequest $r): array
    {
        return [
            'id'                => $r->id,
            'status'            => $r->status,
            'note'              => $r->note,
            'status_reason'     => $r->status_reason,
            'new_name'          => $r->new_name,
            'new_phone_contact' => $r->new_phone_contact,
            'new_description'   => $r->new_description,
            'new_images'        => $r->new_images,
            'requested_by'      => $r->requestedBy ? [
                'id'        => $r->requestedBy->id,
                'full_name' => $r->requestedBy->full_name,
            ] : null,
            'reviewed_by'       => $r->reviewedBy ? [
                'id'        => $r->reviewedBy->id,
                'full_name' => $r->reviewedBy->full_name,
            ] : null,
            'reviewed_at'       => $r->reviewed_at,
            'created_at'        => $r->created_at,
        ];
    }
}
