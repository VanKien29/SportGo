<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeePromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'duration_cycles',
        'applies_to_all_clusters',
        'stackable_with_prepay',
        'budget_amount',
        'spent_amount',
        'starts_at',
        'ends_at',
        'created_by',
        'published_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'duration_cycles' => 'integer',
            'applies_to_all_clusters' => 'boolean',
            'stackable_with_prepay' => 'boolean',
            'budget_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(PlatformFeePromotionAssignment::class, 'promotion_id');
    }
}
