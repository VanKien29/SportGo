<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueLocationChangeRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'requested_by',
        'reviewed_by',
        'status',
        'note',
        'status_reason',
        'new_address',
        'new_province',
        'new_ward',
        'new_latitude',
        'new_longitude',
        'new_map_url',
        'supplementary_documents',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_latitude'  => 'decimal:7',
            'new_longitude' => 'decimal:7',
            'reviewed_at'   => 'datetime',
            'supplementary_documents' => 'array',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
