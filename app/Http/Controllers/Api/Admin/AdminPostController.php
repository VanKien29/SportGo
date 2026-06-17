<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostDetailResource;
use App\Models\CommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPostController extends Controller
{
    /**
     * GET /admin/posts/{post}
     * Chi tiết đầy đủ 1 post phục vụ admin xem.
     */
    public function show(Request $request, string $post): JsonResponse
    {
        $postModel = CommunityPost::query()
            ->with([
                'author:id,username,full_name,avatar_url',
                'media',
            ])
            ->findOrFail($post);

        // Paginate comments riêng
        $comments = $postModel->comments()
            ->with(['user:id,username,full_name,avatar_url', 'media'])
            ->withCount('replies')
            ->whereNull('parent_id') // Chỉ lấy comment gốc, không lấy reply
            ->orderByDesc('created_at')
            ->paginate(20);

        $resource = new PostDetailResource($postModel);
        $resourceData = $resource->toArray($request);

        // Override comments bằng phiên bản paginated
        $resourceData['comments'] = $comments->items()
            ? collect($comments->items())->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'status' => $comment->status,
                'user_name' => $comment->user?->full_name ?: $comment->user?->username,
                'user_avatar' => $comment->user?->avatar_url,
                'replies_count' => $comment->replies_count ?? 0,
                'media' => $comment->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                ]),
                'created_at' => $comment->created_at,
            ])
            : [];

        return response()->json([
            'data' => $resourceData,
            'comments_meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }
}
