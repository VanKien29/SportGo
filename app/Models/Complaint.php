<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $hidden = [
        'idempotency_key',
        'request_fingerprint',
        'submitted_ip',
        'submitted_user_agent',
    ];

    protected $fillable = [
        'complaint_type',
        'is_vip_priority',
        'idempotency_key',
        'request_fingerprint',
        'booking_id',
        'venue_cluster_id',
        'customer_id',
        'booking_snapshot',
        'submitted_ip',
        'submitted_user_agent',
        'policy_version',
        'content',
        'status',
        'first_response_at',
        'response_due_at',
        'resolution_due_at',
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
            'booking_snapshot' => 'array',
            'resolved_at' => 'datetime',
            'first_response_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
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

    public function replies()
    {
        return $this->hasMany(ComplaintReply::class, 'complaint_id')->orderBy('created_at', 'asc');
    }
}
