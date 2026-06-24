<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VenuePost;
use App\Services\VenuePostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VenuePostController extends Controller
{
    public function __construct(private VenuePostService $venuePostService)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', VenuePost::class);

        $posts = VenuePost::with(['media', 'author:id,full_name,username', 'venueCluster:id,name', 'hashtags'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->keyword, fn ($q) => $q->where('title', 'like', "%{$request->keyword}%"))
            ->when($request->post_type, fn ($q) => $q->where('post_type', $request->post_type))
            ->when($request->author, function ($q) use ($request) {
                $author = '%' . $request->author . '%';
                $q->whereHas('author', function ($aq) use ($author) {
                    $aq->where('username', 'like', $author)
                       ->orWhere('full_name', 'like', $author);
                });
            });

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['created_at', 'view_count', 'updated_at'])) {
            $posts->orderBy($sortBy, $sortOrder);
        } else {
            $posts->orderBy('created_at', 'desc');
        }

        $postsData = $posts->paginate($request->integer('per_page', 15));

        return response()->json($postsData);
    }

    public function show(string $id)
    {
        $post = VenuePost::with(['media', 'author', 'venueCluster', 'hashtags'])->findOrFail($id);
        Gate::authorize('view', $post);

        return response()->json(['data' => $post]);
    }

    public function approve(Request $request, string $id)
    {
        $request->validate(['status' => 'required|in:published,rejected,hidden', 'reason' => 'nullable|string']);
        
        $post = VenuePost::findOrFail($id);
        Gate::authorize('approve', $post);

        try {
            $post = $this->venuePostService->changeStatus($post, $request->status, $request->user(), $request->reason);
            return response()->json(['message' => "Bài viết đã được chuyển trạng thái thành {$request->status}.", 'data' => $post]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['status' => [$e->getMessage()]]], 422);
        }
    }

    public function destroy(Request $request, string $id)
    {
        $post = VenuePost::findOrFail($id);
        Gate::authorize('delete', $post);

        try {
            $this->venuePostService->deletePost($post, $request->user());
            return response()->json(['message' => 'Bài viết đã được xóa.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['status' => [$e->getMessage()]]], 422);
        }
    }

    public function restore(Request $request, string $id)
    {
        $post = VenuePost::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $post);

        try {
            $this->venuePostService->restorePost($post, $request->user());
            return response()->json(['message' => 'Bài viết đã được khôi phục.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['status' => [$e->getMessage()]]], 422);
        }
    }
}
