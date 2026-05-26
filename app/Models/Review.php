<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_id',
        'customer_id',
        'venue_cluster_id',
        'rating',
        'comment',
        'reply_content',
        'replied_at',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
