<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerContract extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $appends = [
        'contract_number',
        'generated_file_path',
    ];

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

    public function getContractNumberAttribute(): ?string
    {
        return $this->contract_code;
    }

    public function getGeneratedFilePathAttribute(): ?string
    {
        return $this->generatedDocument?->generated_file_path;
    }

    public function profile()
    {
        return $this->application();
    }

    public function generatedDocument()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function signatures()
    {
        return $this->hasMany(GeneratedDocumentSignature::class, 'generated_document_id', 'generated_document_id');
    }

    public function template()
    {
        return $this->hasOneThrough(
            DocumentTemplate::class,
            GeneratedDocument::class,
            'id',
            'id',
            'generated_document_id',
            'template_id'
        );
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function terminations()
    {
        return $this->hasMany(PartnerTerminationRequest::class, 'partner_application_id', 'partner_application_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

}
