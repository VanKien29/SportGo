<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViolationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'base_score',
        'is_immediate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_score' => 'integer',
            'is_immediate' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'violation_type_id');
    }
}
