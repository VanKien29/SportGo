<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * Hiển thị danh sách banner
     * FE sẽ hiển thị: ảnh, tiêu đề, vị trí, link, thời gian hiệu lực, trạng thái, thứ tự
     */
    public function index(Request $request)
    {
        $query = Banner::with(['createdBy', 'updatedBy']);

        // Filter theo trạng thái
        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter theo vị trí
        if ($request->has('position') && $request->position) {
            $query->where('position', $request->position);
        }

        // Sắp xếp theo sort_order
        $banners = $query->orderBy('sort_order')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $banners
        ]);
    }

    /**
     * Tạo banner mới
     * - Upload ảnh
     * - Nhập link
     * - Chọn vị trí
     * - Chọn thời gian bắt đầu/kết thúc
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
            'link_url' => 'nullable|url:http,https|max:500',
            'position' => 'required|in:homepage_top,homepage_middle,homepage_bottom,category_page,venue_detail',
            'starts_at' => 'required|date|after_or_equal:today',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $imagePath = $request->file('image')->store('banners/' . date('Y/m'), 'public');

            // Lấy sort_order mặc định nếu không cung cấp
            $sortOrder = $request->sort_order ?? (Banner::max('sort_order') ?? 0) + 1;

            $banner = Banner::create([
                'title' => $request->title,
                'image_path' => $imagePath,
                'link_url' => $request->link_url,
                'position' => $request->position,
                'sort_order' => $sortOrder,
                'is_active' => $request->boolean('is_active'),
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Tạo banner thành công.',
                'data' => $banner->load(['createdBy', 'updatedBy'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tạo banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật banner
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'link_url' => 'nullable|url:http,https|max:500',
            'position' => 'sometimes|required|in:homepage_top,homepage_middle,homepage_bottom,category_page,venue_detail',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|required|date|after:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Xử lý upload ảnh mới nếu có
            if ($request->hasFile('image')) {
                // Xóa ảnh cũ
                if ($banner->image_path) {
                    Storage::disk('public')->delete($banner->image_path);
                }

                $file = $request->file('image');
                $banner->image_path = $file->store('banners/' . date('Y/m'), 'public');
            }

            // Cập nhật các trường khác
            $banner->fill($request->only(['title', 'link_url', 'position', 'starts_at', 'ends_at', 'sort_order']));

            // Xử lý is_active riêng vì nó là boolean
            $banner->is_active = $request->boolean('is_active');

            $banner->updated_by = Auth::id();
            $banner->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật banner thành công.',
                'data' => $banner->load(['createdBy', 'updatedBy'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa banner
     */
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        try {
            // Xóa ảnh
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }

            $banner->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Xóa banner thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi xóa banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sắp xếp thứ tự banner
     * Nhận mảng các banner ID theo thứ tự mong muốn
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banner_ids' => 'required|array',
            'banner_ids.*' => 'exists:banners,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            foreach ($request->banner_ids as $index => $bannerId) {
                Banner::where('id', $bannerId)->update(['sort_order' => $index]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sắp xếp banner thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi sắp xếp banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách banner active cho client
     * Chỉ trả banner active còn hạn
     */
    public function getActiveBanners($position = null)
    {
        $today = now()->toDateString();

        // So sánh theo ngày để banner hiển thị cả ngày trong khoảng thời hạn
        $query = Banner::where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today);

        if ($position) {
            $query->where('position', $position);
        }

        $banners = $query->orderBy('sort_order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $banners
        ]);
    }
}
