<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueAffiliatePost extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'author_id',
        'title',
        'description',
        'price',
        'affiliate_platform',
        'affiliate_url',
        'category',
        'status',
        'reviewed_by',
        'reviewed_at',
        'status_reason',
        'view_count',
        'click_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'view_count' => 'integer',
            'click_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
