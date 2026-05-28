<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'system_policies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'version',
        'title',
        'content',
        'type',
        'is_active',
        'effective_from',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
