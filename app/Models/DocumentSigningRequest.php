<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSigningRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'generated_document_id',
        'verification_code_id',
        'user_id',
        'signer_side',
        'action',
        'document_type',
        'document_code',
        'document_version',
        'file_hash',
        'file_hash_after',
        'nonce',
        'otp_type',
        'otp_channel',
        'otp_identifier',
        'otp_sent_at',
        'otp_verified_at',
        'expires_at',
        'status',
        'checkbox_text',
        'signature_image',
        'signed_signature_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'document_version' => 'integer',
            'otp_sent_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function document()
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function verificationCode()
    {
        return $this->belongsTo(VerificationCode::class, 'verification_code_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function signature()
    {
        return $this->belongsTo(GeneratedDocumentSignature::class, 'signed_signature_id');
    }
}
