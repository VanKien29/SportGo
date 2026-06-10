<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmenityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Amenity::query();

        // Nếu có tham số active_only hoặc user hiện tại không phải admin
        // thì chỉ trả về những tiện ích đang hoạt động
        $user = $request->user();
        if ($request->query('active_only') || !$user || $user->role_group !== 'admin') {
            $query->where('is_active', true);
        }

        $amenities = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $amenities
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:amenities,name',
            'is_active' => 'sometimes|boolean',
        ]);

        $amenity = Amenity::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo tiện ích thành công.',
            'data' => $amenity
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $amenity = Amenity::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $amenity
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $amenity = Amenity::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('amenities', 'name')->ignore($amenity->id),
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        $amenity->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? $amenity->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật tiện ích thành công.',
            'data' => $amenity
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $amenity = Amenity::findOrFail($id);
        $amenity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa tiện ích thành công.'
        ]);
    }
}
