<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    use HasFactory;

    public const PROFILE_FIELDS = [
        'system_name' => [
            'label' => 'Tên hệ thống',
            'group' => 'identity',
            'default' => 'SportGo',
        ],
        'company_name' => [
            'label' => 'Tên công ty',
            'group' => 'legal',
            'default' => 'Công ty SportGo',
        ],
        'company_short_name' => [
            'label' => 'Tên viết tắt',
            'group' => 'identity',
            'default' => 'SportGo',
        ],
        'representative_name' => [
            'label' => 'Người đại diện',
            'group' => 'legal',
            'default' => '',
        ],
        'representative_title' => [
            'label' => 'Chức vụ người đại diện',
            'group' => 'legal',
            'default' => '',
        ],
        'company_address' => [
            'label' => 'Địa chỉ công ty',
            'group' => 'legal',
            'default' => '',
        ],
        'tax_code' => [
            'label' => 'Mã số thuế',
            'group' => 'legal',
            'default' => '',
        ],
        'business_code' => [
            'label' => 'Mã số kinh doanh',
            'group' => 'legal',
            'default' => '',
        ],
        'business_license_number' => [
            'label' => 'Số giấy phép kinh doanh',
            'group' => 'legal',
            'default' => '',
        ],
        'support_email' => [
            'label' => 'Email hỗ trợ',
            'group' => 'contact',
            'default' => '',
        ],
        'support_phone' => [
            'label' => 'Số điện thoại hỗ trợ',
            'group' => 'contact',
            'default' => '',
        ],
        'website_url' => [
            'label' => 'Website',
            'group' => 'contact',
            'default' => '',
        ],
        'logo_url' => [
            'label' => 'Logo hệ thống',
            'group' => 'identity',
            'default' => '',
        ],
        'favicon_url' => [
            'label' => 'Favicon',
            'group' => 'identity',
            'default' => '',
        ],
    ];

    protected $fillable = [
        'key',
        'value',
        'type',
        'value_type',
        'group',
        'label',
        'description',
    ];

    public function getValueAttribute($value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    public static function profilePayload(): array
    {
        $payload = [];
        $settings = collect();

        if (Schema::hasTable((new static())->getTable())) {
            $settings = static::query()
                ->whereIn('key', array_keys(self::PROFILE_FIELDS))
                ->get()
                ->keyBy('key');
        }

        foreach (self::PROFILE_FIELDS as $key => $meta) {
            $value = $settings->get($key)?->value;
            $payload[$key] = is_scalar($value) && (string) $value !== ''
                ? (string) $value
                : ($meta['default'] ?? '');
        }

        return $payload;
    }

    public static function documentProfilePayload(): array
    {
        $profile = self::profilePayload();
        $companyName = trim((string) ($profile['company_name'] ?? ''));
        $taxCode = trim((string) (($profile['tax_code'] ?? '') ?: ($profile['business_code'] ?? '')));
        $representativeName = trim((string) ($profile['representative_name'] ?? ''));
        $representativeTitle = trim((string) ($profile['representative_title'] ?? ''));

        return array_filter([
            'sportgo_company_name' => $companyName,
            'sportgo_tax_code' => $taxCode,
            'sportgo_address' => trim((string) ($profile['company_address'] ?? '')),
            'sportgo_representative_name' => $representativeName,
            'sportgo_representative' => $representativeName,
            'sportgo_representative_title' => $representativeTitle,
            'sportgo_representative_position' => $representativeTitle,
            'sportgo_authorization_basis' => $representativeName !== ''
                ? 'Người đại diện theo pháp luật'
                : '',
            'sportgo_phone' => trim((string) ($profile['support_phone'] ?? '')),
            'sportgo_email' => trim((string) ($profile['support_email'] ?? '')),
            'sportgo_website' => trim((string) ($profile['website_url'] ?? '')),
            'sportgo_logo_url' => trim((string) ($profile['logo_url'] ?? '')),
            'sportgo_business_license_number' => trim((string) ($profile['business_license_number'] ?? '')),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public static function upsertProfileValue(string $key, mixed $value, array $meta = []): void
    {
        if (! Schema::hasTable((new static())->getTable())) {
            return;
        }

        $attributes = [
            'value' => trim((string) $value),
        ];

        if (Schema::hasColumn((new static())->getTable(), 'type')) {
            $attributes['type'] = 'string';
        }

        if (Schema::hasColumn((new static())->getTable(), 'value_type')) {
            $attributes['value_type'] = 'string';
        }

        if (Schema::hasColumn((new static())->getTable(), 'group')) {
            $attributes['group'] = $meta['group'] ?? 'general';
        }

        if (Schema::hasColumn((new static())->getTable(), 'label')) {
            $attributes['label'] = $meta['label'] ?? $key;
        }

        static::query()->updateOrCreate(['key' => $key], $attributes);
    }

    public static function integer(string $key, int $default): int
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return (int) $setting->value;
    }

}
