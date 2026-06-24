<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenuePostCommentRequest;
use App\Models\VenuePost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenuePostController extends Controller
{
    public function index(Request $request)
    {
        $posts = VenuePost::with(['media', 'author:id,full_name,username', 'venueCluster:id,name'])
            ->where('status', 'published')
            ->when($request->venue_cluster_id, fn ($q) => $q->where('venue_cluster_id', $request->venue_cluster_id))
            ->when($request->post_type, fn ($q) => $q->where('post_type', $request->post_type))
            ->when($request->keyword, fn ($q) => $q->where('title', 'like', "%{$request->keyword}%"))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($posts);
    }

    public function show(string $slug)
    {
        $post = VenuePost::with(['media', 'author:id,full_name,username', 'venueCluster:id,name', 'hashtags', 'comments.user:id,full_name,username'])
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();

        if ($post->status !== 'published') {
            abort(403, 'Bài viết không tồn tại hoặc chưa được xuất bản.');
        }

        $post->increment('view_count');

        return response()->json(['data' => $post]);
    }

    public function comment(StoreVenuePostCommentRequest $request, string $id)
    {
        $post = VenuePost::where('status', 'published')->findOrFail($id);
        
        $commentId = (string) \Illuminate\Support\Str::uuid();
        DB::table('venue_post_comments')->insert([
            'id' => $commentId,
            'venue_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => strip_tags($request->content),
            'parent_id' => $request->parent_id,
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
                'id' => (string) \Illuminate\Support\Str::uuid(),
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
