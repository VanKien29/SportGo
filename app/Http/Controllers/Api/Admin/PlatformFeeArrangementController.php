<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeePaymentArrangement;
use App\Models\VenueCluster;
use App\Services\Payments\PlatformFeeArrangementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformFeeArrangementController extends Controller
{
    public function __construct(private readonly PlatformFeeArrangementService $arrangements) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);
        $items = PlatformFeePaymentArrangement::query()
            ->with(['venueCluster:id,name', 'owner:id,full_name,email', 'ledgers', 'holds'])
            ->when($data['venue_cluster_id'] ?? null, fn ($query, $id) => $query->where('venue_cluster_id', $id))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'service_months' => ['required', 'integer', Rule::in(config('platform_fee.allowed_deferred_months'))],
            'service_start' => ['required', 'date_format:Y-m-d'],
            'payment_due_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $arrangement = $this->arrangements->propose(
            VenueCluster::query()->findOrFail($data['venue_cluster_id']),
            $data,
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Đã tạo thỏa thuận trả chậm; chủ sân phải xác nhận trước khi có hiệu lực.',
            'data' => $arrangement,
        ], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $arrangement = $this->arrangements->cancel(
            PlatformFeePaymentArrangement::query()->findOrFail($id),
            $request->user()?->id,
        );

        return response()->json(['message' => 'Đã hủy thỏa thuận và giải phóng các khoản tạm giữ.', 'data' => $arrangement]);
    }
}
