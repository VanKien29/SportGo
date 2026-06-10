<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerContract extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contract_code',
        'partner_application_id',
        'owner_id',
        'venue_cluster_id',
        'contract_title',
        'status',
        'generated_document_id',
        'generated_file_media_id',
        'signed_file_media_id',
        'final_file_media_id',
        'generated_by',
        'approved_by',
        'owner_signed_at',
        'sportgo_signed_at',
        'effective_from',
        'effective_to',
        'terminated_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'owner_signed_at' => 'datetime',
            'sportgo_signed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function generatedDocument()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function terminations()
    {
        return $this->hasMany(PartnerTerminationRequest::class, 'partner_contract_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
