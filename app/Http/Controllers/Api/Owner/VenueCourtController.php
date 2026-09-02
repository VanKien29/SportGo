<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueCourtApprovalRequest;
use App\Services\Courts\CourtStatusConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VenueCourtController extends Controller
{
    public function __construct(
        private readonly CourtStatusConflictService $courtStatusConflictService,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
        ]);

        $cluster = VenueCluster::query()->findOrFail($request->query('venue_cluster_id'));

        $isAccessible = $cluster->owner_id === $request->user()->id || 
            DB::table('venue_staff_assignments')
                ->where('user_id', $request->user()->id)
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'active')
                ->exists();

        if (! $isAccessible) {
            return response()->json(['message' => 'Bạn không có quyền xem sân con của cụm sân này.'], 403);
        }

        $courts = VenueCourt::query()
            ->with(['courtType'])
            ->where('venue_cluster_id', $cluster->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $courts]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::query()->with('courtType')->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($court->venue_cluster_id);

        $isAccessible = $cluster->owner_id === $request->user()->id
            || DB::table('venue_staff_assignments')
                ->where('user_id', $request->user()->id)
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'active')
                ->exists();

        if (! $isAccessible) {
            return response()->json(['message' => 'Bạn không có quyền xem sân con này.'], 403);
        }

        return response()->json(['data' => $court]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'court_type_id' => ['required', 'integer', 'exists:court_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'layout_x' => ['nullable', 'numeric'],
            'layout_y' => ['nullable', 'numeric'],
            'layout_w' => ['nullable', 'numeric', 'min:10'],
            'layout_h' => ['nullable', 'numeric', 'min:10'],
            'layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
        ]);

        $cluster = VenueCluster::query()->findOrFail($data['venue_cluster_id']);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền thêm sân con vào cụm sân này.'], 403);
        }

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đang bị khóa. Không thể gửi yêu cầu mới.'], 422);
        }

        // Tạo yêu cầu phê duyệt thay vì tạo sân con trực tiếp
        $approvalRequest = VenueCourtApprovalRequest::create([
            'venue_cluster_id' => $data['venue_cluster_id'],
            'court_type_id'    => $data['court_type_id'],
            'name'             => $data['name'],
            'status'           => 'pending',
            'requested_by'     => $request->user()->id,
            'status_reason'    => 'Yêu cầu thêm sân con từ quản lý sân con',
        ]);

        return response()->json([
            'message' => 'Yêu cầu thêm sân con đã được gửi thành công. Vui lòng chờ Admin xét duyệt.',
            'data' => $approvalRequest->load('courtType'),
        ], 201);
    }

    public function conflicts(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::withTrashed()->findOrFail($id);
        $cluster = $court->venueCluster;

        if (! $cluster || $cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem sân con này.'], 403);
        }

        $items = $this->courtStatusConflictService->getFutureAffectedBookingItems($court);

        return response()->json([
            'data' => $this->courtStatusConflictService->buildConflictPayload($items),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::withTrashed()->findOrFail($id);
        $cluster = $court->venueCluster;

        if ($cluster && $cluster->owner_id === $request->user()->id && $court->trashed()) {
            return response()->json([
                'message' => 'Sân con đã hủy theo phụ lục/hợp đồng, không thể chỉnh sửa trạng thái hoặc cấu hình lại.',
            ], 409);
        }

        if (! $cluster || $cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa sân con này.'], 403);
        }

        $data = $request->validate([
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'layout_x' => ['nullable', 'numeric'],
            'layout_y' => ['nullable', 'numeric'],
            'layout_w' => ['nullable', 'numeric', 'min:10'],
            'layout_h' => ['nullable', 'numeric', 'min:10'],
            'layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
            'reason' => ['nullable', 'string', 'max:500'],
            'resolutions' => ['nullable', 'array'],
            'resolutions.*.booking_item_id' => ['required_with:resolutions', 'integer', 'exists:booking_items,id'],
            'resolutions.*.action' => ['required_with:resolutions', 'in:switch,cancel,cash_refund'],
            'resolutions.*.scope' => ['nullable', 'in:affected,booking_item'],
            'resolutions.*.venue_court_id' => ['nullable', 'integer', 'exists:venue_courts,id'],
        ]);

        $statusChanged = $court->status !== $data['status'];
        $isDeactivating = $statusChanged && in_array($data['status'], ['inactive', 'maintenance'], true);

        if ($isDeactivating) {
            $affectedItems = $this->courtStatusConflictService->getFutureAffectedBookingItems($court);

            if ($affectedItems->isNotEmpty()) {
                if (empty($data['resolutions'])) {
                    $statusName = $data['status'] === 'inactive' ? 'tạm ngưng' : 'bảo trì';
                    return response()->json([
                        'message' => "Sân này đang có {$affectedItems->count()} lịch đặt trong tương lai. Vui lòng chọn phương án xử lý trước khi chuyển sang trạng thái {$statusName}.",
                        'conflicts' => $this->courtStatusConflictService->buildConflictPayload($affectedItems),
                    ], 422);
                }

                $this->courtStatusConflictService->resolveConflicts(
                    $request,
                    $court,
                    $data['status'],
                    $data['reason'] ?? null,
                    $data['resolutions']
                );
            }
        }

        $fillData = collect($data)->except(['reason', 'resolutions'])->all();
        $court->update($fillData);

        return response()->json([
            'message' => 'Cập nhật sân con thành công.',
            'data' => $court->fresh('courtType'),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::withTrashed()->findOrFail($id);
        $cluster = $court->venueCluster;

        if ($cluster && $cluster->owner_id === $request->user()->id && $court->trashed()) {
            return response()->json([
                'message' => 'Sân con đã hủy theo phụ lục/hợp đồng, không thể xóa hoặc chỉnh sửa lại.',
            ], 409);
        }

        if (! $cluster || $cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa sân con này.'], 403);
        }

        $court->delete();

        return response()->json([
            'message' => 'Xóa sân con thành công.',
        ]);
    }

    public function updateLayoutBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'integer', 'exists:venue_clusters,id'],
            'courts' => ['required', 'array'],
            'courts.*.id' => ['required', 'integer', 'exists:venue_courts,id'],
            'courts.*.layout_x' => ['nullable', 'numeric'],
            'courts.*.layout_y' => ['nullable', 'numeric'],
            'courts.*.layout_w' => ['nullable', 'numeric', 'min:10'],
            'courts.*.layout_h' => ['nullable', 'numeric', 'min:10'],
            'courts.*.layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
            'layout_decorations' => ['nullable', 'array'],
            'layout_decorations.*.id' => ['required', 'string'],
            'layout_decorations.*.type' => ['required', 'string', 'in:entrance,reception,restroom,seating,parking,custom'],
            'layout_decorations.*.name' => ['required', 'string', 'max:100'],
            'layout_decorations.*.layout_x' => ['required', 'numeric'],
            'layout_decorations.*.layout_y' => ['required', 'numeric'],
            'layout_decorations.*.layout_w' => ['required', 'numeric', 'min:10'],
            'layout_decorations.*.layout_h' => ['required', 'numeric', 'min:10'],
            'layout_decorations.*.layout_rotation' => ['required', 'integer', 'min:0', 'max:359'],
        ]);

        $cluster = VenueCluster::query()->findOrFail($data['venue_cluster_id']);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa cụm sân này.'], 403);
        }

        $courtIds = collect($data['courts'])->pluck('id')->unique()->values();
        $courts = VenueCourt::query()->whereIn('id', $courtIds)->get();

        if ($courts->count() !== $courtIds->count()) {
            return response()->json([
                'message' => 'Sân con đã hủy theo phụ lục/hợp đồng, không thể sắp xếp hoặc cấu hình lại.',
            ], 409);
        }

        foreach ($courts as $court) {
            if ($court->venue_cluster_id !== $cluster->id) {
                return response()->json(['message' => 'Một số sân con không thuộc cụm sân được chỉ định.'], 400);
            }
        }

        DB::transaction(function () use ($cluster, $data) {
            foreach ($data['courts'] as $courtData) {
                VenueCourt::query()->whereKey($courtData['id'])->update([
                    'layout_x' => $courtData['layout_x'],
                    'layout_y' => $courtData['layout_y'],
                    'layout_w' => $courtData['layout_w'],
                    'layout_h' => $courtData['layout_h'],
                    'layout_rotation' => $courtData['layout_rotation'] ?? 0,
                ]);
            }

            $cluster->update([
                'layout_decorations' => $data['layout_decorations'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Cập nhật sơ đồ thành công.',
        ]);
    }
}
