<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueClusterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerVenueServiceController extends Controller
{
    /**
     * Lấy danh sách dịch vụ/sản phẩm tại sân của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem thông tin của cụm sân này.'], 403);
        }

        $services = VenueClusterService::query()
            ->with('category')
            ->where('venue_cluster_id', $clusterId)
            ->latest()
            ->get();

        return response()->json([
            'data' => $services,
        ]);
    }

    /**
     * Thêm dịch vụ/sản phẩm mới cho cụm sân.
     */
    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền quản lý dịch vụ cho cụm sân này.'], 403);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'string',
                Rule::exists('service_categories', 'id')->where('status', 'active')
            ],
            'price'       => ['required', 'numeric', 'min:0'],
            'unit'        => ['required', 'string', 'max:50'],
            'status'      => ['required', 'string', 'in:active,inactive,out_of_stock'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = VenueClusterService::create([
            'id'               => (string) Str::uuid(),
            'venue_cluster_id' => $clusterId,
            'name'             => $validated['name'],
            'category_id'      => $validated['category_id'],
            'price'            => $validated['price'],
            'unit'             => $validated['unit'],
            'status'           => $validated['status'],
            'description'      => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Thêm dịch vụ thành công.',
            'data'    => $service->load('category'),
        ], 201);
    }

    /**
     * Cập nhật dịch vụ/sản phẩm.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $service = VenueClusterService::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($service->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật dịch vụ này.'], 403);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'string',
                Rule::exists('service_categories', 'id')->where('status', 'active')
            ],
            'price'       => ['required', 'numeric', 'min:0'],
            'unit'        => ['required', 'string', 'max:50'],
            'status'      => ['required', 'string', 'in:active,inactive,out_of_stock'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Cập nhật dịch vụ thành công.',
            'data'    => $service->load('category'),
        ]);
    }

    /**
     * Xóa dịch vụ/sản phẩm.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $service = VenueClusterService::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($service->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa dịch vụ này.'], 403);
        }

        $service->delete();

        return response()->json([
            'message' => 'Xóa dịch vụ thành công.',
        ]);
    }

    /**
     * Thay đổi nhanh trạng thái hoạt động dịch vụ.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $service = VenueClusterService::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($service->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền sửa đổi dịch vụ này.'], 403);
        }

        $newStatus = $service->status === 'active' ? 'inactive' : 'active';
        $service->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Thay đổi trạng thái thành công.',
            'data'    => $service->load('category'),
        ]);
    }
}
