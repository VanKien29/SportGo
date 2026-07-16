<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUiSettingsController extends Controller
{
    private const SETTING_KEY = 'admin_ui_settings';

    private function themePresets(): array
    {
        return [
            [
                'id' => 'sportgo',
                'name' => 'SportGo',
                'color' => '#16a34a',
                'light' => [
                    'primary' => '#16a34a',
                    'secondary' => '#0f766e',
                    'accent' => '#ecfdf5',
                    'muted' => '#64748b',
                    'destructive' => '#ef4444',
                    'border' => '#bbf7d0',
                    'card' => '#ffffff',
                    'background' => '#f6fbf7',
                ],
                'dark' => [
                    'primary' => '#22c55e',
                    'secondary' => '#2dd4bf',
                    'accent' => '#052e16',
                    'muted' => '#94a3b8',
                    'destructive' => '#f87171',
                    'border' => '#164e2f',
                    'card' => '#0f1f17',
                    'background' => '#07130d',
                ],
            ],
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
                ],
            ],
            [
                'id' => 'slate',
                'name' => 'Slate',
                'color' => '#0f172a',
                'light' => [
                    'primary' => '#0f172a',
                    'secondary' => '#1e293b',
                    'accent' => '#e2e8f0',
                    'muted' => '#64748b',
                    'destructive' => '#ef4444',
                    'border' => '#e2e8f0',
                    'card' => '#ffffff',
                    'background' => '#f8fafc',
                ],
                'dark' => [
                    'primary' => '#f8fafc',
                    'secondary' => '#1e293b',
                    'accent' => '#1e293b',
                    'muted' => '#94a3b8',
                    'destructive' => '#ef4444',
                    'border' => '#1e293b',
                    'card' => '#0f172a',
                    'background' => '#020817',
                ],
            ],
            [
                'id' => 'sapphire',
                'name' => 'Sapphire',
                'color' => '#2563eb',
                'light' => [
                    'primary' => '#2563eb',
                    'secondary' => '#0284c7',
                    'accent' => '#f0f9ff',
                    'muted' => '#475569',
                    'destructive' => '#e11d48',
                    'border' => '#bfdbfe',
                    'card' => '#ffffff',
                    'background' => '#f0f6ff',
                ],
                'dark' => [
                    'primary' => '#3b82f6',
                    'secondary' => '#38bdf8',
                    'accent' => '#1e293b',
                    'muted' => '#94a3b8',
                    'destructive' => '#f43f5e',
                    'border' => '#1e3a8a',
                    'card' => '#0f172a',
                    'background' => '#090d16',
                ],
            ],
            [
                'id' => 'amethyst',
                'name' => 'Amethyst',
                'color' => '#7c3aed',
                'light' => [
                    'primary' => '#7c3aed',
                    'secondary' => '#db2777',
                    'accent' => '#f5f3ff',
                    'muted' => '#4b5563',
                    'destructive' => '#dc2626',
                    'border' => '#ddd6fe',
                    'card' => '#ffffff',
                    'background' => '#faf7ff',
                ],
                'dark' => [
                    'primary' => '#8b5cf6',
                    'secondary' => '#ec4899',
                    'accent' => '#2e1065',
                    'muted' => '#9ca3af',
                    'destructive' => '#ef4444',
                    'border' => '#4c1d95',
                    'card' => '#111827',
                    'background' => '#030712',
                ],
            ],
            [
                'id' => 'amber',
                'name' => 'Amber',
                'color' => '#d97706',
                'light' => [
                    'primary' => '#d97706',
                    'secondary' => '#ea580c',
                    'accent' => '#fffbeb',
                    'muted' => '#4b5563',
                    'destructive' => '#dc2626',
                    'border' => '#fde68a',
                    'card' => '#ffffff',
                    'background' => '#fdfbf7',
                ],
                'dark' => [
                    'primary' => '#f59e0b',
                    'secondary' => '#f97316',
                    'accent' => '#451a03',
                    'muted' => '#9ca3af',
                    'destructive' => '#ef4444',
                    'border' => '#78350f',
                    'card' => '#1e1b4b',
                    'background' => '#0c0a09',
                ],
            ],
            [
                'id' => 'rose',
                'name' => 'Rose',
                'color' => '#e11d48',
                'light' => [
                    'primary' => '#e11d48',
                    'secondary' => '#be123c',
                    'accent' => '#fff1f2',
                    'muted' => '#64748b',
                    'destructive' => '#991b1b',
                    'border' => '#fecdd3',
                    'card' => '#ffffff',
                    'background' => '#fff7f8',
                ],
                'dark' => [
                    'primary' => '#fb7185',
                    'secondary' => '#f43f5e',
                    'accent' => '#4c0519',
                    'muted' => '#94a3b8',
                    'destructive' => '#f87171',
                    'border' => '#881337',
                    'card' => '#1f1015',
                    'background' => '#10070a',
                ],
            ],
        ];
    }

    private function getDefaultSettings(): array
    {
        return [
            'active_theme_id' => 'sportgo',
            'sidebar_style' => 'one-level',
            'radius' => '8px',
            'font_size' => '14px',
            'presets' => $this->themePresets(),
            'custom_themes' => []
        ];
    }

    private function mergeSettings(array $stored): array
    {
        $default = $this->getDefaultSettings();
        $settings = array_replace_recursive($default, $stored);
        $presetMap = [];

        foreach ($default['presets'] as $preset) {
            $presetMap[$preset['id']] = $preset;
        }

        foreach (($stored['presets'] ?? []) as $preset) {
            if (isset($preset['id'])) {
                $presetMap[$preset['id']] = array_replace_recursive($presetMap[$preset['id']] ?? [], $preset);
            }
        }

        $settings['presets'] = array_values($presetMap);
        $settings['custom_themes'] = is_array($stored['custom_themes'] ?? null) ? $stored['custom_themes'] : [];

        return $settings;
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

        return response()->json($this->mergeSettings($setting->value ?? []));
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

        $payload = $this->mergeSettings($data);

        $setting = SystemSetting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $payload]
        );

        return response()->json([
            'message' => 'Cập nhật cấu hình giao diện thành công.',
            'data' => $setting->value,
        ]);
    }
}
