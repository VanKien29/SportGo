<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemWalletBalance extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'system_bank_account_id',
        'current_balance',
        'last_synced_at',
        'alert_threshold',
        'is_alert_enabled',
        'last_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'last_synced_at' => 'datetime',
            'alert_threshold' => 'decimal:2',
            'is_alert_enabled' => 'boolean',
            'last_alerted_at' => 'datetime',
        ];
    }

    public function systemBankAccount()
    {
        return $this->belongsTo(SystemBankAccount::class, 'system_bank_account_id');
    }
}
