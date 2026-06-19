<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerApplication extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'applicant_full_name',
        'applicant_phone',
        'applicant_email',
        'applicant_birth_date',
        'applicant_address',
        'applicant_type',
        'representative_name',
        'representative_identity_type',
        'representative_identity_number',
        'representative_identity_issued_date',
        'representative_identity_issued_place',
        'representative_position',
        'business_name',
        'business_code',
        'tax_code',
        'business_license_number',
        'business_address',
        'business_representative_name',
        'business_representative_position',
        'venue_name',
        'venue_address',
        'venue_province',
        'venue_district',
        'venue_ward',
        'venue_map_url',
        'venue_latitude',
        'venue_longitude',
        'venue_phone',
        'venue_email',
        'venue_description',
        'expected_opening_hours',
        'parking_info',
        'amenities',
        'court_count_total',
        'base_price_per_hour',
        'bank_name',
        'bank_code',
        'account_number',
        'account_holder_name',
        'bank_branch',
        'bank_verification_status',
        'bank_verified_at',
        'status',
        'reviewed_by',
        'status_reason',
        'approved_venue_cluster_id',
        'current_contract_id',
        'submitted_at',
        'reviewed_at',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',
            'applicant_birth_date' => 'date',
            'representative_identity_issued_date' => 'date',
            'amenities' => 'array',
            'court_count_total' => 'integer',
            'base_price_per_hour' => 'integer',
            'submitted_at' => 'datetime',
            'bank_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedVenueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'approved_venue_cluster_id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(OwnerBankAccount::class, 'partner_application_id');
    }

    public function contracts()
    {
        return $this->hasMany(PartnerContract::class, 'partner_application_id');
    }

    public function courts()
    {
        return $this->hasMany(PartnerApplicationCourt::class, 'partner_application_id');
    }

    public function documents()
    {
        return $this->hasMany(PartnerApplicationDocument::class, 'partner_application_id');
    }

    public function generatedDocuments()
    {
        return $this->hasMany(GeneratedDocument::class, 'partner_application_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(PartnerApplicationStatusHistory::class, 'partner_application_id');
    }

    public function terminationRequests()
    {
        return $this->hasMany(PartnerTerminationRequest::class, 'partner_application_id');
    }

    public function liquidations()
    {
        return $this->hasManyThrough(
            PartnerLiquidation::class,
            PartnerContract::class,
            'partner_application_id',
            'partner_contract_id',
            'id',
            'id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
