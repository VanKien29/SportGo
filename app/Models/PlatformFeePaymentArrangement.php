<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeePaymentArrangement extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'venue_cluster_id',
        'owner_id',
        'status',
        'arrangement_type',
        'service_months',
        'service_start',
        'service_end',
        'payment_due_date',
        'credit_limit',
        'total_amount',
        'secured_amount',
        'reason',
        'admin_note',
        'proposed_by',
        'approved_by',
        'owner_accepted_by',
        'approved_at',
        'owner_accepted_at',
        'cancelled_at',
        'fulfilled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'service_months' => 'integer',
            'service_start' => 'date',
            'service_end' => 'date',
            'payment_due_date' => 'date',
            'credit_limit' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'secured_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'owner_accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function ledgers()
    {
        return $this->belongsToMany(
            VenuePlatformFeeLedger::class,
            'platform_fee_payment_arrangement_ledgers',
            'arrangement_id',
            'ledger_id',
        )->withPivot(['original_due_date'])->withTimestamps();
    }

    public function holds()
    {
        return $this->hasMany(PlatformFeeWalletHold::class, 'arrangement_id');
    }
}
