<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VnProvince extends Model
{
    use HasFactory;

    protected $table = 'vn_provinces';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'codename',
        'division_type',
        'phone_code',
    ];

    public function wards()
    {
        return $this->hasMany(VnWard::class, 'province_code', 'code');
    }
}
