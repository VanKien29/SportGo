<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingSupportRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'conversation_id',
        'booking_id',
        'customer_id',
        'venue_cluster_id',
        'request_type',
        'note',
        'status',
        'handled_by',
        'handled_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}