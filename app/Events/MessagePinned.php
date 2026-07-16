<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagePinned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public string $messageId;
    public bool $isPinned;

    /**
     * Create a new event instance.
     */
    public function __construct(string $conversationId, string $messageId, bool $isPinned)
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
        $this->isPinned = $isPinned;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.pinned';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'is_pinned'  => $this->isPinned,
        ];
    }
}
