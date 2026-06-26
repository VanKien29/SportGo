<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCourtMembershipHistory extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'membership_id',
        'user_id',
        'venue_cluster_id',
        'from_tier',
        'to_tier',
        'change_type',
        'reason',
        'total_bookings',
        'total_spent',
        'period_bookings',
        'period_spent',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_bookings' => 'integer',
            'total_spent' => 'decimal:2',
            'period_bookings' => 'integer',
            'period_spent' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function membership()
    {
        return $this->belongsTo(UserCourtMembership::class, 'membership_id');
    }
}
