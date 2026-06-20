<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\VenueCluster;
use App\Models\VenueUnlockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VenueUnlockRequestController extends Controller
{
    /**
     * Lấy danh sách yêu cầu mở khóa của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if (! $this->canAccess($request, $cluster)) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $requests = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['requestedBy:id,full_name,username', 'reviewedBy:id,full_name,username'])
            ->latest()
            ->get()
            ->map(fn ($r) => $this->payload($r));

        return response()->json([
            'data' => $requests,
            'cluster' => [
                'id'            => $cluster->id,
                'name'          => $cluster->name,
                'status'        => $cluster->status,
                'status_reason' => $cluster->status_reason,
                'locked_at'     => $cluster->locked_at,
            ],
        ]);
    }

    /**
     * Gửi yêu cầu mở khóa cụm sân.
     */
    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if (! $this->canAccess($request, $cluster)) {
            return response()->json(['message' => 'Bạn không có quyền gửi yêu cầu cho cụm sân này.'], 403);
        }

        if ($cluster->status !== 'locked') {
            return response()->json(['message' => 'Cụm sân hiện không ở trạng thái bị khóa.'], 422);
        }

        $hasPending = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Bạn đã có yêu cầu mở khóa đang chờ xét duyệt. Vui lòng chờ Admin phản hồi hoặc hủy yêu cầu cũ.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do giải trình.',
            'reason.min'      => 'Lý do giải trình phải có ít nhất 10 ký tự.',
            'reason.max'      => 'Lý do giải trình không được vượt quá 2000 ký tự.',
        ]);

        $unlockRequest = DB::transaction(function () use ($request, $cluster, $data) {
            $unlockRequest = VenueUnlockRequest::create([
                'venue_cluster_id' => $cluster->id,
                'requested_by'     => $request->user()->id,
                'status'           => 'pending',
                'reason'           => $data['reason'],
            ]);

            $this->audit(
                $request,
                'venue_cluster.unlock_request_created',
                $cluster,
                null,
                $this->payload($unlockRequest)
            );

            return $unlockRequest;
        });

        return response()->json([
            'message' => 'Gửi yêu cầu mở khóa thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $this->payload($unlockRequest->load(['requestedBy:id,full_name,username'])),
        ], 201);
    }

    /**
     * Hủy yêu cầu đang ở trạng thái chờ duyệt.
     */
    public function cancel(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if (! $this->canAccess($request, $cluster)) {
            return response()->json(['message' => 'Bạn không có quyền hủy yêu cầu này.'], 403);
        }

        $unlockRequest = VenueUnlockRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($unlockRequest->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $oldValues = $this->payload($unlockRequest);
        $unlockRequest->forceFill(['status' => 'cancelled'])->save();

        $this->audit($request, 'venue_cluster.unlock_request_cancelled', $cluster, $oldValues, $this->payload($unlockRequest));

        return response()->json([
            'message' => 'Đã hủy yêu cầu mở khóa.',
            'data'    => $this->payload($unlockRequest->fresh(['requestedBy', 'reviewedBy'])),
        ]);
    }

    private function canAccess(Request $request, VenueCluster $cluster): bool
    {
        $userId = $request->user()->id;

        if ($cluster->owner_id === $userId) {
            return true;
        }

        // Nhân viên được gán vào cụm sân cũng có quyền
        return DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'active')
            ->exists();
    }

    private function payload(VenueUnlockRequest $r): array
    {
        return [
            'id'                => $r->id,
            'venue_cluster_id'  => $r->venue_cluster_id,
            'status'            => $r->status,
            'reason'            => $r->reason,
            'admin_note'        => $r->admin_note,
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

    private function audit(Request $request, string $action, VenueCluster $cluster, ?array $oldValues, ?array $newValues): void
    {
        if (! class_exists(AuditLog::class) || ! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'actor_id'    => $request->user()->id,
            'actor_type'  => 'owner',
            'module'      => 'venue',
            'action'      => $action,
            'entity_type' => 'venue_clusters',
            'entity_id'   => $cluster->id,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'context'     => 'owner',
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
