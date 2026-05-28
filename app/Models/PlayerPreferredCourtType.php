<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerPreferredCourtType extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_type_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
