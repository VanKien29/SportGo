<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'template_code',
        'document_type',
        'template_name',
        'version',
        'file_name',
        'file_path',
        'mime_type',
        'storage_disk',
        'template_variables',
        'render_engine',
        'status',
        'is_active',
        'uploaded_by',
        'activated_at',
        'replaced_template_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'template_variables' => 'array',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class, 'template_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
