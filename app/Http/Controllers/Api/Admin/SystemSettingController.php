<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    private const FIELDS = [
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

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsPayload(),
            'meta' => [
                'fields' => self::FIELDS,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_short_name' => ['nullable', 'string', 'max:120'],
            'representative_name' => ['required', 'string', 'max:255'],
            'representative_title' => ['nullable', 'string', 'max:150'],
            'company_address' => ['required', 'string', 'max:1000'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'business_code' => ['nullable', 'string', 'max:100'],
            'business_license_number' => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'website_url' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:1000'],
            'favicon_url' => ['nullable', 'string', 'max:1000'],
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'favicon_file' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:512'],
        ], [
            'system_name.required' => 'Vui lòng nhập tên hệ thống.',
            'company_name.required' => 'Vui lòng nhập tên công ty.',
            'representative_name.required' => 'Vui lòng nhập tên người đại diện.',
            'company_address.required' => 'Vui lòng nhập địa chỉ công ty.',
            'support_email.email' => 'Email hỗ trợ không hợp lệ.',
            'logo_file.mimes' => 'Logo chỉ hỗ trợ JPG, PNG, WEBP hoặc SVG.',
            'favicon_file.mimes' => 'Favicon chỉ hỗ trợ ICO, PNG hoặc SVG.',
        ]);

        if ($request->hasFile('logo_file')) {
            $data['logo_url'] = Storage::url($request->file('logo_file')->store('system', 'public'));
        }

        if ($request->hasFile('favicon_file')) {
            $data['favicon_url'] = Storage::url($request->file('favicon_file')->store('system', 'public'));
        }

        foreach (self::FIELDS as $key => $meta) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => trim((string) ($data[$key] ?? '')),
                    'type' => 'string',
                    'group' => $meta['group'],
                    'label' => $meta['label'],
                ],
            );
        }

        return response()->json([
            'message' => 'Đã lưu thông tin hệ thống.',
            'data' => $this->settingsPayload(),
        ]);
    }

    private function settingsPayload(): array
    {
        $stored = SystemSetting::query()
            ->whereIn('key', array_keys(self::FIELDS))
            ->get()
            ->keyBy('key');

        $payload = [];

        foreach (self::FIELDS as $key => $meta) {
            $payload[$key] = $stored->get($key)?->value ?? $meta['default'];
        }

        return $payload;
    }
}
