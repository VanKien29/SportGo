<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'system_policies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'version',
        'title',
        'content',
        'type',
        'policy_type',
        'status',
        'is_active',
        'is_overridable',
        'priority',
        'effective_from',
        'effective_to',
        'published_at',
        'published_by',
        'replaced_policy_id',
        'require_reaccept',
        'change_summary',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'is_overridable' => 'boolean',
            'priority' => 'integer',
            'require_reaccept' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function replacedPolicy()
    {
        return $this->belongsTo(self::class, 'replaced_policy_id');
    }

    public function actionBindings()
    {
        return $this->hasMany(PolicyActionBinding::class, 'system_policy_id');
    }

    public function rules()
    {
        return $this->hasMany(PolicyRule::class, 'system_policy_id');
    }

    public function evaluationLogs()
    {
        return $this->hasMany(PolicyEvaluationLog::class, 'system_policy_id');
    }
}
