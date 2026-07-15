<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUiSettingsController extends Controller
{
    private const SETTING_KEY = 'admin_ui_settings';

    private function getDefaultSettings(): array
    {
        return [
            'active_theme_id' => 'zinc',
            'sidebar_style' => 'one-level',
            'radius' => '8px',
            'font_size' => '14px',
            'presets' => [
                [
                    'id' => 'zinc',
                    'name' => 'Zinc',
                    'color' => '#18181b',
                    'light' => [
                        'primary' => '#18181b',
                        'secondary' => '#27272a',
                        'accent' => '#f4f4f5',
                        'muted' => '#71717a',
                        'destructive' => '#ef4444',
                        'border' => '#e4e4e7',
                        'card' => '#ffffff',
                        'background' => '#fafafa',
                    ],
                    'dark' => [
                        'primary' => '#fafafa',
                        'secondary' => '#27272a',
                        'accent' => '#27272a',
                        'muted' => '#a1a1aa',
                        'destructive' => '#ef4444',
                        'border' => '#27272a',
                        'card' => '#09090b',
                        'background' => '#09090b',
                    ]
                ]
            ],
            'custom_themes' => []
        ];
    }

    public function getSettings(): JsonResponse
    {
        $setting = SystemSetting::where('key', self::SETTING_KEY)->first();

        if (!$setting) {
            $default = $this->getDefaultSettings();
            $setting = SystemSetting::create([
                'key' => self::SETTING_KEY,
                'value' => $default,
            ]);
        }

        return response()->json($setting->value);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active_theme_id' => ['required', 'string'],
            'sidebar_style' => ['required', 'string'],
            'radius' => ['required', 'string'],
            'font_size' => ['nullable', 'string'],
            'presets' => ['nullable', 'array'],
            'custom_themes' => ['nullable', 'array'],
        ]);

        $setting = SystemSetting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $data]
        );

        return response()->json([
            'message' => 'Cập nhật cấu hình giao diện thành công.',
            'data' => $setting->value,
        ]);
    }
}
