<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_code',
        'payment_context',
        'booking_id',
        'subscription_id',
        'system_bank_account_id',
        'amount',
        'wallet_amount',
        'gateway_amount',
        'user_wallet_id',
        'user_wallet_ledger_id',
        'payment_kind',
        'method',
        'gateway_txn_id',
        'gateway_response',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'wallet_amount' => 'decimal:2',
            'gateway_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function subscription()
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    public function logs()
    {
        return $this->hasMany(PaymentLog::class, 'payment_id')->latest('created_at');
    }

    public function ownerWalletLedgers()
    {
        return $this->hasMany(OwnerWalletLedger::class, 'payment_id');
    }

    public function systemBankAccount()
    {
        return $this->belongsTo(SystemBankAccount::class, 'system_bank_account_id');
    }
}
