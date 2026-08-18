<?php

namespace App\Observers;

use App\Events\BookingScheduleUpdated;
use App\Models\SlotLock;
use Throwable;

class SlotLockObserver
{
    /**
     * Handle the SlotLock "created" event.
     */
    public function created(SlotLock $slotLock): void
    {
        $this->broadcastScheduleChange($slotLock, 'slot_locked');
    }

    /**
     * Handle the SlotLock "deleted" event.
     */
    public function deleted(SlotLock $slotLock): void
    {
        $this->broadcastScheduleChange($slotLock, 'slot_unlocked');
    }

    /**
     * Helper to safely dispatch broadcast event without breaking database transactions.
     */
    private function broadcastScheduleChange(SlotLock $slotLock, string $eventType): void
    {
        $clusterId = $slotLock->venue_cluster_id;
        $date = $slotLock->booking_date ? (string) $slotLock->booking_date : null;

        if (!$clusterId) {
            return;
        }

        try {
            broadcast(new BookingScheduleUpdated(
                (string) $clusterId,
                $date,
                $eventType,
                [
                    'lock_id' => $slotLock->id,
                    'venue_court_id' => $slotLock->venue_court_id,
                    'start_time' => $slotLock->start_time,
                    'end_time' => $slotLock->end_time,
                    'lock_type' => $slotLock->lock_type,
                ]
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
