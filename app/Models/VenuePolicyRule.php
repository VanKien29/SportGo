<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePolicyRule extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'base_policy_rule_id',
        'action_code',
        'rule_code',
        'rule_name',
        'rule_type',
        'condition_json',
        'result_json',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'created_by',
        'updated_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'effective_from',
        'effective_to',
        'constraint_check_result',
    ];

    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'result_json' => 'array',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'constraint_check_result' => 'array',
        ];
    }

    public function baseRule()
    {
        return $this->belongsTo(PolicyRule::class, 'base_policy_rule_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
