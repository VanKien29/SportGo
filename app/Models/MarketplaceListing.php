<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceListing extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'price',
        'is_negotiable',
        'condition',
        'category',
        'court_type_id',
        'preferred_venue_cluster_id',
        'pickup_address',
        'status',
        'reviewed_by',
        'reviewed_at',
        'status_reason',
        'view_count',
        'expires_at',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_negotiable' => 'boolean',
            'reviewed_at' => 'datetime',
            'view_count' => 'integer',
            'expires_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function preferredVenueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'preferred_venue_cluster_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
