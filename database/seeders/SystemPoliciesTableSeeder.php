<?php

namespace Database\Seeders;

use App\Models\SystemPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SystemPoliciesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('system_policies')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        $policies = [
            ['terms_of_service', 'Điều khoản sử dụng', 'general', 'Điều khoản sử dụng cơ bản của SportGo.'],
            ['privacy_policy', 'Chính sách bảo mật', 'general', 'Chính sách bảo mật thông tin người dùng SportGo.'],
            ['refund_policy', 'Chính sách hoàn tiền', 'refund', 'Chính sách hoàn tiền cơ bản khi hủy đặt sân.'],
            ['booking_policy', 'Chính sách đặt sân', 'booking', 'Quy định cơ bản khi đặt và sử dụng sân.'],
        ];

        foreach ($policies as [$key, $title, $type, $content]) {
            SystemPolicy::query()->updateOrCreate(
                [
                    'key' => $key,
                    'version' => 1,
                ],
                [
                    'title' => $title,
                    'content' => $content,
                    'type' => $type,
                    'is_active' => true,
                    'effective_from' => now(),
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ]
            );
        }
    }
}
