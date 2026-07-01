<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueCourtApprovalRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'court_type_id',
        'name',
        'status',
        'requested_by',
        'reviewed_by',
        'status_reason',
        'evidence_image',
        'supplementary_documents',
        'approved_venue_court_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'reviewed_at' => 'datetime',
            'supplementary_documents' => 'array',
        ];
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
