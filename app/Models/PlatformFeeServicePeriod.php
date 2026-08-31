<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeServicePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'ledger_id',
        'replacement_of_id',
        'plan_version_id',
        'tier_id',
        'promotion_id',
        'promotion_assignment_id',
        'purpose',
        'status',
        'period_start',
        'period_end',
        'reference_period_start',
        'reference_period_end',
        'service_days',
        'reference_days',
        'rounding_rule',
        'court_count',
        'price_per_court_month',
        'base_amount',
        'prepay_discount_percent',
        'prepay_discount_amount',
        'promotion_discount_amount',
        'waiver_amount',
        'net_amount',
        'idempotency_key',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'promotion_id' => 'integer',
            'promotion_assignment_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'reference_period_start' => 'date',
            'reference_period_end' => 'date',
            'service_days' => 'integer',
            'reference_days' => 'integer',
            'court_count' => 'integer',
            'price_per_court_month' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'prepay_discount_percent' => 'decimal:2',
            'prepay_discount_amount' => 'decimal:2',
            'promotion_discount_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function ledger()
    {
        return $this->belongsTo(VenuePlatformFeeLedger::class, 'ledger_id');
    }

    public function replacementOf()
    {
        return $this->belongsTo(self::class, 'replacement_of_id');
    }

    public function planVersion()
    {
        return $this->belongsTo(PlatformFeePlanVersion::class, 'plan_version_id');
    }

    public function tier()
    {
        return $this->belongsTo(PlatformFeeTier::class, 'tier_id');
    }

    public function promotion()
    {
        return $this->belongsTo(PlatformFeePromotion::class, 'promotion_id');
    }

    public function promotionAssignment()
    {
        return $this->belongsTo(PlatformFeePromotionAssignment::class, 'promotion_assignment_id');
    }
}
