<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWalletLedger extends Model
{
    use HasFactory;

    protected $table = 'user_wallet_ledgers';

    protected $fillable = [
        'user_wallet_id',
        'transaction_code',
        'type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
