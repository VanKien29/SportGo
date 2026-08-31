<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePlatformFeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'trial_plan_version_id',
        'trial_status',
        'trial_days',
        'trial_started_at',
        'trial_ends_at',
        'fee_started_at',
        'billing_anchor_day',
        'auto_pay_from_balance',
        'last_fee_cutoff_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'fee_started_at' => 'datetime',
            'billing_anchor_day' => 'integer',
            'auto_pay_from_balance' => 'boolean',
            'last_fee_cutoff_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function trialPlanVersion()
    {
        return $this->belongsTo(PlatformFeePlanVersion::class, 'trial_plan_version_id');
    }
}
