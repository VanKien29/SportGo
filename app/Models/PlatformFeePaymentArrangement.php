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
        'terms_revision',
        'service_months',
        'service_start',
        'service_end',
        'payment_due_date',
        'expires_at',
        'credit_limit',
        'total_amount',
        'secured_amount',
        'reason',
        'admin_note',
        'proposed_by',
        'approved_by',
        'owner_accepted_by',
        'accepted_terms_snapshot',
        'owner_accepted_ip',
        'owner_accepted_user_agent',
        'cancelled_by',
        'approved_at',
        'owner_accepted_at',
        'cancelled_at',
        'rejected_at',
        'cancellation_reason',
        'fulfilled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'service_months' => 'integer',
            'terms_revision' => 'integer',
            'service_start' => 'date',
            'service_end' => 'date',
            'payment_due_date' => 'date',
            'expires_at' => 'datetime',
            'credit_limit' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'secured_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'owner_accepted_at' => 'datetime',
            'accepted_terms_snapshot' => 'array',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
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
