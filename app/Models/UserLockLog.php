<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLockLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'reason',
        'locked_by',
        'auto_triggered',
        'lock_until',
        'policy_snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_triggered' => 'boolean',
            'lock_until' => 'datetime',
            'policy_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * User bị khóa/mở khóa.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Admin thực hiện thao tác (NULL nếu tự động).
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
