<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueCourt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'court_type_id',
        'name',
        'status',
        'sort_order',
        'layout_x',
        'layout_y',
        'layout_w',
        'layout_h',
        'layout_rotation',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'sort_order' => 'integer',
            'layout_x' => 'double',
            'layout_y' => 'double',
            'layout_w' => 'double',
            'layout_h' => 'double',
            'layout_rotation' => 'integer',
        ];
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }
}
