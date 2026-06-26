<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemWalletLedger extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'system_bank_account_id',
        'transaction_ref',
        'direction',
        'entry_kind',
        'amount',
        'balance_before',
        'balance_after',
        'refund_reserved_before',
        'refund_reserved_after',
        'voucher_reserved_before',
        'voucher_reserved_after',
        'transaction_type',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
        'transacted_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'refund_reserved_before' => 'decimal:2',
            'refund_reserved_after' => 'decimal:2',
            'voucher_reserved_before' => 'decimal:2',
            'voucher_reserved_after' => 'decimal:2',
            'metadata' => 'array',
            'transacted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function systemBankAccount()
    {
        return $this->belongsTo(SystemBankAccount::class, 'system_bank_account_id');
    }
}
