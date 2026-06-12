<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VenueCourtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
        ]);

        $cluster = VenueCluster::query()->findOrFail($request->query('venue_cluster_id'));

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem sân con của cụm sân này.'], 403);
        }

        $courts = VenueCourt::query()
            ->with(['courtType'])
            ->where('venue_cluster_id', $cluster->id)
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

        $court = VenueCourt::query()->create([
            'venue_cluster_id' => $data['venue_cluster_id'],
            'court_type_id' => $data['court_type_id'],
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => 'active',
            'layout_x' => $data['layout_x'] ?? null,
            'layout_y' => $data['layout_y'] ?? null,
            'layout_w' => $data['layout_w'] ?? null,
            'layout_h' => $data['layout_h'] ?? null,
            'layout_rotation' => $data['layout_rotation'] ?? 0,
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
            'layout_x' => ['nullable', 'numeric'],
            'layout_y' => ['nullable', 'numeric'],
            'layout_w' => ['nullable', 'numeric', 'min:10'],
            'layout_h' => ['nullable', 'numeric', 'min:10'],
            'layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
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

    public function updateLayoutBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'courts' => ['required', 'array'],
            'courts.*.id' => ['required', 'uuid', 'exists:venue_courts,id'],
            'courts.*.layout_x' => ['nullable', 'numeric'],
            'courts.*.layout_y' => ['nullable', 'numeric'],
            'courts.*.layout_w' => ['nullable', 'numeric', 'min:10'],
            'courts.*.layout_h' => ['nullable', 'numeric', 'min:10'],
            'courts.*.layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
        ]);

        $courtIds = collect($data['courts'])->pluck('id');
        $courts = VenueCourt::query()->with('venueCluster')->whereIn('id', $courtIds)->get();

        foreach ($courts as $court) {
            if ($court->venueCluster->owner_id !== $request->user()->id) {
                return response()->json(['message' => 'Bạn không có quyền chỉnh sửa một số sân con trong danh sách.'], 403);
            }
        }

        DB::transaction(function () use ($data) {
            foreach ($data['courts'] as $courtData) {
                VenueCourt::query()->whereKey($courtData['id'])->update([
                    'layout_x' => $courtData['layout_x'],
                    'layout_y' => $courtData['layout_y'],
                    'layout_w' => $courtData['layout_w'],
                    'layout_h' => $courtData['layout_h'],
                    'layout_rotation' => $courtData['layout_rotation'] ?? 0,
                ]);
            }
        });

        return response()->json([
            'message' => 'Cập nhật sơ đồ thành công.',
        ]);
    }
}
