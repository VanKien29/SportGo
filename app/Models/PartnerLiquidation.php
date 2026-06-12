<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerLiquidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_contract_id',
        'termination_request_id',
        'file_path',
        'status',
    ];

    public function contract()
    {
        return $this->belongsTo(PartnerContract::class, 'partner_contract_id');
    }

    public function terminationRequest()
    {
        return $this->belongsTo(PartnerTerminationRequest::class, 'termination_request_id');
    }
}
