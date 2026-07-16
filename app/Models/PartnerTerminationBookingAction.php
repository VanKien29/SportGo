<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerTerminationBookingAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_termination_request_id',
        'booking_id',
        'action',
        'status',
        'paid_online_amount',
        'refund_id',
        'processed_by',
        'processed_at',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'paid_online_amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function request()
    {
        return $this->belongsTo(PartnerTerminationRequest::class, 'partner_termination_request_id');
    }
}
