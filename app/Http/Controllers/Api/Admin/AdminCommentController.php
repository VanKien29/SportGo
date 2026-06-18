<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentDetailResource;
use App\Models\CommunityPostComment;
use Illuminate\Http\JsonResponse;

class AdminCommentController extends Controller
{
    /**
     * GET /admin/comments/{comment}
     * Chi tiết đầy đủ 1 comment phục vụ admin xem.
     */
    public function show(string $comment): JsonResponse
    {
        $commentModel = CommunityPostComment::findOrFail($comment);
        
        $postModel = \App\Models\CommunityPost::query()
            ->with([
                'author:id,username,full_name,avatar_url',
                'media',
            ])
            ->findOrFail($commentModel->post_id);

        $comments = $postModel->comments()
            ->with(['user:id,username,full_name,avatar_url', 'media'])
            ->withCount('replies')
            ->whereNull('parent_id') // Top-level comments
            ->orderBy('created_at', 'asc') // Chronological order
            ->get();

        return response()->json([
            'data' => [
                'target_comment_id' => $commentModel->id,
                'post' => [
                    'id' => $postModel->id,
                    'content' => $postModel->content,
                    'status' => $postModel->status,
                    'author_name' => $postModel->author?->full_name ?: $postModel->author?->username,
                    'media' => $postModel->media->map(fn($m) => [
                        'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                    ]),
                    'created_at' => $postModel->created_at,
                ],
                'comments' => $comments->map(fn($c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'status' => $c->status,
                    'user_name' => $c->user?->full_name ?: $c->user?->username,
                    'user_avatar' => $c->user?->avatar_url,
                    'created_at' => $c->created_at,
                ]),
            ],
        ]);
    }

    /**
     * POST /admin/comments/{comment}/action
     * Xử lý ẩn hoặc xóa comment
     */
    public function processAction(\Illuminate\Http\Request $request, string $comment): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:hide,delete,unhide',
        ]);

        $commentModel = CommunityPostComment::findOrFail($comment);

        // Audit logging could be added here if there's a generic audit mechanism

        if ($validated['action'] === 'delete') {
            $commentModel->delete();
            return response()->json(['message' => 'Đã xóa bình luận thành công.']);
        }

        if ($validated['action'] === 'hide') {
            $commentModel->update([
                'status' => 'hidden',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            return response()->json(['message' => 'Đã ẩn bình luận thành công.']);
        }

        if ($validated['action'] === 'unhide') {
            $commentModel->update([
                'status' => 'visible',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            return response()->json(['message' => 'Đã mở ẩn bình luận thành công.']);
        }

        return response()->json(['message' => 'Hành động không hợp lệ.'], 400);
    }
}
