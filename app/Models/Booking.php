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
        'venue_cluster_id',
        'booking_date',
        'total_price',
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
        'cancelled_at',
        'created_by',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'total_price' => 'decimal:2',
            'required_payment_amount' => 'decimal:2',
            'recurring_start_date' => 'date',
            'recurring_end_date' => 'date',
            'recurrence_interval' => 'integer',
            'recurrence_days_of_week' => 'array',
            'recurrence_days_of_month' => 'array',
            'cancelled_at' => 'datetime',
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
}
