<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'player_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'player_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
