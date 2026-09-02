<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'full_name',
        'phone',
        'email',
        'google_id',
        'email_verified_at',
        'phone_verified_at',
        'password',
        'password_set_at',
        'avatar_url',
        'cover_image_url',
        'bio',
        'preferred_sports',
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
            'password_set_at' => 'datetime',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_locked' => 'boolean',
            'password' => 'hashed',
            'preferred_sports' => 'array',
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
        $ownerRoles = ['venue_owner'];
        $staffRoles = ['venue_staff'];

        if (array_intersect($roles, $adminRoles)) {
            return 'admin';
        }

        if (array_intersect($roles, $ownerRoles)) {
            return 'owner';
        }

        if (array_intersect($roles, $staffRoles)) {
            return 'staff';
        }

        return 'user';
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->password_set_at === null && empty($user->google_id) && filled($user->password)) {
                $user->password_set_at = now();
            }
        });

        static::updated(function (User $user): void {
            if ($user->wasChanged('status') && in_array($user->status, ['locked', 'deactivated'], true)) {
                $user->revokeAllAccess();
            }
        });

        static::created(function (User $user) {
            $role = Role::query()->where('name', 'user')->first();
            if ($role) {
                UserRole::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'scope_type' => 'system',
                    'scope_id' => 0,
                ]);
            }
        });
    }

    /**
     * Revoke every API token and database-backed browser session for this user.
     */
    public function revokeAllAccess(): void
    {
        $this->tokens()->delete();

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $this->getKey())->delete();
        }
    }
}
