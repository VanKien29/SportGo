<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceSlot extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'court_type_id',
        'booking_type',
        'start_time',
        'end_time',
        'price',
        'apply_to_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'price' => 'decimal:2',
            'apply_to_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
