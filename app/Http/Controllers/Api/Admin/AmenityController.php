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
            $query->where('status', 'active');
        } else {
            // Admin can see all, eager load creator and reviewer
            $query->with(['createdBy:id,full_name,username', 'reviewedBy:id,full_name,username']);
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
            'description' => 'nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'pending_review', 'rejected', 'cancelled'])],
        ]);

        $status = $validated['status'] ?? 'active';

        $amenity = Amenity::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'created_by' => $request->user()?->id,
            'reviewed_by' => $status === 'active' ? $request->user()?->id : null,
            'reviewed_at' => $status === 'active' ? now() : null,
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
        $amenity = Amenity::with(['createdBy:id,full_name,username', 'reviewedBy:id,full_name,username'])->findOrFail($id);
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
            'description' => 'nullable|string|max:1000',
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'pending_review', 'rejected', 'cancelled'])],
            'status_reason' => 'nullable|string|max:2000',
        ]);

        $status = $validated['status'] ?? $amenity->status;

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $amenity->description,
            'status' => $status,
            'status_reason' => $validated['status_reason'] ?? $amenity->status_reason,
        ];

        if ($status !== $amenity->status) {
            if ($status === 'active') {
                $updateData['reviewed_by'] = $request->user()?->id;
                $updateData['reviewed_at'] = now();
            }
        }

        $amenity->update($updateData);

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

    /**
     * Admin review / approve or reject requested amenity.
     */
    public function review(Request $request, string $id)
    {
        $amenity = Amenity::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'rejected'])],
            'status_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ], [
            'status_reason.required_if' => 'Vui lòng cung cấp lý do từ chối.'
        ]);

        $amenity->update([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'status_reason' => $validated['status_reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Xử lý yêu cầu tiện ích thành công.',
            'data' => $amenity
        ]);
    }

    /**
     * Owner request a new amenity to be added to the catalog.
     */
    public function requestAmenity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:amenities,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $amenity = Amenity::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending_review',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu thêm tiện ích thành công. Vui lòng chờ admin duyệt.',
            'data' => $amenity
        ], 201);
    }
}
