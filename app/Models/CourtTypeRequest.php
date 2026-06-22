<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtTypeRequest extends Model
{
    use HasFactory;

    protected $table = 'court_type_requests';

    protected $fillable = [
        'name',
        'description',
        'player_count',
        'parent_id',
        'requested_by',
        'status',
        'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'player_count' => 'integer',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(CourtType::class, 'parent_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
