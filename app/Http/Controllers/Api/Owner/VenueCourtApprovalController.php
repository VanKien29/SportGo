<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueCourtApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueCourtApprovalController extends Controller
{
    /**
     * Lấy danh sách yêu cầu quy mô sân của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $query = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['courtType:id,name', 'requestedBy:id,full_name,username', 'reviewedBy:id,full_name,username'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get()->map(fn ($r) => $this->payload($r));

        return response()->json(['data' => $requests]);
    }

    /**
     * Gửi yêu cầu mở rộng quy mô (thêm sân con mới).
     */
    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền gửi yêu cầu cho cụm sân này.'], 403);
        }

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đang bị khóa. Không thể gửi yêu cầu mới.'], 422);
        }

        $data = $request->validate([
            'court_type_id'  => ['required', 'integer', 'exists:court_types,id'],
            'name'           => ['required', 'string', 'max:100'],
            'note'           => ['nullable', 'string', 'max:1000'],
            'evidence_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'court_type_id.required'  => 'Vui lòng chọn loại sân.',
            'court_type_id.exists'    => 'Loại sân không tồn tại.',
            'name.required'           => 'Vui lòng nhập tên sân.',
            'name.max'                => 'Tên sân không được quá 100 ký tự.',
            'evidence_image.image'    => 'File minh chứng phải là ảnh.',
            'evidence_image.mimes'    => 'Ảnh minh chứng phải có định dạng: jpg, jpeg, png, webp.',
            'evidence_image.max'      => 'Ảnh minh chứng không được quá 5MB.',
        ]);

        // Xử lý upload ảnh minh chứng
        $evidencePath = null;
        if ($request->hasFile('evidence_image')) {
            $evidencePath = $request->file('evidence_image')
                ->store('approval-evidence/' . $clusterId, 'public');
        }

        $approvalRequest = VenueCourtApprovalRequest::create([
            'venue_cluster_id' => $clusterId,
            'court_type_id'    => $data['court_type_id'],
            'name'             => $data['name'],
            'status'           => 'pending',
            'requested_by'     => $request->user()->id,
            'status_reason'    => $data['note'] ?? null,
            'evidence_image'   => $evidencePath,
        ]);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $this->payload($approvalRequest->load(['courtType:id,name', 'requestedBy:id,full_name,username'])),
        ], 201);
    }

    /**
     * Hủy yêu cầu đang ở trạng thái chờ duyệt.
     */
    public function cancel(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền hủy yêu cầu này.'], 403);
        }

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $approvalRequest->forceFill([
            'status' => 'cancelled',
        ])->save();

        return response()->json([
            'message' => 'Đã hủy yêu cầu.',
            'data'    => $this->payload($approvalRequest->fresh(['courtType', 'requestedBy'])),
        ]);
    }

    private function payload(VenueCourtApprovalRequest $r): array
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
}
