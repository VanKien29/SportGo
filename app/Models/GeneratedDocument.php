<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedDocument extends Model
{
    use HasFactory;

    protected $hidden = [
        'generated_file_path',
        'final_file_path',
        'generated_pdf_path',
        'final_pdf_path',
        'file_hash',
        'pdf_hash',
        'final_pdf_hash',
    ];

    protected $fillable = [
        'document_code',
        'document_type',
        'template_id',
        'template_version',
        'document_version',
        'reference_type',
        'reference_id',
        'entity_type',
        'entity_id',
        'partner_application_id',
        'partner_contract_id',
        'partner_termination_request_id',
        'partner_settlement_id',
        'owner_id',
        'venue_cluster_id',
        'title',
        'status',
        'render_data',
        'generated_file_media_id',
        'signed_file_media_id',
        'final_file_media_id',
        'generated_file_path',
        'final_file_path',
        'generated_pdf_path',
        'final_pdf_path',
        'file_hash',
        'pdf_hash',
        'final_pdf_hash',
        'generated_by',
        'generated_at',
        'pdf_generated_at',
        'locked_at',
        'pdf_locked_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'document_version' => 'integer',
            'render_data' => 'array',
            'generated_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'locked_at' => 'datetime',
            'pdf_locked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signatures()
    {
        return $this->hasMany(GeneratedDocumentSignature::class, 'generated_document_id');
    }

    public function signingRequests()
    {
        return $this->hasMany(DocumentSigningRequest::class, 'generated_document_id');
    }

    public function accessLogs()
    {
        return $this->hasMany(DocumentAccessLog::class, 'generated_document_id');
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
