<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformFeeNotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'event_type',
        'event_revision',
        'plan_version_id',
        'ledger_id',
        'arrangement_id',
        'recipient_user_id',
        'channel',
        'destination',
        'title',
        'body',
        'action_url',
        'status',
        'attempts',
        'last_error',
        'queued_at',
        'sent_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_revision' => 'integer',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
