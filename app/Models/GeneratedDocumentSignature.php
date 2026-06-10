<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedDocumentSignature extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'generated_document_id',
        'signer_side',
        'signer_user_id',
        'signer_full_name',
        'signer_title',
        'signer_organization',
        'signature_method',
        'signature_media_id',
        'signed_at',
        'ip_address',
        'user_agent',
        'status',
        'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
