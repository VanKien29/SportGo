<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationRequest extends Model
{
    use HasFactory, HasUuids;

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
        'requested_effective_date',
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
            'approved_at' => 'datetime',
        ];
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
