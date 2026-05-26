<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueStaffAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'venue_cluster_id',
        'scope_type',
        'court_type_id',
        'scope_key',
        'assigned_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
        ];
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
