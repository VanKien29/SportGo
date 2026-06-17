<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLockPolicy extends Model
{
    protected $fillable = [
        'auto_lock_enabled',
        'report_threshold',
        'lock_duration_hours',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'auto_lock_enabled' => 'boolean',
            'report_threshold' => 'integer',
            'lock_duration_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Lấy policy đang active, nếu không có trả về null.
     */
    public static function getActive(): ?self
    {
        return static::query()->where('is_active', true)->latest()->first();
    }
}
