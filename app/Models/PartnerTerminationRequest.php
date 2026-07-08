<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'termination_code',
        'partner_contract_id',
        'partner_application_id',
        'owner_id',
        'venue_cluster_id',
        'termination_type',
        'requested_by',
        'requested_at',
        'reason',
        'detail_reason',
        'requested_effective_date',
        'future_booking_policy',
        'future_booking_policy_confirmed_at',
        'owner_warning_accepted_at',
        'future_booking_count',
        'owner_balance_total',
        'future_online_booking_liability',
        'pending_refund_liability',
        'pending_withdrawal_amount',
        'withdrawable_amount',
        'future_booking_summary',
        'owner_attachments',
        'admin_locked_owner_cancel',
        'owner_cancel_reason',
        'owner_cancelled_at',
        'owner_cancelled_by',
        'admin_rejected_by',
        'admin_rejected_at',
        'manual_debt_resolved_at',
        'manual_debt_resolved_by',
        'final_document_generated_at',
        'final_document_ready_at',
        'final_document_admin_signed_at',
        'final_document_owner_signed_at',
        'final_document_completed_at',
        'grace_period_days',
        'owner_access_view_until',
        'metadata',
        'status',
        'approved_by',
        'approved_at',
        'reject_reason',
        'effective_termination_date',
        'transition_end_at',
        'owner_access_revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'requested_effective_date' => 'date',
            'future_booking_policy_confirmed_at' => 'datetime',
            'owner_warning_accepted_at' => 'datetime',
            'future_booking_count' => 'integer',
            'owner_balance_total' => 'decimal:2',
            'future_online_booking_liability' => 'decimal:2',
            'pending_refund_liability' => 'decimal:2',
            'pending_withdrawal_amount' => 'decimal:2',
            'withdrawable_amount' => 'decimal:2',
            'future_booking_summary' => 'array',
            'owner_attachments' => 'array',
            'admin_locked_owner_cancel' => 'boolean',
            'owner_cancelled_at' => 'datetime',
            'admin_rejected_at' => 'datetime',
            'manual_debt_resolved_at' => 'datetime',
            'final_document_generated_at' => 'datetime',
            'final_document_ready_at' => 'datetime',
            'final_document_admin_signed_at' => 'datetime',
            'final_document_owner_signed_at' => 'datetime',
            'final_document_completed_at' => 'datetime',
            'grace_period_days' => 'integer',
            'owner_access_view_until' => 'datetime',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'effective_termination_date' => 'datetime',
            'transition_end_at' => 'datetime',
            'owner_access_revoked_at' => 'datetime',
        ];
    }

    public function getTypeAttribute(): ?string
    {
        return $this->termination_type;
    }

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function contract()
    {
        return $this->belongsTo(PartnerContract::class, 'partner_contract_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function documents()
    {
        return $this->hasMany(PartnerTerminationDocument::class, 'partner_termination_request_id');
    }

    public function bookingActions()
    {
        return $this->hasMany(PartnerTerminationBookingAction::class, 'partner_termination_request_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function settlement()
    {
        return $this->hasOne(PartnerSettlement::class, 'partner_termination_request_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(PartnerTerminationStatusHistory::class, 'partner_termination_request_id');
    }
}
