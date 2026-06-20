<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueCourtApprovalRequest;
use App\Models\VenueLocationChangeRequest;
use App\Models\VenuePlatformFeeLedger;
use App\Models\VenueUnlockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VenueClusterController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // Danh sách cụm sân toàn hệ thống (filter theo status)
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = VenueCluster::query()
            ->with([
                'owner:id,full_name,username,email,phone',
                'venueCourts:id,venue_cluster_id,court_type_id,name,status',
                'venueCourts.courtType:id,name',
                'latestPlatformFeeLedger',
                'media',
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
            ->with(['customer:id,full_name,username,phone', 'venueCourt:id,name'])
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
                'status'       => $b->status,
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

        return response()->json([
            'data' => [
                'cluster'                  => $this->detailPayload($cluster),
                'bookings'                 => $bookings,
                'fees'                     => $fees,
                'lock_history'             => $lockHistory,
                'approval_requests'        => $approvalRequests,
                'location_change_requests' => $locationChangeRequests,
                'unlock_requests'          => $unlockRequests,
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

        if ($cluster->status !== 'locked') {
            return response()->json(['message' => 'Cụm sân không ở trạng thái bị khóa.'], 422);
        }

        $oldValues = $this->lockSnapshot($cluster);

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
        $newCourt = VenueCourt::create([
            'venue_cluster_id' => $clusterId,
            'court_type_id'    => $approvalRequest->court_type_id,
            'name'             => $approvalRequest->name,
            'status'           => 'active',
        ]);

        $approvalRequest->forceFill([
            'status'                  => 'approved',
            'reviewed_by'             => $actor->id,
            'reviewed_at'             => now(),
            'approved_venue_court_id' => $newCourt->id,
            'status_reason'           => null,
        ])->save();

        $this->audit($request, $actor, 'venue_court_approval.approved', $approvalRequest, ['status' => 'pending'], ['status' => 'approved', 'venue_court_id' => $newCourt->id]);

        return response()->json([
            'message' => 'Duyệt yêu cầu thành công. Sân con mới đã được tạo.',
            'request' => $this->approvalPayload($approvalRequest->fresh(['courtType', 'requestedBy', 'reviewedBy'])),
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

        return response()->json([
            'message' => 'Đã từ chối yêu cầu.',
            'request' => $this->approvalPayload($approvalRequest->fresh(['courtType', 'requestedBy', 'reviewedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Duyệt yêu cầu thay đổi vị trí
    // ─────────────────────────────────────────────────────────────────
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

        $cluster = VenueCluster::findOrFail($clusterId);

        $oldValues = [
            'address'   => $cluster->address,
            'province'  => $cluster->province,
            'ward'      => $cluster->ward,
            'latitude'  => $cluster->latitude,
            'longitude' => $cluster->longitude,
            'map_url'   => $cluster->map_url,
        ];

        // Cập nhật vị trí cluster từ snapshot
        $cluster->forceFill([
            'address'   => $locationRequest->new_address,
            'province'  => $locationRequest->new_province,
            'ward'      => $locationRequest->new_ward,
            'latitude'  => $locationRequest->new_latitude,
            'longitude' => $locationRequest->new_longitude,
            'map_url'   => $locationRequest->new_map_url,
        ])->save();

        $locationRequest->forceFill([
            'status'      => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'status_reason' => null,
        ])->save();

        $this->audit(
            $request, $actor,
            'venue_cluster.location_changed',
            $cluster,
            $oldValues,
            [
                'address'   => $locationRequest->new_address,
                'province'  => $locationRequest->new_province,
                'ward'      => $locationRequest->new_ward,
                'latitude'  => $locationRequest->new_latitude,
                'longitude' => $locationRequest->new_longitude,
            ]
        );

        return response()->json([
            'message' => 'Duyệt yêu cầu thành công. Vị trí cụm sân đã được cập nhật.',
            'request' => $this->locationChangePayload($locationRequest->fresh(['requestedBy', 'reviewedBy'])),
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

        return response()->json([
            'message' => 'Đã từ chối yêu cầu.',
            'request' => $this->locationChangePayload($locationRequest->fresh(['requestedBy', 'reviewedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Duyệt yêu cầu mở khóa cụm sân
    // ─────────────────────────────────────────────────────────────────
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
        ]);
    }

    private function approvalPayload(VenueCourtApprovalRequest $r): array
    {
        return [
            'id'                      => $r->id,
            'name'                    => $r->name,
            'status'                  => $r->status,
            'status_reason'           => $r->status_reason,
            'evidence_image'          => $r->evidence_image,
            'evidence_image_url'      => $r->evidence_image ? asset('storage/' . $r->evidence_image) : null,
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
        return [
            'id'            => $r->id,
            'status'        => $r->status,
            'note'          => $r->note,
            'status_reason' => $r->status_reason,
            'new_address'   => $r->new_address,
            'new_province'  => $r->new_province,
            'new_ward'      => $r->new_ward,
            'new_latitude'  => $r->new_latitude,
            'new_longitude' => $r->new_longitude,
            'new_map_url'   => $r->new_map_url,
            'requested_by'  => $r->requestedBy ? ['id' => $r->requestedBy->id, 'full_name' => $r->requestedBy->full_name] : null,
            'reviewed_by'   => $r->reviewedBy ? ['id' => $r->reviewedBy->id, 'full_name' => $r->reviewedBy->full_name] : null,
            'reviewed_at'   => $r->reviewed_at,
            'created_at'    => $r->created_at,
        ];
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
}
