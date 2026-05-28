<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermissionRevoke extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'permission_id',
        'scope_type',
        'scope_id',
        'revoked_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'permission_id' => 'integer',
        ];
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
