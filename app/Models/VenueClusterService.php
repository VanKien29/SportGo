<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueClusterService extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'category_id',
        'name',
        'price',
        'unit',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /**
     * Mối quan hệ với Cụm sân (Venue Cluster)
     */
    public function venueCluster(): BelongsTo
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    /**
     * Mối quan hệ với Danh mục dịch vụ hệ thống
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }
}
