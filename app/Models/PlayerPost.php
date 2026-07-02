<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerPost extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_id',
        'author_id',
        'title',
        'description',
        'image_path',
        'needed_players',
        'cost_per_player',
        'status',
        'status_reason',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'needed_players' => 'integer',
            'cost_per_player' => 'decimal:2',
        ];
    }

    protected function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
