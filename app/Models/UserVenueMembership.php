<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVenueMembership extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'venue_cluster_id',
        'tier_key',
        'completed_bookings',
        'total_spend_amount',
        'last_booking_completed_at',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_bookings' => 'integer',
            'total_spend_amount' => 'decimal:2',
            'last_booking_completed_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
