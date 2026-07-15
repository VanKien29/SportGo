<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueStaffShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'user_id',
        'venue_staff_shift_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'check_in_at',
        'check_out_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(VenueStaffShift::class, 'venue_staff_shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
