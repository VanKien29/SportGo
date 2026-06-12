<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VenueCourtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'maintenance'])],
        ]);

        $cluster = VenueCluster::query()->findOrFail($request->query('venue_cluster_id'));

        if ($cluster->owner_id !== $request->user()->id) {
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'court_type_id' => ['required', 'integer', 'exists:court_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $cluster = VenueCluster::query()->findOrFail($data['venue_cluster_id']);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền thêm sân con vào cụm sân này.'], 403);
        }

        $court = VenueCourt::query()->create([
            'venue_cluster_id' => $data['venue_cluster_id'],
            'court_type_id' => $data['court_type_id'],
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Thêm sân con thành công.',
            'data' => $court->load('courtType'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::query()->findOrFail($id);
        $cluster = $court->venueCluster;

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa sân con này.'], 403);
        }

        $data = $request->validate([
            'court_type_id' => ['nullable', 'integer', 'exists:court_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $court->update($data);

        return response()->json([
            'message' => 'Cập nhật sân con thành công.',
            'data' => $court->load('courtType'),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $court = VenueCourt::query()->findOrFail($id);
        $cluster = $court->venueCluster;

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa sân con này.'], 403);
        }

        $court->delete();

        return response()->json([
            'message' => 'Xóa sân con thành công.',
        ]);
    }
}
