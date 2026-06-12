<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_id',
        'booking_id',
        'customer_id',
        'amount',
        'reason',
        'refund_destination',
        'user_wallet_id',
        'user_wallet_ledger_id',
        'user_payout_account_id',
        'owner_wallet_ledger_id',
        'policy_id',
        'policy_rule_id',
        'policy_evaluation_log_id',
        'status',
        'status_reason',
        'owner_confirmed_by',
        'owner_confirmed_at',
        'owner_confirm_note',
        'processed_by',
        'processed_at',
        'admin_confirmed_by',
        'admin_confirmed_at',
        'completed_at',
        'gateway_refund_txn_id',
        'payout_transfer_code',
        'payout_qr_created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'owner_confirmed_at' => 'datetime',
            'processed_at' => 'datetime',
            'admin_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'payout_qr_created_at' => 'datetime',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function ownerConfirmedBy()
    {
        return $this->belongsTo(User::class, 'owner_confirmed_by');
    }

    public function adminConfirmedBy()
    {
        return $this->belongsTo(User::class, 'admin_confirmed_by');
    }

    public function receipt()
    {
        return $this->morphOne(InternalReceipt::class, 'receiptable');
    }

    public function payoutAccount()
    {
        return $this->belongsTo(UserPayoutAccount::class, 'user_payout_account_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function policy()
    {
        return $this->belongsTo(SystemPolicy::class, 'policy_id');
    }

    public function policyRule()
    {
        return $this->belongsTo(PolicyRule::class, 'policy_rule_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(RefundStatusHistory::class, 'refund_id');
    }
}
