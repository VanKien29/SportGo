<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueClusterAmenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_cluster_id',
        'amenity_id',
        'description',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }
}
