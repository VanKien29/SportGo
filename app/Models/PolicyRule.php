<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyRule extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'system_policy_id',
        'action_code',
        'rule_code',
        'rule_name',
        'rule_type',
        'decision_key',
        'conflict_group',
        'condition_json',
        'result_json',
        'constraint_json',
        'allowed_override_json',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'result_json' => 'array',
            'constraint_json' => 'array',
            'allowed_override_json' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(SystemPolicy::class, 'system_policy_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function venuePolicyRules()
    {
        return $this->hasMany(VenuePolicyRule::class, 'base_policy_rule_id');
    }
}
