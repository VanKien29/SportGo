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
        'auto_hide_score',
        'admin_alert_score',
        'score_window_days',
        'score_reset_days',
        'action_type',
        'duration_days',
    ];

    protected function casts(): array
    {
        return [
            'auto_hide_score' => 'integer',
            'admin_alert_score' => 'integer',
            'score_window_days' => 'integer',
            'score_reset_days' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(SystemPolicy::class, 'system_policy_id');
    }
}
