<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerWithdrawalRequest extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'request_code',
        'owner_id',
        'owner_wallet_id',
        'owner_bank_account_id',
        'amount',
        'status',
        'owner_note',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'status_reason',
        'completed_by',
        'completed_at',
        'transfer_reference',
        'metadata',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function bankAccount()
    {
        return $this->belongsTo(OwnerBankAccount::class, 'owner_bank_account_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function wallet()
    {
        return $this->belongsTo(OwnerWallet::class, 'owner_wallet_id');
    }
}
