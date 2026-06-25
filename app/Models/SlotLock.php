<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_cluster_id',
        'venue_court_id',
        'lock_scope',
        'booking_date',
        'start_time',
        'end_time',
        'locked_by',
        'booking_id',
        'booking_item_id',
        'lock_type',
        'reason',
        'notified_booking_ids',
        'notification_sent_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'notified_booking_ids' => 'array',
            'notification_sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function notifyAffectedBookings(): array
    {
        $alreadyNotified = collect($this->notified_booking_ids ?? [])->filter()->values();

        $bookings = Booking::query()
            ->where('venue_cluster_id', $this->venue_cluster_id)
            ->whereDate('booking_date', $this->booking_date)
            ->whereIn('status', ['pending_approval', 'pending_payment', 'confirmed', 'checked_in'])
            ->when($this->venue_court_id, fn ($query) => $query->where('venue_court_id', $this->venue_court_id))
            ->where('start_time', '<', $this->end_time)
            ->where('end_time', '>', $this->start_time)
            ->whereNotNull('customer_id')
            ->whereNotIn('id', $alreadyNotified->all())
            ->get();

        foreach ($bookings as $booking) {
            Notification::query()->create([
                'user_id' => $booking->customer_id,
                'type' => 'emergency_slot_lock',
                'title' => 'Lich dat san bi anh huong',
                'body' => 'San da bi khoa dot xuat. SportGo se cap nhat phuong an xu ly va hoan tien neu can.',
                'reference_type' => Booking::class,
                'reference_id' => $booking->id,
                'data' => [
                    'slot_lock_id' => $this->id,
                    'venue_cluster_id' => $this->venue_cluster_id,
                    'venue_court_id' => $this->venue_court_id,
                    'booking_date' => $this->booking_date?->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'reason' => $this->reason,
                ],
            ]);
        }

        $notifiedIds = $alreadyNotified
            ->merge($bookings->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $this->forceFill([
            'notified_booking_ids' => $notifiedIds,
            'notification_sent_at' => count($notifiedIds) > 0 ? now() : $this->notification_sent_at,
        ])->save();

        return $bookings->pluck('id')->all();
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function venueCourt()
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
