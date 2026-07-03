<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintReply extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'complaint_id',
        'user_id',
        'content',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evidence()
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
