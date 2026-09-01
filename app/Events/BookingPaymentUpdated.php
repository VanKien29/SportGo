<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingPaymentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int|string $bookingId,
        public int|string $customerId,
        public int|string $paymentId,
        public string $paymentStatus,
        public string $bookingStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('booking.' . $this->bookingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.payment.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'customer_id' => $this->customerId,
            'payment_id' => $this->paymentId,
            'payment_status' => $this->paymentStatus,
            'booking_status' => $this->bookingStatus,
        ];
    }
}
