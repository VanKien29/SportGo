<?php

namespace Database\Seeders;

use App\Models\ModerationConfig;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ModerationConfigsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('moderation_configs')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $configs = [
            ['slot_hold_minutes', '20', 'integer', 'Số phút giữ slot trong quá trình đặt sân.'],
            ['reminder_before_minutes', '30', 'integer', 'Số phút nhắc trước giờ đặt sân.'],
            ['auto_lock_enabled', '0', 'boolean', 'Bật hoặc tắt khóa tài khoản tự động.'],
            [
                'auto_lock_reason',
                'Tài khoản bị khóa tự động theo cấu hình hệ thống',
                'string',
                'Lý do mặc định khi hệ thống tự khóa tài khoản.',
            ],
        ];

        foreach ($configs as [$key, $value, $valueType, $description]) {
            ModerationConfig::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'value_type' => $valueType,
                    'description' => $description,
                    'updated_by' => $admin?->id,
                ]
            );
        }
    }
}
