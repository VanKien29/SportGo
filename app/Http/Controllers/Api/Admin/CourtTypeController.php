<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CourtType::query()->with('parent');
        $user = $request->user();

        if ($request->query('active_only') || !$user || $user->role_group !== 'admin') {
            $query->where('is_active', true);
        }

        $courtTypes = $query->latest()->get();

        return response()->json(['data' => $courtTypes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:court_types,name'],
            'parent_id' => ['nullable', 'exists:court_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'player_count' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $courtType = CourtType::query()->create($data);

        return response()->json([
            'message' => 'Tạo loại sân thành công.',
            'data' => $courtType->load('parent'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $courtType = CourtType::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:court_types,name,' . $id],
            'parent_id' => ['nullable', 'exists:court_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'player_count' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $courtType->update($data);

        return response()->json([
            'message' => 'Cập nhật loại sân thành công.',
            'data' => $courtType->load('parent'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $courtType = CourtType::query()->findOrFail($id);
        $courtType->delete();

        return response()->json([
            'message' => 'Xóa loại sân thành công.',
        ]);
    }
}
