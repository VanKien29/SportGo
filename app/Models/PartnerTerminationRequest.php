<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_application_id',
        'requested_by',
        'type',
        'reason',
        'status',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\TerminationType::class,
            'approved_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function liquidation()
    {
        return $this->hasOne(PartnerLiquidation::class, 'termination_request_id');
    }
}

