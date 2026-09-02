<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ConversationParticipant;
use App\Models\Booking;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private channel for a specific conversation.
 * Only allow users who are actual participants to subscribe.
 */
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

/**
 * Private channel for a specific user.
 * Only allow the user themselves to subscribe to their own channel.
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

/**
 * Private channel for payment updates of a booking.
 * Only the booking owner may subscribe to its payment events.
 */
Broadcast::channel('booking.{bookingId}', function ($user, $bookingId) {
    return Booking::query()
        ->whereKey($bookingId)
        ->where(function ($query) use ($user) {
            $query->where('customer_id', $user->id)
                ->orWhereHas('venueCluster', fn ($clusterQuery) => $clusterQuery->where('owner_id', $user->id));
        })
        ->exists();
});

/**
 * Presence channel for tracking active/online chat users.
 */
Broadcast::channel('chat-presence', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->username,
        ];
    }
    return false;
});
