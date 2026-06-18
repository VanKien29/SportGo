<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->full_name ?: $this->user?->username),
            'user_avatar' => $this->whenLoaded('user', fn () => $this->user?->avatar_url),
            'post' => $this->whenLoaded('post', fn () => [
                'id' => $this->post->id,
                'content' => mb_substr($this->post->content ?? '', 0, 200),
                'status' => $this->post->status ?? null,
            ]),
            'is_edited' => $this->updated_at && $this->created_at
                ? $this->updated_at->gt($this->created_at)
                : false,
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'content' => $reply->content,
                'user_name' => $reply->user?->full_name ?: $reply->user?->username,
                'user_avatar' => $reply->user?->avatar_url,
                'created_at' => $reply->created_at,
            ])),
            'replies_count' => $this->whenCounted('replies', $this->replies_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
