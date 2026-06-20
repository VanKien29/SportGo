<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnerAffiliateProductController extends Controller
{
    /**
     * Lấy danh sách sản phẩm tiếp thị liên kết của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem thông tin của cụm sân này.'], 403);
        }

        $products = AffiliateProduct::query()
            ->where('venue_cluster_id', $clusterId)
            ->latest()
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Thêm sản phẩm tiếp thị liên kết mới cho cụm sân.
     */
    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::query()->findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền quản lý sản phẩm cho cụm sân này.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5MB
            'price' => ['nullable', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_url' => ['required', 'url', 'max:2000'],
            'platform_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = AffiliateProduct::create([
            'id' => (string) Str::uuid(),
            'venue_cluster_id' => $clusterId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'price' => $validated['price'] ?? null,
            'original_price' => $validated['original_price'] ?? null,
            'affiliate_url' => $validated['affiliate_url'],
            'platform_name' => $validated['platform_name'] ?? null,
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'click_count' => 0,
        ]);

        return response()->json([
            'message' => 'Thêm sản phẩm tiếp thị liên kết thành công.',
            'data' => $product,
        ], 201);
    }

    /**
     * Cập nhật sản phẩm tiếp thị liên kết.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = AffiliateProduct::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($product->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật sản phẩm này.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5MB
            'price' => ['nullable', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_url' => ['required', 'url', 'max:2000'],
            'platform_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'price' => $validated['price'] ?? null,
            'original_price' => $validated['original_price'] ?? null,
            'affiliate_url' => $validated['affiliate_url'],
            'platform_name' => $validated['platform_name'] ?? null,
            'is_active' => filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json([
            'message' => 'Cập nhật sản phẩm tiếp thị liên kết thành công.',
            'data' => $product,
        ]);
    }

    /**
     * Xóa sản phẩm tiếp thị liên kết.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $product = AffiliateProduct::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($product->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa sản phẩm này.'], 403);
        }

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json([
            'message' => 'Xóa sản phẩm thành công.',
        ]);
    }

    /**
     * Bật/Tắt trạng thái hoạt động của sản phẩm.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $product = AffiliateProduct::query()->findOrFail($id);
        $cluster = VenueCluster::query()->findOrFail($product->venue_cluster_id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền thay đổi trạng thái sản phẩm này.'], 403);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'message' => 'Thay đổi trạng thái hoạt động sản phẩm thành công.',
            'data' => $product,
        ]);
    }
}
