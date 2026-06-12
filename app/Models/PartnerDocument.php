<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_application_id',
        'type',
        'file_path',
        'file_name',
    ];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\DocumentType::class,
        ];
    }

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }
}

