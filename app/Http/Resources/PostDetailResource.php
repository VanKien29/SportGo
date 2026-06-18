<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'status' => $this->status,
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->full_name ?: $this->author?->username),
            'author_avatar' => $this->whenLoaded('author', fn () => $this->author?->avatar_url),
            'comment_count' => $this->comment_count ?? 0,
            'like_count' => $this->like_count ?? 0,
            'view_count' => $this->view_count ?? 0,
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($comment) => [
                'id' => $comment->id,
                'content' => $comment->content,
                'status' => $comment->status,
                'user_name' => $comment->user?->full_name ?: $comment->user?->username,
                'user_avatar' => $comment->user?->avatar_url,
                'replies_count' => $comment->replies_count ?? 0,
                'created_at' => $comment->created_at,
            ])),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => str_starts_with($m->file_path, 'http') ? $m->file_path : \Illuminate\Support\Facades\Storage::url($m->file_path),
                'type' => $m->type ?? $m->mime_type,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
