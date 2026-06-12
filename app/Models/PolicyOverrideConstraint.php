<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyOverrideConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_policy_id',
        'policy_rule_id',
        'rule_code',
        'constraint_key',
        'constraint_name',
        'comparison_direction',
        'min_value',
        'max_value',
        'allowed_values',
        'message_vi',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'allowed_values' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(SystemPolicy::class, 'system_policy_id');
    }

    public function rule()
    {
        return $this->belongsTo(PolicyRule::class, 'policy_rule_id');
    }
}
