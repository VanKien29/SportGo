<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyEvaluationLog extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'system_policy_id',
        'policy_rule_id',
        'venue_policy_rule_id',
        'action_code',
        'entity_type',
        'entity_id',
        'input_data',
        'result_data',
        'policy_version_snapshot',
        'rule_snapshot',
        'evaluated_by_type',
        'evaluated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'result_data' => 'array',
            'policy_version_snapshot' => 'array',
            'rule_snapshot' => 'array',
            'created_at' => 'datetime',
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

    public function venueRule()
    {
        return $this->belongsTo(VenuePolicyRule::class, 'venue_policy_rule_id');
    }
}
