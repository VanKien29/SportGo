<?php

namespace App\Observers;

use App\Events\BookingScheduleUpdated;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
        $this->broadcastScheduleChange($booking, 'booking_created');
    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        // Check if any schedule-affecting attributes were changed
        $scheduleAttributes = [
            'status',
            'venue_court_id',
            'venue_cluster_id',
            'booking_date',
            'start_time',
            'end_time',
            'deleted_at',
        ];

        if ($booking->wasChanged($scheduleAttributes)) {
            $this->broadcastScheduleChange($booking, 'booking_status_updated');

            // If booking_date was changed, also broadcast for the old date
            if ($booking->wasChanged('booking_date') && $booking->getOriginal('booking_date')) {
                $oldClusterId = $booking->getOriginal('venue_cluster_id') ?: $booking->venue_cluster_id;
                $oldDate = (string) $booking->getOriginal('booking_date');
                $this->dispatchBroadcast($oldClusterId, $oldDate, 'booking_rescheduled');
            }
        }

        if ($booking->wasChanged('status') && in_array($booking->status, ['cancelled', 'rejected'], true)) {
            $this->handleBookingCancellationSideEffects($booking);
        }
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        $this->broadcastScheduleChange($booking, 'booking_deleted');
        $this->handleBookingCancellationSideEffects($booking);
    }

    /**
     * Automatically close linked matchmaking post and notify participants when booking is cancelled/deleted.
     */
    private function handleBookingCancellationSideEffects(Booking $booking): void
    {
        try {
            $playerPost = $booking->playerPost;
            if ($playerPost && in_array($playerPost->status, ['open', 'full'], true)) {
                $playerPost->status = 'closed';
                $playerPost->status_reason = 'booking_cancelled';
                $playerPost->save();

                $participants = DB::table('player_post_participants')
                    ->where('post_id', $playerPost->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->get();

                if ($participants->isNotEmpty()) {
                    DB::table('player_post_participants')
                        ->where('post_id', $playerPost->id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->update([
                            'status' => 'cancelled',
                            'responded_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $venueName = $booking->venueCluster->name ?? 'sân';
                    foreach ($participants as $p) {
                        Notification::create([
                            'user_id' => $p->user_id,
                            'type' => 'matchmaking_booking_cancelled',
                            'title' => 'Buổi giao lưu đã bị hủy',
                            'body' => "Lịch đặt sân tại {$venueName} đã bị hủy nên buổi giao lưu không thể diễn ra.",
                            'reference_type' => 'player_post',
                            'reference_id' => $playerPost->id,
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Helper to safely dispatch broadcast event without breaking business transactions.
     */
    private function broadcastScheduleChange(Booking $booking, string $eventType): void
    {
        $clusterId = $booking->venue_cluster_id;
        $date = $booking->booking_date ? (string) $booking->booking_date : null;

        if (!$clusterId) {
            return;
        }

        $this->dispatchBroadcast(
            (string) $clusterId,
            $date,
            $eventType,
            [
                'booking_id' => $booking->id,
                'status' => $booking->status,
                'venue_court_id' => $booking->venue_court_id,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]
        );
    }

    private function dispatchBroadcast(string $clusterId, ?string $date, string $eventType, array $metadata = []): void
    {
        try {
            broadcast(new BookingScheduleUpdated($clusterId, $date, $eventType, $metadata));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
