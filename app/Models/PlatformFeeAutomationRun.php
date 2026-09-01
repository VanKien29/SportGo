<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeAutomationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_code',
        'job_type',
        'as_of_date',
        'dry_run',
        'status',
        'scanned_count',
        'created_count',
        'skipped_count',
        'failed_count',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function results()
    {
        return $this->hasMany(PlatformFeeAutomationResult::class, 'automation_run_id');
    }
}
