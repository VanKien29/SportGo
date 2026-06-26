<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueMembershipTierSetting extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'tier_key',
        'tier_label',
        'tier_order',
        'discount_percent',
        'min_completed_bookings',
        'min_spend_amount',
        'maintain_period_months',
        'maintain_min_bookings',
        'maintain_min_spend_amount',
    ];

    protected function casts(): array
    {
        return [
            'tier_order' => 'integer',
            'discount_percent' => 'decimal:2',
            'min_completed_bookings' => 'integer',
            'min_spend_amount' => 'decimal:2',
            'maintain_period_months' => 'integer',
            'maintain_min_bookings' => 'integer',
            'maintain_min_spend_amount' => 'decimal:2',
        ];
    }
}
