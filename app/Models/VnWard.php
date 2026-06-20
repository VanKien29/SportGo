<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VnWard extends Model
{
    use HasFactory;

    protected $table = 'vn_wards';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'codename',
        'division_type',
        'province_code',
    ];

    public function province()
    {
        return $this->belongsTo(VnProvince::class, 'province_code', 'code');
    }
}
