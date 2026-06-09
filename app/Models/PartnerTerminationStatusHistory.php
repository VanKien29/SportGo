<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'partner_termination_request_id',
        'old_status',
        'new_status',
        'changed_by',
        'actor_type',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request()
    {
        return $this->belongsTo(PartnerTerminationRequest::class, 'partner_termination_request_id');
    }
}
