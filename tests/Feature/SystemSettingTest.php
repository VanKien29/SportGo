<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;
    public function test_profile_payload_returns_all_keys_with_default_empty_values(): void
    {
        $payload = SystemSetting::profilePayload();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('system_name', $payload);
        $this->assertArrayHasKey('company_name', $payload);
        $this->assertEquals('', $payload['system_name']);
        $this->assertEquals('', $payload['company_name']);
    }

    public function test_profile_payload_returns_values_from_database(): void
    {
        SystemSetting::query()->create([
            'key' => 'system_name',
            'value' => 'MySportGo',
            'type' => 'string',
            'group' => 'identity',
            'label' => 'Tên hệ thống',
        ]);

        $payload = SystemSetting::profilePayload();

        $this->assertEquals('MySportGo', $payload['system_name']);
        $this->assertEquals('', $payload['company_name']);
    }
}
