<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueTransferRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'transfer_code',
        'venue_cluster_id',
        'from_owner_id',
        'to_owner_id',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'status_reason',
        'effective_date',
        'completed_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function fromOwner()
    {
        return $this->belongsTo(User::class, 'from_owner_id');
    }

    public function toOwner()
    {
        return $this->belongsTo(User::class, 'to_owner_id');
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
