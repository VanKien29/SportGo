<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeePromotionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'venue_cluster_id',
        'initial_cycles',
        'remaining_cycles',
        'status',
        'assigned_by',
        'assigned_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_cycles' => 'integer',
            'remaining_cycles' => 'integer',
            'assigned_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function promotion()
    {
        return $this->belongsTo(PlatformFeePromotion::class, 'promotion_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
