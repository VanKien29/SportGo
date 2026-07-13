<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminServiceCategoryController extends Controller
{
    /**
     * Lấy danh sách danh mục dịch vụ hệ thống.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceCategory::query();

        // Nếu chỉ lấy các danh mục đang hoạt động (active)
        $user = $request->user();
        if ($request->query('active_only') || !$user || $user->role_group !== 'admin') {
            $query->where('status', 'active');
        }

        $categories = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Thêm danh mục dịch vụ mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:service_categories,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = ServiceCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tạo danh mục dịch vụ thành công.',
            'data' => $category
        ], 201);
    }

    /**
     * Chi tiết danh mục dịch vụ.
     */
    public function show(string $id): JsonResponse
    {
        $category = ServiceCategory::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Cập nhật thông tin danh mục dịch vụ.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = ServiceCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_categories', 'name')->ignore($category->id)
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công.',
            'data' => $category
        ]);
    }

    /**
     * Xóa danh mục dịch vụ.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = ServiceCategory::findOrFail($id);

        // Kiểm tra xem danh mục có đang chứa dịch vụ nào không
        if ($category->services()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa danh mục này vì đang có sản phẩm/dịch vụ tại các sân liên kết.'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa danh mục thành công.'
        ]);
    }

    /**
     * Thay đổi nhanh trạng thái hoạt động của danh mục.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $category = ServiceCategory::findOrFail($id);
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Thay đổi trạng thái danh mục thành công.',
            'data' => $category
        ]);
    }
}
