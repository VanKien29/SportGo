<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeePlanVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'effective_from',
        'effective_to',
        'trial_days',
        'invoice_lead_days',
        'due_day',
        'notice_days',
        'notes',
        'created_by',
        'published_by',
        'scheduled_at',
        'activated_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'trial_days' => 'integer',
            'invoice_lead_days' => 'integer',
            'due_day' => 'integer',
            'notice_days' => 'integer',
            'scheduled_at' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function tiers()
    {
        return $this->hasMany(PlatformFeeTier::class, 'plan_version_id')->orderBy('min_courts');
    }

    public function prepayDiscountRules()
    {
        return $this->hasMany(PlatformFeePrepayDiscountRule::class, 'plan_version_id')->orderBy('months');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
