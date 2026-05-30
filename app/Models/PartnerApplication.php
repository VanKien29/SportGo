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
        'business_name',
        'tax_code',
        'venue_name',
        'venue_address',
        'venue_map_url',
        'venue_latitude',
        'venue_longitude',
        'status',
        'reviewed_by',
        'status_reason',
        'approved_venue_cluster_id',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_latitude' => 'decimal:7',
            'venue_longitude' => 'decimal:7',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bankAccounts()
    {
        return $this->hasMany(OwnerBankAccount::class, 'partner_application_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
