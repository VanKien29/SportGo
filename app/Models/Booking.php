<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_code',
        'customer_id',
        'venue_court_id',
        'requested_venue_court_id',
        'venue_cluster_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'total_price',
        'original_amount',
        'discount_amount',
        'membership_discount_amount',
        'membership_tier_snapshot',
        'system_discount_amount',
        'venue_discount_amount',
        'final_amount',
        'voucher_id',
        'voucher_code_snapshot',
        'payment_option',
        'required_payment_amount',
        'source',
        'booking_type',
        'recurring_group_code',
        'recurring_start_date',
        'recurring_end_date',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_days_of_week',
        'recurrence_days_of_month',
        'status',
        'walk_in_name',
        'walk_in_phone',
        'status_reason',
        'cancelled_by',
        'cancellation_initiator',
        'cancellation_reason_type',
        'cancelled_at',
        'created_by',
        'court_changed_by',
        'court_changed_at',
        'court_changed_reason',
        'reminder_sent_at',
        'membership_tier_discount_amount',
        'membership_tier',
        'cashback_amount',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'duration_minutes' => 'integer',
            'total_price' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'membership_discount_amount' => 'decimal:2',
            'membership_tier_snapshot' => 'array',
            'system_discount_amount' => 'decimal:2',
            'venue_discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'required_payment_amount' => 'decimal:2',
            'membership_tier_discount_amount' => 'decimal:2',
            'cashback_amount' => 'decimal:2',
            'recurring_start_date' => 'date',
            'recurring_end_date' => 'date',
            'recurrence_interval' => 'integer',
            'recurrence_days_of_week' => 'array',
            'recurrence_days_of_month' => 'array',
            'cancelled_at' => 'datetime',
            'court_changed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function courtChangedBy()
    {
        return $this->belongsTo(User::class, 'court_changed_by');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class, 'booking_id')->orderBy('sort_order');
    }

    /**
     * Tổng thời lượng (phút) = SUM(booking_items.duration_minutes)
     */
    public function totalDurationMinutes(): int
    {
        return (int) $this->items()->sum('duration_minutes');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    public function slotLocks()
    {
        return $this->hasMany(SlotLock::class, 'booking_id');
    }

    public function requestedVenueCourt()
    {
        return $this->belongsTo(VenueCourt::class, 'requested_venue_court_id');
    }

    public function venueCourt()
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }
}
