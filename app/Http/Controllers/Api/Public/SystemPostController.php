<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\SystemPost;
use Illuminate\Http\Request;

class SystemPostController extends Controller
{
    /**
     * Display a listing of published system posts.
     */
    public function index(Request $request)
    {
        $categoryAliases = [
            'announcement' => ['announcement', 'Thông báo'],
            'guide' => ['guide', 'Hướng dẫn'],
            'news' => ['news', 'Tin tức'],
            'event' => ['event', 'Sự kiện'],
        ];
        $requestedCategory = (string) $request->category;
        $categoryValues = $categoryAliases[$requestedCategory] ?? ($requestedCategory !== '' ? [$requestedCategory] : []);

        $posts = SystemPost::query()
            ->select(['id', 'title', 'slug', 'short_description', 'category', 'thumbnail_path', 'published_at', 'view_count'])
            ->where('status', 'published')
            ->when($request->keyword, fn ($query) => $query->where('title', 'like', "%{$request->keyword}%"))
            ->when($categoryValues, fn ($query) => $query->whereIn('category', $categoryValues))
            ->when($request->user_id, fn ($query) => $query->where('author_id', $request->user_id))
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($posts);
    }

    /**
     * Display the specified system post.
     */
    public function show(string $slug)
    {
        $post = SystemPost::query()
            ->with(['author:id,full_name,username,avatar_url'])
            ->where('status', 'published')
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('id', $slug))
            ->firstOrFail();

        $post->increment('view_count');

        return response()->json(['data' => $post]);
    }
}
