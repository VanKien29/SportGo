<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationThreshold extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_policy_id',
        'target_type',
        'warning_threshold',
        'action_threshold',
        'unique_reporters_threshold',
        'timeframe_days',
    ];

    protected function casts(): array
    {
        return [
            'warning_threshold' => 'integer',
            'action_threshold' => 'integer',
            'unique_reporters_threshold' => 'integer',
            'timeframe_days' => 'integer',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(SystemPolicy::class, 'system_policy_id');
    }
}
