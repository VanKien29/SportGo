<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPolicyAcceptance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'system_policy_id',
        'policy_version',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function systemPolicy()
    {
        return $this->belongsTo(SystemPolicy::class, 'system_policy_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
