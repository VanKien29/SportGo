<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'value_type',
        'description',
    ];

    public static function integer(string $key, int $default): int
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return (int) $setting->value;
    }
}
