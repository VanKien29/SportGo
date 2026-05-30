<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePlatformFeeLedger extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'tier_id',
        'court_count',
        'billing_cycle',
        'period_months',
        'period_start',
        'period_end',
        'due_date',
        'price_per_court_month',
        'discount_percent',
        'amount_due',
        'amount_paid',
        'payment_proof_media_id',
        'payment_proof_status',
        'payment_proof_note',
        'status',
        'paid_at',
        'payment_confirmed_by',
        'payment_confirmed_at',
        'payment_rejected_by',
        'payment_rejected_at',
        'payment_reject_reason',
        'locked_venue_at',
        'internal_receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'tier_id' => 'integer',
            'court_count' => 'integer',
            'period_months' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'price_per_court_month' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_rejected_at' => 'datetime',
            'locked_venue_at' => 'datetime',
        ];
    }

    public function internalReceipt()
    {
        return $this->belongsTo(InternalReceipt::class, 'internal_receipt_id');
    }

    public function paymentConfirmedBy()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function paymentProofMedia()
    {
        return $this->belongsTo(Media::class, 'payment_proof_media_id');
    }

    public function paymentRejectedBy()
    {
        return $this->belongsTo(User::class, 'payment_rejected_by');
    }

    public function tier()
    {
        return $this->belongsTo(PlatformFeeTier::class, 'tier_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
