<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedDocument extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'document_code',
        'document_type',
        'template_id',
        'template_version',
        'reference_type',
        'reference_id',
        'status',
        'render_data',
        'generated_file_path',
        'final_file_path',
        'generated_by',
        'generated_at',
        'locked_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'render_data' => 'array',
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
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

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
