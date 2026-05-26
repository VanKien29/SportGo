<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueCluster extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'phone_contact',
        'address',
        'map_url',
        'latitude',
        'longitude',
        'amenities',
        'status',
        'status_reason',
        'locked_at',
        'locked_until',
        'locked_by',
        'rating_avg',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'amenities' => 'array',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
