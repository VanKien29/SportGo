<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenuePostComment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'venue_post_id',
        'user_id',
        'parent_id',
        'content',
        'status',
    ];

    public function post()
    {
        return $this->belongsTo(VenuePost::class, 'venue_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies()
    {
        return $this->hasMany(VenuePostComment::class, 'parent_id')->latest();
    }

    public function parent()
    {
        return $this->belongsTo(VenuePostComment::class, 'parent_id');
    }
}
