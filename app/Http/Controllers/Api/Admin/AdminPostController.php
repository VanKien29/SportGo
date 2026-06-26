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
            ->with(['user:id,username,full_name,avatar_url', 'media', 'replies.user:id,username,full_name,avatar_url', 'replies.media'])
            ->withCount('replies')
            ->whereNull('parent_id') // Chỉ lấy comment gốc, không lấy reply
            ->orderByDesc('created_at')
            ->paginate(20);

        $resource = new PostDetailResource($postModel);
        $resourceData = $resource->toArray($request);

        // Override comments bằng phiên bản paginated
        $resourceData['comments'] = $comments->items()
            ? collect($comments->items())->map(function ($comment) {
                $reportsCount = \App\Models\Report::where('reportable_type', \App\Models\CommunityPostComment::class)
                    ->where('reportable_id', $comment->id)
                    ->count();

                $resolvedReportsCount = \App\Models\Report::where('reportable_type', \App\Models\CommunityPostComment::class)
                    ->where('reportable_id', $comment->id)
                    ->where('status', 'resolved')
                    ->count();

                $pendingReportsCount = \App\Models\Report::where('reportable_type', \App\Models\CommunityPostComment::class)
                    ->where('reportable_id', $comment->id)
                    ->where('status', 'pending')
                    ->count();

                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'status' => $comment->status,
                    'user_name' => $comment->user?->full_name ?: $comment->user?->username,
                    'user_avatar' => $comment->user?->avatar_url,
                    'replies_count' => $comment->replies_count ?? 0,
                    'reports_count' => $reportsCount,
                    'resolved_reports_count' => $resolvedReportsCount,
                    'pending_reports_count' => $pendingReportsCount,
                    'is_reported' => $reportsCount > 0,
                    'needs_attention' => $pendingReportsCount > 0,
                    'threshold_reached' => $resolvedReportsCount >= 3,
                    'near_threshold' => $resolvedReportsCount >= 2,
                    'media' => $comment->media->map(fn ($m) => [
                        'id' => $m->id,
                        'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                    ]),
                    'replies' => $comment->replies->map(fn ($reply) => [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'status' => $reply->status,
                        'user_name' => $reply->user?->full_name ?: $reply->user?->username,
                        'user_avatar' => $reply->user?->avatar_url,
                        'created_at' => $reply->created_at,
                    ])->values()->all(),
                    'created_at' => $comment->created_at,
                ];
            })
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

    /**
     * POST /admin/posts/{post}/action
     * Xử lý ẩn hoặc xóa bài đăng
     */
    public function processAction(Request $request, string $post): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:hide,delete,unhide',
        ]);

        $postModel = CommunityPost::findOrFail($post);

        // Audit logging could be added here if there's a generic audit mechanism

        if ($validated['action'] === 'delete') {
            \App\Models\Report::resolvePendingReportsForTarget($postModel, 'content_deleted', $request->user());
            $postModel->delete();
            return response()->json(['message' => 'Đã xóa bài đăng thành công.']);
        }

        if ($validated['action'] === 'hide') {
            $postModel->update([
                'status' => 'hidden',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            \App\Models\Report::resolvePendingReportsForTarget($postModel, 'content_hidden', $request->user());
            return response()->json(['message' => 'Đã ẩn bài đăng thành công.']);
        }

        if ($validated['action'] === 'unhide') {
            $postModel->update([
                'status' => 'published',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            return response()->json(['message' => 'Đã mở ẩn bài đăng thành công.']);
        }

        return response()->json(['message' => 'Hành động không hợp lệ.'], 400);
    }

    /**
     * GET /admin/posts/{post}/likes
     * Lấy danh sách những người đã like bài viết
     */
    public function likes(Request $request, string $post): JsonResponse
    {
        $postModel = CommunityPost::findOrFail($post);

        $likes = $postModel->likes()
            ->with('user:id,username,full_name,avatar_url')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($likes->items())->map(function ($like) {
                return [
                    'id' => $like->id,
                    'user_id' => $like->user_id,
                    'user_name' => $like->user?->full_name ?: $like->user?->username,
                    'user_avatar' => $like->user?->avatar_url,
                    'created_at' => $like->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $likes->currentPage(),
                'last_page' => $likes->lastPage(),
                'per_page' => $likes->perPage(),
                'total' => $likes->total(),
            ],
        ]);
    }
}
