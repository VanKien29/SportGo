<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueStaffShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'name',
        'start_time',
        'end_time',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function schedules()
    {
        return $this->hasMany(VenueStaffShiftSchedule::class, 'venue_staff_shift_id');
    }
}
