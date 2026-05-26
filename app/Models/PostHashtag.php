<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostHashtag extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'hashtag_id',
        'post_type',
        'post_id',
    ];

    protected function casts(): array
    {
        return [
            'hashtag_id' => 'integer',
        ];
    }

    public function hashtag()
    {
        return $this->belongsTo(Hashtag::class, 'hashtag_id');
    }
}
