<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'venue_cluster_id',
        'ledger_id',
        'type',
        'amount',
        'status',
        'reason',
        'evidence_reference',
        'owner_wallet_ledger_id',
        'created_by',
        'approved_by',
        'applied_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
