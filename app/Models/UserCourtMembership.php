<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCourtMembership extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'venue_cluster_id',
        'tier',
        'total_bookings',
        'total_spent',
        'period_bookings',
        'period_spent',
        'period_start',
        'last_upgraded_at',
        'last_downgraded_at',
        'downgrade_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'total_bookings' => 'integer',
            'total_spent' => 'decimal:2',
            'period_bookings' => 'integer',
            'period_spent' => 'decimal:2',
            'period_start' => 'date',
            'last_upgraded_at' => 'datetime',
            'last_downgraded_at' => 'datetime',
            'downgrade_notified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
