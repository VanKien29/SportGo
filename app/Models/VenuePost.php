<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenuePost extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'venue_cluster_id',
        'author_id',
        'title',
        'slug',
        'content',
        'short_description',
        'meta_title',
        'meta_description',
        'post_type',
        'valid_from',
        'valid_to',
        'status',
        'reviewed_by',
        'reviewed_at',
        'status_reason',
        'view_count',
        'like_count',
        'comment_count',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'reviewed_at' => 'datetime',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function venueCluster()
    {
        return $this->belongsTo(VenueCluster::class, 'venue_cluster_id');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtags', 'post_id', 'hashtag_id')
            ->where('post_hashtags.post_type', 'venue_posts');
    }

    public function comments()
    {
        return $this->hasMany(VenuePostComment::class, 'venue_post_id')->where('status', 'published')->latest();
    }

    public function topLevelComments()
    {
        return $this->hasMany(VenuePostComment::class, 'venue_post_id')
            ->where('status', 'published')
            ->whereNull('parent_id')
            ->latest();
    }
}
