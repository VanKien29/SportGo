<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_application_id',
        'media_id',
        'document_type',
        'document_group',
        'title',
        'description',
        'file_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'sort_order',
    ];

    protected $hidden = [
        'file_path',
    ];

    protected $appends = [
        'download_url',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function partnerApplication()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getDownloadUrlAttribute(): string
    {
        return url('/api/user/partner-application/documents/' . $this->id . '/download');
    }
}
