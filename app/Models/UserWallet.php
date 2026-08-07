<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'locked_balance' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(UserWithdrawalRequest::class, 'user_wallet_id');
    }

    public function ledgers()
    {
        return $this->hasMany(UserWalletLedger::class, 'user_wallet_id')->latest('created_at');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_wallet_id');
    }
}
