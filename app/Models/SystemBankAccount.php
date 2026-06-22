<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemBankAccount extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'bank_name',
        'bank_code',
        'account_number',
        'account_holder_name',
        'status',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'system_bank_account_id');
    }

    public function platformFeeLedgers()
    {
        return $this->hasMany(VenuePlatformFeeLedger::class, 'system_bank_account_id');
    }
}
