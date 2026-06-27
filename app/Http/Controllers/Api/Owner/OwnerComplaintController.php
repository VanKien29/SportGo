<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\Media;
use App\Models\VenueCluster;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerComplaintController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    private function getOwnerClusterIds(Request $request)
    {
        return VenueCluster::where('owner_id', $request->user()->id)->pluck('id');
    }

    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $request->validate([
            'status' => ['nullable', Rule::in(['open', 'processing', 'resolved', 'rejected', 'closed'])],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        $complaints = Complaint::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->with([
                'customer:id,username,full_name,email,phone',
                'venueCluster:id,name',
                'booking:id,booking_code,status,total_price,booking_date',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%'.$request->query('keyword').'%';
                $query->where(function ($inner) use ($keyword): void {
                    $inner->where('content', 'like', $keyword)
                        ->orWhereHas('customer', fn ($user) => $user
                            ->where('full_name', 'like', $keyword)
                            ->orWhere('email', 'like', $keyword)
                            ->orWhere('phone', 'like', $keyword))
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_code', 'like', $keyword))
                        ->orWhereHas('venueCluster', fn ($venue) => $venue->where('name', 'like', $keyword));
                });
            })
            ->latest()
            ->paginate($request->query('per_page', 15));

        $items = collect($complaints->items())->map(fn (Complaint $complaint) => [
            'id' => $complaint->id,
            'complaint_type' => $complaint->complaint_type,
            'content' => $complaint->content,
            'status' => $complaint->status,
            'customer' => $complaint->customer ? [
                'id' => $complaint->customer->id,
                'full_name' => $complaint->customer->full_name,
                'email' => $complaint->customer->email,
                'phone' => $complaint->customer->phone,
            ] : null,
            'venue_cluster' => $complaint->venueCluster ? [
                'id' => $complaint->venueCluster->id,
                'name' => $complaint->venueCluster->name,
            ] : null,
            'booking' => $complaint->booking ? [
                'id' => $complaint->booking->id,
                'booking_code' => $complaint->booking->booking_code,
                'status' => $complaint->booking->status,
                'total_price' => $complaint->booking->total_price,
                'booking_date' => $complaint->booking->booking_date,
            ] : null,
            'resolved_at' => $complaint->resolved_at,
            'created_at' => $complaint->created_at,
        ]);

        return response()->json([
            'data' => collect($complaints)->merge(['data' => $items]),
            'summary' => [
                'total' => Complaint::query()->whereIn('venue_cluster_id', $clusterIds)->count(),
                'open' => Complaint::query()->whereIn('venue_cluster_id', $clusterIds)->where('status', 'open')->count(),
                'processing' => Complaint::query()->whereIn('venue_cluster_id', $clusterIds)->where('status', 'processing')->count(),
                'resolved' => Complaint::query()->whereIn('venue_cluster_id', $clusterIds)->where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $complaint = Complaint::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->with([
                'customer:id,username,full_name,email,phone',
                'venueCluster:id,name,address,owner_id',
                'booking.customer:id,full_name,email,phone',
                'booking.venueCluster:id,name',
                'booking.venueCourt:id,name',
                'evidence',
                'replies.user:id,full_name,username',
                'replies.evidence',
            ])
            ->findOrFail($id);

        $booking = $complaint->booking;

        $complaintData = [
            'id' => $complaint->id,
            'complaint_type' => $complaint->complaint_type,
            'content' => $complaint->content,
            'status' => $complaint->status,
            'customer' => $complaint->customer ? [
                'id' => $complaint->customer->id,
                'full_name' => $complaint->customer->full_name,
                'email' => $complaint->customer->email,
                'phone' => $complaint->customer->phone,
            ] : null,
            'resolve_note' => $complaint->resolve_note,
            'status_reason' => $complaint->status_reason,
            'resolved_at' => $complaint->resolved_at,
            'created_at' => $complaint->created_at,
            'booking_detail' => $booking ? [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'booking_date' => $booking->booking_date,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'status' => $booking->status,
                'total_price' => $booking->total_price,
                'required_payment_amount' => $booking->required_payment_amount,
                'venue_cluster' => $booking->venueCluster?->only(['id', 'name']),
                'venue_court' => $booking->venueCourt?->only(['id', 'name']),
            ] : null,
            'evidence' => $complaint->evidence->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'file_path' => $media->file_path,
                'mime_type' => $media->mime_type,
            ])->values(),
        ];

        $auditLogs = AuditLog::query()
            ->where('entity_type', 'complaints')
            ->where('entity_id', $complaint->id)
            ->with('user:id,full_name,username')
            ->get(['id', 'user_id', 'action', 'details', 'created_at']);

        // Format timeline merging audit_logs and replies
        $timeline = collect();

        foreach ($auditLogs as $log) {
            $timeline->push([
                'type' => 'log',
                'id' => $log->id,
                'action' => $log->action,
                'details' => $log->details,
                'user' => $log->user,
                'created_at' => $log->created_at,
            ]);
        }

        foreach ($complaint->replies as $reply) {
            $timeline->push([
                'type' => 'reply',
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $reply->user,
                'evidence' => $reply->evidence->map(fn ($media) => [
                    'id' => $media->id,
                    'file_path' => $media->file_path,
                    'file_name' => $media->file_name,
                ]),
                'created_at' => $reply->created_at,
            ]);
        }

        // Sort by created_at ascending
        $timeline = $timeline->sortBy('created_at')->values();

        return response()->json([
            'data' => [
                'complaint' => $complaintData,
                'timeline' => $timeline,
            ],
        ]);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $complaint = Complaint::query()
            ->whereIn('venue_cluster_id', $clusterIds)
            ->findOrFail($id);

        if (in_array($complaint->status, ['resolved', 'rejected', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Khiếu nại này đã kết thúc, không thể gửi thêm phản hồi.',
            ]);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5MB max
        ]);

        DB::beginTransaction();

        try {
            $reply = ComplaintReply::create([
                'complaint_id' => $complaint->id,
                'user_id' => $request->user()->id,
                'content' => $data['content'],
            ]);

            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $index => $file) {
                    $path = $file->store('complaints/replies', 'public');
                    $reply->evidence()->create([
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => '/storage/' . $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'sort_order' => $index,
                    ]);
                }
            }

            // Change complaint status to processing if it was open
            if ($complaint->status === 'open') {
                $oldValues = $complaint->only(['status']);
                $complaint->status = 'processing';
                $complaint->save();
                
                $this->audit->log($request, 'complaint', 'complaint.processing', 'complaints', $complaint->id, $oldValues, $complaint->toArray());
            }

            DB::commit();

            return response()->json([
                'message' => 'Đã gửi phản hồi giải trình thành công.',
                'data' => [
                    'type' => 'reply',
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'user' => $reply->load('user:id,full_name,username')->user,
                    'evidence' => $reply->evidence->map(fn ($media) => [
                        'id' => $media->id,
                        'file_path' => $media->file_path,
                        'file_name' => $media->file_name,
                    ]),
                    'created_at' => $reply->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
