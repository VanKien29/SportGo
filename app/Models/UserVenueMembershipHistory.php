<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVenueMembershipHistory extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'membership_id',
        'user_id',
        'venue_cluster_id',
        'from_tier_key',
        'to_tier_key',
        'change_type',
        'reason',
        'completed_bookings',
        'total_spend_amount',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_bookings' => 'integer',
            'total_spend_amount' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }
}
