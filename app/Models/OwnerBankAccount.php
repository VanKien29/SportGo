<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerBankAccount extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'partner_application_id',
        'bank_name',
        'bank_code',
        'account_number',
        'account_holder_name',
        'branch_name',
        'status',
        'is_default',
        'verified_by',
        'verified_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function partnerApplication()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
