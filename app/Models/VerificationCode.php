<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'identifier',
        'type',
        'channel',
        'code',
        'attempt_count',
        'max_attempts',
        'is_used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'is_used' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
