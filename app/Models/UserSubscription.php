<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'package_id',
        'billing_cycle',
        'started_at',
        'expires_at',
        'status',
        'paid_amount',
        'payment_ref',
        'month_post_count',
        'month_post_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_amount' => 'decimal:2',
            'month_post_count' => 'integer',
            'month_post_reset_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membershipPackage()
    {
        return $this->belongsTo(MembershipPackage::class, 'package_id');
    }
}
