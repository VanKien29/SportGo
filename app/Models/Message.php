<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'reply_to_id',
        'sender_id',
        'content',
        'is_system',
        'reference_type',
        'reference_id',
        'reactions',
        'is_pinned',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute()
    {
        if ($this->reference_type === 'image' && $this->reference_id) {
            return asset('storage/' . $this->reference_id);
        }
        return null;
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_pinned' => 'boolean',
            'reactions' => 'array',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }
}
