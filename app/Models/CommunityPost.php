<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_id',
        'content',
        'status',
        'reviewed_by',
        'reviewed_at',
        'status_reason',
        'view_count',
        'like_count',
        'comment_count',
        'edited_at',
        'edit_count',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'edited_at' => 'datetime',
            'edit_count' => 'integer',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::deleting(function ($post) {
            if (method_exists($post, 'isForceDeleting') && $post->isForceDeleting()) {
                // post_hashtags is a logical polymorphic pivot without a post FK.
                $post->hashtags()->detach();

                // Cascade delete comments
                $post->comments()->delete();

                // Cascade delete likes
                $post->likes()->delete();

                // Cascade delete reports
                Report::where('reportable_type', self::class)
                    ->where('reportable_id', $post->id)
                    ->delete();
            }
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtags', 'post_id', 'hashtag_id')
            ->withPivotValue('post_type', 'community_posts');
    }

    public function comments()
    {
        return $this->hasMany(CommunityPostComment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }
}
