<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    public const PROFILE_FIELDS = [
        'system_name' => ['label' => 'Tên hệ thống', 'group' => 'identity', 'default' => 'SportGo'],
        'company_name' => ['label' => 'Tên công ty', 'group' => 'legal', 'default' => 'Công ty SportGo'],
        'company_short_name' => ['label' => 'Tên viết tắt', 'group' => 'identity', 'default' => 'SportGo'],
        'representative_name' => ['label' => 'Người đại diện', 'group' => 'legal', 'default' => ''],
        'representative_title' => ['label' => 'Chức vụ người đại diện', 'group' => 'legal', 'default' => ''],
        'company_address' => ['label' => 'Địa chỉ công ty', 'group' => 'legal', 'default' => ''],
        'tax_code' => ['label' => 'Mã số thuế', 'group' => 'legal', 'default' => ''],
        'business_code' => ['label' => 'Mã số kinh doanh', 'group' => 'legal', 'default' => ''],
        'business_license_number' => ['label' => 'Số giấy phép kinh doanh', 'group' => 'legal', 'default' => ''],
        'support_email' => ['label' => 'Email hỗ trợ', 'group' => 'contact', 'default' => ''],
        'support_phone' => ['label' => 'Số điện thoại hỗ trợ', 'group' => 'contact', 'default' => ''],
        'website_url' => ['label' => 'Website', 'group' => 'contact', 'default' => ''],
        'logo_url' => ['label' => 'Logo hệ thống', 'group' => 'identity', 'default' => ''],
        'favicon_url' => ['label' => 'Favicon', 'group' => 'identity', 'default' => ''],
    ];

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public static function profilePayload(): array
    {
        $payload = [];

        foreach (self::PROFILE_FIELDS as $key => $meta) {
            $payload[$key] = $meta['default'];
        }

        if (! Schema::hasTable('system_settings')) {
            return $payload;
        }

        $stored = self::query()
            ->whereIn('key', array_keys(self::PROFILE_FIELDS))
            ->get()
            ->keyBy('key');

        foreach (self::PROFILE_FIELDS as $key => $meta) {
            $payload[$key] = $stored->get($key)?->value ?? $meta['default'];
        }

        return $payload;
    }
}
