<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchmakingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $postId,
        public string $action = 'updated',
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("matchmaking.{$this->postId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MatchmakingUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postId,
            'action' => $this->action,
            'data' => $this->data,
        ];
    }
}
