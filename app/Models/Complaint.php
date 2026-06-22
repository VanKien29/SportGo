<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'complaint_type',
        'is_vip_priority',
        'booking_id',
        'venue_cluster_id',
        'customer_id',
        'content',
        'status',
        'assigned_to',
        'resolved_by',
        'resolve_note',
        'status_reason',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_vip_priority' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function evidence()
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
