<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Message extends Model
{
    use HasFactory;

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

    /**
     * Tự động mã hóa nội dung tin nhắn và giải mã khi truy xuất.
     * Tương thích ngược với các tin nhắn dạng văn bản thô cũ.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return $value;
                }
                try {
                    return decrypt($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $value;
                }
            },
            set: fn ($value) => $value !== null ? encrypt($value) : null,
        );
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
