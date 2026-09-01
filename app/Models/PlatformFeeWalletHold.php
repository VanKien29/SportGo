<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeWalletHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_wallet_id',
        'owner_id',
        'venue_cluster_id',
        'ledger_id',
        'arrangement_id',
        'amount',
        'original_amount',
        'remaining_amount',
        'consumed_amount',
        'status',
        'reason',
        'starts_at',
        'released_at',
        'released_by',
        'consumed_at',
        'consumed_by',
        'movement_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'consumed_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(OwnerWallet::class, 'owner_wallet_id');
    }

    public function ledger()
    {
        return $this->belongsTo(VenuePlatformFeeLedger::class, 'ledger_id');
    }

    public function arrangement()
    {
        return $this->belongsTo(PlatformFeePaymentArrangement::class, 'arrangement_id');
    }
}
