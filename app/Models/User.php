<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'username',
        'full_name',
        'phone',
        'email',
        'google_id',
        'email_verified_at',
        'phone_verified_at',
        'password',
        'avatar_url',
        'bio',
        'status',
        'is_locked',
        'verification_channel',
        'lock_type',
        'status_reason',
        'locked_at',
        'locked_until',
        'locked_by',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_locked' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function lockLogs()
    {
        return $this->hasMany(UserLockLog::class, 'user_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(self::class, 'locked_by');
    }

    public function lockedUsers()
    {
        return $this->hasMany(self::class, 'locked_by');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['scope_type', 'scope_id', 'granted_by']);
    }

    public function getRoleGroupAttribute(): string
    {
        $roles = $this->roles->pluck('name')->all();
        $adminRoles = [
            'super_admin',
            'admin',
            'system_staff',
            'content_moderator',
            'complaint_handler',
            'venue_manager',
            'partner_manager',
            'booking_support',
            'finance_operator',
            'policy_manager',
            'staff_manager',
        ];
        $ownerRoles = ['venue_owner', 'venue_staff'];

        if (array_intersect($roles, $adminRoles)) {
            return 'admin';
        }

        if (array_intersect($roles, $ownerRoles)) {
            return 'owner';
        }

        return 'user';
    }
}

