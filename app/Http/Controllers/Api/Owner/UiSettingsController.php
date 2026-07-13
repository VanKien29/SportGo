<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UiSettingsController extends Controller
{
    private function settingKey(Request $request): string
    {
        return 'owner_ui_settings_' . $request->user()->id;
    }

    private function defaultSettings(): array
    {
        return [
            'active_theme_id' => 'owner-zinc',
            'sidebar_style' => 'one-level',
            'radius' => '8px',
            'presets' => [
                [
                    'id' => 'owner-zinc',
                    'name' => 'Zinc',
                    'color' => '#18181b',
                    'light' => ['primary' => '#18181b', 'secondary' => '#27272a', 'accent' => '#f4f4f5', 'muted' => '#71717a', 'destructive' => '#ef4444', 'border' => '#e4e4e7', 'card' => '#ffffff', 'background' => '#fafafa'],
                    'dark' => ['primary' => '#fafafa', 'secondary' => '#27272a', 'accent' => '#27272a', 'muted' => '#a1a1aa', 'destructive' => '#ef4444', 'border' => '#27272a', 'card' => '#09090b', 'background' => '#09090b'],
                ],
            ],
            'custom_themes' => [],
        ];
    }

    public function getSettings(Request $request): JsonResponse
    {
        $setting = SystemSetting::query()->firstOrCreate(
            ['key' => $this->settingKey($request)],
            ['value' => $this->defaultSettings()]
        );

        $value = array_replace_recursive($this->defaultSettings(), $setting->value ?? []);
        $value['presets'] = $this->defaultSettings()['presets'];

        return response()->json($value);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active_theme_id' => ['required', 'string', 'max:80'],
            'sidebar_style' => ['required', 'string', Rule::in(['one-level', 'two-level'])],
            'radius' => ['required', 'string', Rule::in(['0px', '4px', '8px', '12px', '16px'])],
            'presets' => ['nullable', 'array'],
            'custom_themes' => ['nullable', 'array'],
        ]);

        $payload = array_replace_recursive($this->defaultSettings(), $data);

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $this->settingKey($request)],
            ['value' => $payload]
        );

        return response()->json([
            'message' => 'Cập nhật cấu hình giao diện chủ sân thành công.',
            'data' => $setting->value,
        ]);
    }
}
