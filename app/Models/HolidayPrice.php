<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayPrice extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'court_type_id',
        'date_type',
        'booking_type',
        'holiday_date',
        'start_time',
        'end_time',
        'price',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'holiday_date' => 'date',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
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
