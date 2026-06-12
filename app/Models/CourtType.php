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
        'parent_id',
        'description',
        'player_count',
        'is_active',
        'default_layout_w',
        'default_layout_h',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'player_count' => 'integer',
            'is_active' => 'boolean',
            'default_layout_w' => 'double',
            'default_layout_h' => 'double',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(CourtType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CourtType::class, 'parent_id');
    }
}
