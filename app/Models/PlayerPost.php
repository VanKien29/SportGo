<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'author_id',
        'title',
        'description',
        'image_path',
        'needed_players',
        'lock_lead_minutes',
        'skill_level',
        'cost_per_player',
        'cost_type',
        'status',
        'status_reason',
        'ai_verdict',
        'ai_score',
        'ai_summary',
        'ai_flags',
        'ai_reviewed_at',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'needed_players' => 'integer',
            'lock_lead_minutes' => 'integer',
            'cost_per_player' => 'decimal:2',
            'ai_flags' => 'array',
            'ai_score' => 'integer',
            'ai_reviewed_at' => 'datetime',
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

    public function participants()
    {
        return $this->belongsToMany(User::class, 'player_post_participants', 'post_id', 'user_id')
            ->withPivot(['status', 'message', 'responded_at', 'left_at'])
            ->withTimestamps();
    }
}
