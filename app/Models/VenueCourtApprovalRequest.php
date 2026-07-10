<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueCourtApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'court_type_id',
        'name',
        'change_type',
        'requested_courts',
        'removed_court_ids',
        'status',
        'requested_by',
        'reviewed_by',
        'status_reason',
        'evidence_image',
        'supplementary_documents',
        'signature_image',
        'signature_hash',
        'signed_at',
        'generated_document_id',
        'approved_venue_court_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'reviewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'supplementary_documents' => 'array',
            'requested_courts' => 'array',
            'removed_court_ids' => 'array',
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

    public function generatedDocument()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
