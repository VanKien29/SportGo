<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPackage extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'monthly_price',
        'quarterly_price',
        'yearly_price',
        'voucher_count_per_month',
        'voucher_discount_percent',
        'voucher_min_order_amount',
        'voucher_max_discount_amount',
        'cashback_percent',
        'match_post_limit_per_month',
        'priority_complaint',
        'badge_name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'quarterly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'voucher_count_per_month' => 'integer',
            'voucher_discount_percent' => 'decimal:2',
            'voucher_min_order_amount' => 'decimal:2',
            'voucher_max_discount_amount' => 'decimal:2',
            'cashback_percent' => 'decimal:2',
            'match_post_limit_per_month' => 'integer',
            'priority_complaint' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'package_id');
    }
}
