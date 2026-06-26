<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_courts',
        'max_courts',
        'price_per_court_month',
        'annual_discount_percent',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'min_courts' => 'integer',
            'max_courts' => 'integer',
            'price_per_court_month' => 'decimal:2',
            'annual_discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    public function ledgers()
    {
        return $this->hasMany(VenuePlatformFeeLedger::class, 'tier_id');
    }
}
