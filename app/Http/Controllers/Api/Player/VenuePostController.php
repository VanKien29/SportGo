<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenuePostCommentRequest;
use App\Models\VenuePost;
use App\Http\Requests\StoreVenuePostRequest;
use App\Http\Requests\UpdateVenuePostRequest;
use App\Services\VenuePostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VenuePostController extends Controller
{
    public function __construct(private VenuePostService $venuePostService)
    {
    }

    public function index(Request $request)
    {
        $posts = VenuePost::with(['media', 'author:id,full_name,username', 'venueCluster:id,name', 'hashtags'])
            ->where('status', 'published')
            ->when($request->author_id, fn ($q) => $q->where('author_id', $request->author_id))
            ->when($request->venue_cluster_id, fn ($q) => $q->where('venue_cluster_id', $request->venue_cluster_id))
            ->when($request->post_type, fn ($q) => $q->where('post_type', $request->post_type))
            ->when($request->category, function ($q) use ($request) {
                $q->whereHas('hashtags', function ($q2) use ($request) {
                    $q2->where('name', $request->category);
                });
            })
            ->when($request->keyword, fn ($q) => $q->where('title', 'like', "%{$request->keyword}%"))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($posts);
    }

    public function show(string $slug)
    {
        $post = VenuePost::with([
            'media', 
            'author:id,full_name,username', 
            'venueCluster:id,name', 
            'hashtags', 
            'likers:id,full_name,username,avatar_url',
            'topLevelComments' => function ($query) {
                $query->with(['user:id,full_name,username,avatar_url', 'replies.user:id,full_name,username,avatar_url']);
            }
        ])
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        if ($post->status !== 'published') {
            abort(403, 'Bài viết không tồn tại hoặc chưa được xuất bản.');
        }

        $post->increment('view_count');

        return response()->json(['data' => $post]);
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

        $this->venuePostService->deletePost($post);

        return response()->json(['message' => 'Bài viết đã được xóa.']);
    }

    public function comment(StoreVenuePostCommentRequest $request, string $id)
    {
        $post = VenuePost::where('status', 'published')->findOrFail($id);
        
        $commentId = DB::table('venue_post_comments')->insertGetId([
            'venue_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => strip_tags($request->input('content')),
            'parent_id' => $request->input('parent_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post->increment('comment_count');

        return response()->json(['message' => 'Đã gửi bình luận.', 'data' => ['id' => $commentId]]);
    }

    public function toggleLike(Request $request, string $id)
    {
        $post = VenuePost::where('status', 'published')->findOrFail($id);
        $userId = $request->user()->id;

        $like = DB::table('venue_post_likes')->where('post_id', $post->id)->where('user_id', $userId)->first();

        if ($like) {
            DB::table('venue_post_likes')->where('post_id', $post->id)->where('user_id', $userId)->delete();
            $post->decrement('like_count');
            return response()->json(['message' => 'Đã bỏ thích.']);
        } else {
            DB::table('venue_post_likes')->insert([
                'post_id' => $post->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $post->increment('like_count');
            return response()->json(['message' => 'Đã thích bài viết.']);
        }
    }
}
