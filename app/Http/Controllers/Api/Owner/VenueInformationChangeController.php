<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueInformationChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VenueInformationChangeController extends Controller
{
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $query = VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['requestedBy:id,full_name,username', 'reviewedBy:id,full_name,username'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get()->map(fn ($r) => $this->payload($r));

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền gửi yêu cầu cho cụm sân này.'], 403);
        }

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đang bị khóa. Không thể gửi yêu cầu mới.'], 422);
        }

        $hasPending = VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Bạn đã có yêu cầu chỉnh sửa thông tin đang chờ xét duyệt. Vui lòng hủy yêu cầu cũ trước khi gửi yêu cầu mới.',
            ], 422);
        }

        $data = $request->validate([
            'new_name'          => ['required', 'string', 'max:255'],
            'new_phone_contact' => ['required', 'string', 'max:20'],
            'new_description'   => ['nullable', 'string', 'max:2000'],
            'new_images'        => ['nullable', 'array'],
            'note'              => ['required', 'string', 'max:1000'],
        ], [
            'new_name.required'          => 'Vui lòng nhập tên mới.',
            'new_phone_contact.required' => 'Vui lòng nhập số điện thoại mới.',
            'note.required'              => 'Vui lòng nhập lý do chỉnh sửa thông tin.',
        ]);

        $infoRequest = VenueInformationChangeRequest::create([
            'venue_cluster_id'  => $clusterId,
            'requested_by'      => $request->user()->id,
            'status'            => 'pending',
            'note'              => $data['note'],
            'new_name'          => $data['new_name'],
            'new_phone_contact' => $data['new_phone_contact'],
            'new_description'   => $data['new_description'] ?? null,
            'new_images'        => $data['new_images'] ?? [],
        ]);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $this->payload($infoRequest->load(['requestedBy:id,full_name,username'])),
        ], 201);
    }

    public function cancel(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền hủy yêu cầu này.'], 403);
        }

        $infoRequest = VenueInformationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($infoRequest->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $infoRequest->forceFill(['status' => 'cancelled'])->save();

        return response()->json([
            'message' => 'Đã hủy yêu cầu.',
            'data'    => $this->payload($infoRequest->fresh(['requestedBy'])),
        ]);
    }

    public function uploadTempMedia(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền upload ảnh cho cụm sân này.'], 403);
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // max 5MB
        ]);

        $path = $request->file('image')->store('temp_clusters', 'public');

        return response()->json([
            'message' => 'Tải lên hình ảnh tạm thời thành công.',
            'data' => [
                'file_path' => $path,
                'url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    private function payload(VenueInformationChangeRequest $r): array
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
