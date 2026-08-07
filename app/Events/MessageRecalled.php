<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRecalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public string $messageId;

    public function __construct(string $conversationId, string $messageId)
    {
        $this->conversationId = (string) $conversationId;
        $this->messageId = (string) $messageId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.recalled';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'is_recalled' => true,
        ];
    }
}
