<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeAutomationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_run_id',
        'venue_cluster_id',
        'ledger_id',
        'result',
        'reason',
        'snapshot',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
