<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_application_id',
        'contract_template_id',
        'contract_number',
        'status',
        'generated_file_path',
        'final_signed_file_path',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\ContractStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function signatures()
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function liquidation()
    {
        return $this->hasOne(PartnerLiquidation::class);
    }
}

