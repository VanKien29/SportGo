<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'status',
        'description',
    ];

    /**
     * Quan hệ với danh sách các dịch vụ tại cụm sân thuộc danh mục này
     */
    public function services(): HasMany
    {
        return $this->hasMany(VenueClusterService::class, 'category_id');
    }
}
