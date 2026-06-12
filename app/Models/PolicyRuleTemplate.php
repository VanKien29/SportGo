<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyRuleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_type',
        'rule_code',
        'rule_name',
        'description',
        'action_code',
        'condition_schema',
        'result_schema',
        'is_venue_overridable',
        'risk_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'condition_schema' => 'array',
            'result_schema' => 'array',
            'is_venue_overridable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
