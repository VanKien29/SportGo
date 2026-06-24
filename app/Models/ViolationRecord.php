<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViolationRecord extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'target_type',
        'target_id',
        'violation_count',
        'last_violation_at',
        'last_action_type',
        'last_action_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'violation_count' => 'integer',
            'last_violation_at' => 'datetime',
            'last_action_expires_at' => 'datetime',
        ];
    }
}
