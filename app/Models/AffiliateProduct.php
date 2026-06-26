<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateProduct extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'name',
        'description',
        'image_path',
        'price',
        'original_price',
        'affiliate_url',
        'platform_name',
        'is_active',
        'click_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'click_count' => 'integer',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
        ];
    }

    /**
     * Mối quan hệ với Cụm sân (Venue Cluster)
     */
    public function venueCluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
