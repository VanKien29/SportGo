<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenuePostRequest;
use App\Http\Requests\UpdateVenuePostRequest;
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
        $user = $request->user();
        Gate::authorize('viewAny', VenuePost::class);

        $query = VenuePost::with(['media', 'venueCluster', 'hashtags'])
            ->where('author_id', $user->id);

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->keyword . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('post_type')) {
            $query->where('post_type', $request->post_type);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['created_at', 'view_count', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $posts = $query->paginate($request->integer('per_page', 15));

        return response()->json($posts);
    }

    public function store(StoreVenuePostRequest $request)
    {
        Gate::authorize('create', VenuePost::class);

        try {
            $post = $this->venuePostService->createPost(
                $request->validated(),
                $request->user(),
                $request->file('thumbnail')
            );

            return response()->json(['message' => 'Bài viết đã được tạo thành công.', 'data' => $post->load(['media', 'hashtags'])], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['status' => [$e->getMessage()]]], 422);
        }
    }

    public function show(string $id)
    {
        $post = VenuePost::with(['media', 'venueCluster', 'hashtags'])->findOrFail($id);
        Gate::authorize('view', $post);

        return response()->json(['data' => $post]);
    }

    public function update(UpdateVenuePostRequest $request, string $id)
    {
        $post = VenuePost::findOrFail($id);
        Gate::authorize('update', $post);

        try {
            $post = $this->venuePostService->updatePost(
                $post,
                $request->validated(),
                $request->user(),
                $request->file('thumbnail')
            );

            return response()->json(['message' => 'Bài viết đã được cập nhật.', 'data' => $post->load(['media', 'hashtags'])]);
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

    public function uploadEditorImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('editor', 'public');
            return response()->json([
                'url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['message' => 'Tải lên ảnh thất bại.'], 400);
    }
}
