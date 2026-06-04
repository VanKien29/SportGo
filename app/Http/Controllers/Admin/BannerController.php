<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct()
    {
        // Áp dụng Middleware phân quyền admin (Được tạo ở mục 2 bên dưới)
        $this->middleware('permission:banner.view')->only(['index']);
        $this->middleware('permission:banner.create')->only(['create', 'store']);
        $this->middleware('permission:banner.update')->only(['edit', 'update']);
        $this->middleware('permission:banner.delete')->only(['destroy']);
    }

    /**
     * Danh sách banner
     */
    public function index(Request $request)
    {
        $query = Banner::query();

        // Filter theo trạng thái
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', (bool) $request->is_active);
        }

        // Filter theo vị trí
        if ($request->has('position') && $request->position) {
            $query->where('position', $request->position);
        }

        $banners = $query->orderBy('sort_order')->paginate(15);
        $positions = [
            'homepage_top' => 'Trang chủ - Phía trên',
            'homepage_middle' => 'Trang chủ - Giữa',
            'homepage_bottom' => 'Trang chủ - Phía dưới',
            'category_page' => 'Trang danh mục',
            'venue_detail' => 'Chi tiết sân',
        ];

        return view('admin.banners.index', compact('banners', 'positions'));
    }

    /**
     * Form tạo banner
     */
    public function create()
    {
        $positions = [
            'homepage_top' => 'Trang chủ - Phía trên',
            'homepage_middle' => 'Trang chủ - Giữa',
            'homepage_bottom' => 'Trang chủ - Phía dưới',
            'category_page' => 'Trang danh mục',
            'venue_detail' => 'Chi tiết sân',
        ];

        return view('admin.banners.create', compact('positions'));
    }

    /**
     * Lưu banner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            'link_url' => 'nullable|url|max:500',
            'position' => 'required|in:homepage_top,homepage_middle,homepage_bottom,category_page,venue_detail',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Upload ảnh
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners/' . date('Y/m'), 'public');
        }

        unset($validated['image']);
        $validated['image_path'] = $imagePath;
        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $validated['sort_order'] = $validated['sort_order'] ?? (Banner::max('sort_order') ?? 0) + 1;

        Banner::create($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Tạo banner thành công!');
    }

    /**
     * Form chỉnh sửa banner
     */
    public function edit(Banner $banner)
    {
        $positions = [
            'homepage_top' => 'Trang chủ - Phía trên',
            'homepage_middle' => 'Trang chủ - Giữa',
            'homepage_bottom' => 'Trang chủ - Phía dưới',
            'category_page' => 'Trang danh mục',
            'venue_detail' => 'Chi tiết sân',
        ];

        return view('admin.banners.edit', compact('banner', 'positions'));
    }

    /**
     * Cập nhật banner
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'link_url' => 'nullable|url|max:500',
            'position' => 'required|in:homepage_top,homepage_middle,homepage_bottom,category_page,venue_detail',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Upload ảnh mới nếu có
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('banners/' . date('Y/m'), 'public');
        }

        unset($validated['image']);
        $validated['updated_by'] = Auth::id();
        $validated['is_active'] = $request->has('is_active');
        $banner->update($validated);

        return redirect()->route('admin.banners.index')->with('success', 'Cập nhật banner thành công!');
    }

    /**
     * Xóa banner
     */
    public function destroy(Banner $banner)
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Xóa banner thành công!');
    }
}
