<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePlatformFeeLedger extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'tier_id',
        'court_count',
        'billing_cycle',
        'period_start',
        'period_end',
        'price_per_court_month',
        'discount_percent',
        'amount_due',
        'amount_paid',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'tier_id' => 'integer',
            'court_count' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'price_per_court_month' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function tier()
    {
        return $this->belongsTo(PlatformFeeTier::class, 'tier_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
