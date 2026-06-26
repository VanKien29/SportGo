<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtMembershipTier extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'tier',
        'discount_percent',
        'min_bookings',
        'min_spent_amount',
        'maintain_min_bookings',
        'maintain_min_spent',
        'maintain_period_months',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'min_bookings' => 'integer',
            'min_spent_amount' => 'decimal:2',
            'maintain_min_bookings' => 'integer',
            'maintain_min_spent' => 'decimal:2',
            'maintain_period_months' => 'integer',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
