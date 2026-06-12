<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalReceipt extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'receipt_code',
        'receipt_type',
        'receiptable_type',
        'receiptable_id',
        'issued_to_user_id',
        'issued_by',
        'title',
        'amount',
        'currency',
        'status',
        'issued_at',
        'cancelled_at',
        'cancel_reason',
        'file_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function receiptable()
    {
        return $this->morphTo();
    }
}
