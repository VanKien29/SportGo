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
            'owner_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                'pending_owner_acceptance',
                'active',
                'overdue',
                'fulfilled',
                'cancelled',
                'rejected',
                'expired',
            ])],
            'q' => ['nullable', 'string', 'max:100'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:due_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $items = PlatformFeePaymentArrangement::query()
            ->with(['venueCluster:id,name', 'owner:id,full_name,email', 'ledgers', 'holds'])
            ->when($data['venue_cluster_id'] ?? null, fn ($query, $id) => $query->where('venue_cluster_id', $id))
            ->when($data['owner_id'] ?? null, fn ($query, $id) => $query->where('owner_id', $id))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, function ($query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('code', 'like', $term)
                        ->orWhereHas('venueCluster', fn ($venueQuery) => $venueQuery->where('name', 'like', $term))
                        ->orWhereHas('owner', function ($ownerQuery) use ($term): void {
                            $ownerQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($data['due_from'] ?? null, fn ($query, $date) => $query->whereDate('payment_due_date', '>=', $date))
            ->when($data['due_to'] ?? null, fn ($query, $date) => $query->whereDate('payment_due_date', '<=', $date))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json($items);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'service_months' => ['required', 'integer', Rule::in(config('platform_fee.allowed_deferred_months'))],
        ], [
            'venue_cluster_id.required' => 'Vui lòng chọn cụm sân.',
            'venue_cluster_id.exists' => 'Cụm sân đã chọn không còn tồn tại.',
            'service_months.required' => 'Vui lòng nhập số kỳ được hoãn.',
            'service_months.integer' => 'Số kỳ được hoãn phải là số nguyên.',
            'service_months.in' => 'Số kỳ được hoãn phải từ 1 đến 3.',
        ]);

        return response()->json([
            'data' => $this->arrangements->preview(
                VenueCluster::query()->findOrFail($data['venue_cluster_id']),
                (int) $data['service_months'],
            ),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $arrangement = PlatformFeePaymentArrangement::query()
            ->with([
                'venueCluster:id,name',
                'owner:id,full_name,email',
                'ledgers.planVersion:id,code,name',
                'ledgers.servicePeriods',
                'holds',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $arrangement]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'service_months' => ['required', 'integer', Rule::in(config('platform_fee.allowed_deferred_months'))],
            'payment_due_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'venue_cluster_id.required' => 'Vui lòng chọn cụm sân.',
            'venue_cluster_id.exists' => 'Cụm sân đã chọn không còn tồn tại.',
            'service_months.required' => 'Vui lòng nhập số kỳ được hoãn.',
            'service_months.integer' => 'Số kỳ được hoãn phải là số nguyên.',
            'service_months.in' => 'Số kỳ được hoãn phải từ 1 đến 3.',
            'payment_due_date.required' => 'Vui lòng chọn hạn thanh toán.',
            'payment_due_date.date_format' => 'Hạn thanh toán phải theo định dạng ngày hợp lệ.',
            'reason.required' => 'Vui lòng nhập lý do thỏa thuận.',
            'reason.min' => 'Lý do thỏa thuận phải có ít nhất 10 ký tự.',
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
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $arrangement = $this->arrangements->cancel(
            PlatformFeePaymentArrangement::query()->findOrFail($id),
            $request->user()?->id,
            $data['reason'],
        );

        return response()->json([
            'message' => 'Đã hủy thỏa thuận. Kỳ chưa bắt đầu đã được hủy; kỳ đã bắt đầu trở về hạn thanh toán chuẩn.',
            'data' => $arrangement,
        ]);
    }
}
