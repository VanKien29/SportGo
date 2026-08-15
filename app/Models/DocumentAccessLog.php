<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'generated_document_id',
        'partner_application_document_id',
        'user_id',
        'action',
        'delivery',
        'file_hash',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function generatedDocument()
    {
        return $this->belongsTo(GeneratedDocument::class);
    }

    public function partnerApplicationDocument()
    {
        return $this->belongsTo(PartnerApplicationDocument::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
