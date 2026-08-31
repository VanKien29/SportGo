<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePlatformFeeLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'creation_source',
        'automation_key',
        'tier_id',
        'plan_version_id',
        'promotion_id',
        'payment_arrangement_id',
        'tier_name_snapshot',
        'tier_min_courts_snapshot',
        'tier_max_courts_snapshot',
        'court_count',
        'billing_cycle',
        'period_months',
        'period_start',
        'period_end',
        'due_date',
        'original_due_date',
        'price_per_court_month',
        'discount_percent',
        'pricing_snapshotted_at',
        'base_amount',
        'prepay_discount_amount',
        'promotion_discount_amount',
        'waiver_amount',
        'settlement_type',
        'settlement_reason',
        'amount_due',
        'amount_paid',
        'system_bank_account_id',
        'payment_code',
        'gateway_txn_id',
        'gateway_response',
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
        'voided_by',
        'voided_at',
        'replaced_by_ledger_id',
        'locked_venue_at',
        'internal_receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'tier_id' => 'integer',
            'plan_version_id' => 'integer',
            'promotion_id' => 'integer',
            'payment_arrangement_id' => 'integer',
            'tier_min_courts_snapshot' => 'integer',
            'tier_max_courts_snapshot' => 'integer',
            'court_count' => 'integer',
            'period_months' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'original_due_date' => 'date',
            'price_per_court_month' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'pricing_snapshotted_at' => 'datetime',
            'base_amount' => 'decimal:2',
            'prepay_discount_amount' => 'decimal:2',
            'promotion_discount_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_rejected_at' => 'datetime',
            'voided_at' => 'datetime',
            'locked_venue_at' => 'datetime',
        ];
    }

    public function internalReceipt()
    {
        return $this->belongsTo(InternalReceipt::class, 'internal_receipt_id');
    }

    public function emailLogs()
    {
        return $this->hasMany(PlatformFeeEmailLog::class, 'ledger_id')->latest();
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

    public function planVersion()
    {
        return $this->belongsTo(PlatformFeePlanVersion::class, 'plan_version_id');
    }

    public function promotion()
    {
        return $this->belongsTo(PlatformFeePromotion::class, 'promotion_id');
    }

    public function paymentArrangement()
    {
        return $this->belongsTo(PlatformFeePaymentArrangement::class, 'payment_arrangement_id');
    }

    public function servicePeriods()
    {
        return $this->hasMany(PlatformFeeServicePeriod::class, 'ledger_id')->orderBy('period_start');
    }

    public function walletHold()
    {
        return $this->hasOne(PlatformFeeWalletHold::class, 'ledger_id');
    }

    public function replacementLedger()
    {
        return $this->belongsTo(self::class, 'replaced_by_ledger_id');
    }

    public function systemBankAccount()
    {
        return $this->belongsTo(SystemBankAccount::class, 'system_bank_account_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
