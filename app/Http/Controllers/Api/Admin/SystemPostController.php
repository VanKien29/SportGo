<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class SystemPostController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemPost::with('author:id,full_name,username,avatar_url')->latest();

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->keyword . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:500',
            'category' => 'required|string|in:news,announcement,guide,event',
            'content' => 'required|string',
            'status' => 'required|string|in:draft,published,hidden',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $post = new SystemPost();
        $post->author_id = $request->user()->id;
        $post->title = $validated['title'];
        $post->slug = Str::slug($validated['title']) . '-' . uniqid();
        $post->short_description = $validated['short_description'];
        $post->category = $validated['category'];
        $post->content = $validated['content'];
        $post->status = $validated['status'];
        
        if ($post->status === 'published') {
            $post->published_at = now();
        }

        if ($request->hasFile('thumbnail')) {
            $post->thumbnail_path = $this->uploadImage($request->file('thumbnail'), 'system-posts');
        }

        $post->save();

        return response()->json(['message' => 'Tạo bài viết thành công.', 'data' => $post], 201);
    }

    public function show(string $id)
    {
        $post = SystemPost::findOrFail($id);
        return response()->json(['data' => $post]);
    }

    public function update(Request $request, string $id)
    {
        $post = SystemPost::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:500',
            'category' => 'required|string|in:news,announcement,guide,event',
            'content' => 'required|string',
            'status' => 'required|string|in:draft,published,hidden',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $oldStatus = $post->status;

        $post->title = $validated['title'];
        $post->short_description = $validated['short_description'];
        $post->category = $validated['category'];
        $post->content = $validated['content'];
        $post->status = $validated['status'];
        
        if ($oldStatus !== 'published' && $post->status === 'published') {
            $post->published_at = now();
        }

        if ($request->hasFile('thumbnail')) {
            if ($post->thumbnail_path && Storage::disk('public')->exists(str_replace('/storage/', '', $post->thumbnail_path))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $post->thumbnail_path));
            }
            $post->thumbnail_path = $this->uploadImage($request->file('thumbnail'), 'system-posts');
        }

        $post->save();

        return response()->json(['message' => 'Cập nhật bài viết thành công.', 'data' => $post]);
    }

    public function destroy(string $id)
    {
        $post = SystemPost::findOrFail($id);
        
        if ($post->thumbnail_path && Storage::disk('public')->exists(str_replace('/storage/', '', $post->thumbnail_path))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $post->thumbnail_path));
        }

        $post->delete();

        return response()->json(['message' => 'Xóa bài viết thành công.']);
    }

    public function uploadEditorImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $url = $this->uploadImage($request->file('image'), 'editor/system-posts');
            return response()->json(['url' => $url]);
        }

        return response()->json(['message' => 'Tải lên ảnh thất bại.'], 400);
    }

    private function uploadImage($file, $directory)
    {
        $manager = ImageManager::usingDriver(new Driver());
        $image = $manager->decodePath($file->getPathname());
        
        $filename = uniqid('img_', true) . '.webp';
        $path = $directory . '/' . $filename;
        
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }
        
        $image->save(storage_path('app/public/' . $path), 80);
        
        return '/storage/' . $path;
    }
}
