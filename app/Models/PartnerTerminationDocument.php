<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_termination_request_id',
        'generated_document_id',
        'document_type',
        'media_id',
        'file_path',
        'status',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function generatedDocument()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function request()
    {
        return $this->belongsTo(PartnerTerminationRequest::class, 'partner_termination_request_id');
    }
}
