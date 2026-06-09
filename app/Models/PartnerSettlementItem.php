<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerSettlementItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'partner_settlement_id',
        'item_type',
        'description',
        'amount',
        'direction',
        'reference_type',
        'reference_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function settlement()
    {
        return $this->belongsTo(PartnerSettlement::class, 'partner_settlement_id');
    }
}
