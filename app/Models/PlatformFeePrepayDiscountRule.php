<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeePrepayDiscountRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_version_id',
        'months',
        'discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function planVersion()
    {
        return $this->belongsTo(PlatformFeePlanVersion::class, 'plan_version_id');
    }
}
