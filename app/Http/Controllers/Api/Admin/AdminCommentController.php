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
        $commentModel = CommunityPostComment::query()
            ->with([
                'user:id,username,full_name,avatar_url',
                'post:id,content,status',
                'post.author:id,username,full_name',
                'replies' => function ($query) {
                    $query->with('user:id,username,full_name,avatar_url')
                        ->orderBy('created_at')
                        ->limit(20);
                },
            ])
            ->withCount('replies')
            ->findOrFail($comment);

        return response()->json([
            'data' => new CommentDetailResource($commentModel),
        ]);
    }
}
