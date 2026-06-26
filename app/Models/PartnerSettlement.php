<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerSettlement extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'settlement_code',
        'partner_termination_request_id',
        'partner_contract_id',
        'owner_id',
        'venue_cluster_id',
        'owner_wallet_available_amount',
        'owner_wallet_pending_amount',
        'platform_fee_remaining_refund_amount',
        'unpaid_platform_fee_amount',
        'penalty_amount',
        'adjustment_amount',
        'final_payable_to_owner',
        'final_receivable_from_owner',
        'status',
        'calculated_by',
        'approved_by',
        'approved_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'owner_wallet_available_amount' => 'decimal:2',
            'owner_wallet_pending_amount' => 'decimal:2',
            'platform_fee_remaining_refund_amount' => 'decimal:2',
            'unpaid_platform_fee_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'final_payable_to_owner' => 'decimal:2',
            'final_receivable_from_owner' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(PartnerSettlementItem::class, 'partner_settlement_id');
    }

    public function request()
    {
        return $this->belongsTo(PartnerTerminationRequest::class, 'partner_termination_request_id');
    }

    public function contract()
    {
        return $this->belongsTo(PartnerContract::class, 'partner_contract_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(OwnerWithdrawalRequest::class, 'partner_settlement_id');
    }
}
