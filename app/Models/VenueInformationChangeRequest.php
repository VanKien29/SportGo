<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueInformationChangeRequest extends Model
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
        'new_name',
        'new_phone_contact',
        'new_description',
        'new_images',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_images'  => 'array',
            'reviewed_at' => 'datetime',
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
