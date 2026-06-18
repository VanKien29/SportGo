<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueLocationChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueLocationChangeController extends Controller
{
    /**
     * Lấy lịch sử yêu cầu thay đổi vị trí của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $query = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['requestedBy:id,full_name,username', 'reviewedBy:id,full_name,username'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get()->map(fn ($r) => $this->payload($r));

        return response()->json(['data' => $requests]);
    }

    /**
     * Gửi yêu cầu thay đổi vị trí cụm sân.
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

        // Kiểm tra xem đã có yêu cầu pending chưa
        $hasPending = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Bạn đã có yêu cầu thay đổi vị trí đang chờ xét duyệt. Vui lòng hủy yêu cầu cũ trước khi gửi yêu cầu mới.',
            ], 422);
        }

        $data = $request->validate([
            'new_address'   => ['required', 'string', 'max:255'],
            'new_province'  => ['required', 'string', 'max:255'],
            'new_ward'      => ['required', 'string', 'max:255'],
            'new_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'new_longitude' => ['required', 'numeric', 'between:-180,180'],
            'new_map_url'   => ['nullable', 'url', 'max:2000'],
            'note'          => ['required', 'string', 'max:1000'],
        ], [
            'new_address.required'   => 'Vui lòng nhập địa chỉ mới.',
            'new_province.required'  => 'Vui lòng nhập tỉnh/thành phố mới.',
            'new_ward.required'      => 'Vui lòng nhập phường/xã mới.',
            'new_latitude.required'  => 'Vui lòng nhập vĩ độ.',
            'new_latitude.between'   => 'Vĩ độ không hợp lệ.',
            'new_longitude.required' => 'Vui lòng nhập kinh độ.',
            'new_longitude.between'  => 'Kinh độ không hợp lệ.',
            'note.required'          => 'Vui lòng nhập lý do muốn thay đổi vị trí.',
        ]);

        $locationRequest = VenueLocationChangeRequest::create([
            'venue_cluster_id' => $clusterId,
            'requested_by'     => $request->user()->id,
            'status'           => 'pending',
            'note'             => $data['note'],
            'new_address'      => $data['new_address'],
            'new_province'     => $data['new_province'],
            'new_ward'         => $data['new_ward'],
            'new_latitude'     => $data['new_latitude'],
            'new_longitude'    => $data['new_longitude'],
            'new_map_url'      => $data['new_map_url'] ?? null,
        ]);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $this->payload($locationRequest->load(['requestedBy:id,full_name,username'])),
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

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($locationRequest->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $locationRequest->forceFill(['status' => 'cancelled'])->save();

        return response()->json([
            'message' => 'Đã hủy yêu cầu.',
            'data'    => $this->payload($locationRequest->fresh(['requestedBy'])),
        ]);
    }

    private function payload(VenueLocationChangeRequest $r): array
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
            'requested_by'  => $r->requestedBy ? [
                'id'        => $r->requestedBy->id,
                'full_name' => $r->requestedBy->full_name,
            ] : null,
            'reviewed_by'   => $r->reviewedBy ? [
                'id'        => $r->reviewedBy->id,
                'full_name' => $r->reviewedBy->full_name,
            ] : null,
            'reviewed_at'   => $r->reviewed_at,
            'created_at'    => $r->created_at,
        ];
    }
}
