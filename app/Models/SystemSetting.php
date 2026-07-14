<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public const PROFILE_FIELDS = [
        'system_name' => ['group' => 'identity', 'label' => 'Tên hệ thống'],
        'company_name' => ['group' => 'legal', 'label' => 'Tên công ty'],
        'company_short_name' => ['group' => 'identity', 'label' => 'Tên viết tắt'],
        'representative_name' => ['group' => 'legal', 'label' => 'Người đại diện'],
        'representative_title' => ['group' => 'legal', 'label' => 'Chức vụ'],
        'company_address' => ['group' => 'legal', 'label' => 'Địa chỉ công ty'],
        'tax_code' => ['group' => 'legal', 'label' => 'Mã số thuế'],
        'business_code' => ['group' => 'legal', 'label' => 'Mã số kinh doanh'],
        'business_license_number' => ['group' => 'legal', 'label' => 'Số giấy phép kinh doanh'],
        'support_email' => ['group' => 'contact', 'label' => 'Email hỗ trợ'],
        'support_phone' => ['group' => 'contact', 'label' => 'Số điện thoại hỗ trợ'],
        'website_url' => ['group' => 'contact', 'label' => 'Website'],
        'logo_url' => ['group' => 'identity', 'label' => 'Logo URL'],
        'favicon_url' => ['group' => 'identity', 'label' => 'Favicon URL'],
    ];

    public static function profilePayload(): array
    {
        $keys = array_keys(self::PROFILE_FIELDS);
        
        $settings = static::query()
            ->whereIn('key', $keys)
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        $payload = [];
        foreach ($keys as $key) {
            $payload[$key] = $settings[$key] ?? '';
        }

        return $payload;
    }

    public static function integer(string $key, int $default): int
    {
        if (! Schema::hasTable((new static())->getTable())) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return (int) $setting->value;
    }
}
