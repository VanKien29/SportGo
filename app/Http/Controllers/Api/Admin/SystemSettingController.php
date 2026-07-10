<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => SystemSetting::profilePayload(),
            'meta' => [
                'fields' => SystemSetting::PROFILE_FIELDS,
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
            'favicon_file' => ['nullable', 'file', 'mimes:ico,jpg,jpeg,png,webp,svg', 'max:512'],
        ], [
            'system_name.required' => 'Vui lòng nhập tên hệ thống.',
            'company_name.required' => 'Vui lòng nhập tên công ty.',
            'representative_name.required' => 'Vui lòng nhập tên người đại diện.',
            'company_address.required' => 'Vui lòng nhập địa chỉ công ty.',
            'support_email.email' => 'Email hỗ trợ không hợp lệ.',
            'logo_file.mimes' => 'Logo chỉ hỗ trợ JPG, PNG, WEBP hoặc SVG.',
            'favicon_file.mimes' => 'Favicon chỉ hỗ trợ ICO, JPG, PNG, WEBP hoặc SVG.',
        ]);

        if ($request->hasFile('logo_file')) {
            $data['logo_url'] = Storage::url($request->file('logo_file')->store('system', 'public'));
        }

        if ($request->hasFile('favicon_file')) {
            $data['favicon_url'] = Storage::url($request->file('favicon_file')->store('system', 'public'));
        }

        foreach (SystemSetting::PROFILE_FIELDS as $key => $meta) {
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
            'data' => SystemSetting::profilePayload(),
        ]);
    }
}
