<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_id',
        'venue_court_id',
        'requested_venue_court_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'unit_price',
        'subtotal',
        'status',
        'status_reason',
        'cancelled_by',
        'cancelled_at',
        'maintenance_lock_id',
        'court_changed_by',
        'court_changed_at',
        'court_changed_reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'court_changed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function venueCourt()
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function requestedVenueCourt()
    {
        return $this->belongsTo(VenueCourt::class, 'requested_venue_court_id');
    }

    public function courtChangedBy()
    {
        return $this->belongsTo(User::class, 'court_changed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function slotLocks()
    {
        return $this->hasMany(SlotLock::class, 'booking_item_id');
    }

    public function maintenanceLock()
    {
        return $this->belongsTo(SlotLock::class, 'maintenance_lock_id');
    }
}
