<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'status_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function venueClusterAmenities()
    {
        return $this->hasMany(VenueClusterAmenity::class);
    }

    public function venueClusters()
    {
        return $this->belongsToMany(VenueCluster::class, 'venue_cluster_amenities')
            ->withPivot(['description', 'is_visible'])
            ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
