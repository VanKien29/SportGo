<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingConfig extends Model
{
    use HasFactory;

    protected $primaryKey = 'venue_cluster_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'min_duration_minutes',
        'max_duration_minutes',
        'min_advance_booking_minutes',
        'fixed_open_time',
        'fixed_close_time',
        'weekly_operating_hours',
        'special_operating_hours',
        'slot_hold_minutes',
        'reminder_before_minutes',
        'allow_full_payment',
        'allow_deposit',
        'allow_no_prepay',
        'auto_approve_full_payment',
        'deposit_percent',
        'cancel_before_hours',
        'refund_percent',
    ];

    protected function casts(): array
    {
        return [
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
            'min_advance_booking_minutes' => 'integer',
            'weekly_operating_hours' => 'array',
            'special_operating_hours' => 'array',
            'slot_hold_minutes' => 'integer',
            'reminder_before_minutes' => 'integer',
            'allow_full_payment' => 'boolean',
            'allow_deposit' => 'boolean',
            'allow_no_prepay' => 'boolean',
            'auto_approve_full_payment' => 'boolean',
            'deposit_percent' => 'decimal:2',
            'cancel_before_hours' => 'integer',
            'refund_percent' => 'integer',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
