<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingScheduleUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $venueClusterId;
    public ?string $bookingDate;
    public string $eventType;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $venueClusterId,
        ?string $bookingDate = null,
        string $eventType = 'booking_updated',
        array $metadata = []
    ) {
        $this->venueClusterId = $venueClusterId;
        $this->bookingDate = $bookingDate;
        $this->eventType = $eventType;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('venue-cluster.' . $this->venueClusterId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'booking.schedule.updated';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'venue_cluster_id' => $this->venueClusterId,
            'booking_date' => $this->bookingDate,
            'event_type' => $this->eventType,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }
}
