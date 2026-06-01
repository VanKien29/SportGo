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
            ['terms_of_service', 'Điều khoản sử dụng SportGo', 'general', 'terms', 'Điều khoản sử dụng cần được người dùng đọc và chấp nhận khi đăng nhập lần đầu hoặc khi có phiên bản mới.', true, false, 80],
            ['privacy_policy', 'Chính sách bảo mật', 'general', 'terms', 'Quy định về thu thập, bảo vệ và sử dụng thông tin cá nhân trên SportGo.', false, false, 70],
            ['refund_policy', 'Chính sách hủy và hoàn tiền', 'refund', 'refund', 'Quy định khung về hủy booking và hoàn tiền trên SportGo.', false, true, 100],
            ['booking_policy', 'Chính sách đặt sân', 'booking', 'booking', 'Quy định cơ bản khi đặt và sử dụng sân trên SportGo.', false, true, 90],
            ['moderation_policy', 'Chính sách xử lý báo cáo vi phạm', 'moderation', 'moderation', 'Quy định xử lý report, complaint và nội dung vi phạm.', false, false, 90],
            ['venue_fee_policy', 'Chính sách phí duy trì cụm sân', 'general', 'platform_fee', 'Quy định khung về xử lý cụm sân quá hạn phí duy trì nền tảng.', false, false, 100],
        ];

        foreach ($policies as [$key, $title, $type, $policyType, $content, $requireReaccept, $isOverridable, $priority]) {
            $payload = [
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'is_active' => true,
                'effective_from' => now(),
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ];

            if (Schema::hasColumn('system_policies', 'policy_type')) {
                $payload['policy_type'] = $policyType;
            }

            if (Schema::hasColumn('system_policies', 'status')) {
                $payload['status'] = 'active';
            }

            if (Schema::hasColumn('system_policies', 'is_overridable')) {
                $payload['is_overridable'] = $isOverridable;
            }

            if (Schema::hasColumn('system_policies', 'priority')) {
                $payload['priority'] = $priority;
            }

            if (Schema::hasColumn('system_policies', 'published_at')) {
                $payload['published_at'] = now();
            }

            if (Schema::hasColumn('system_policies', 'published_by')) {
                $payload['published_by'] = $admin?->id;
            }

            if (Schema::hasColumn('system_policies', 'require_reaccept')) {
                $payload['require_reaccept'] = $requireReaccept;
            }

            if (Schema::hasColumn('system_policies', 'change_summary')) {
                $payload['change_summary'] = 'Dữ liệu mẫu chuẩn tiếng Việt cho module chính sách.';
            }

            SystemPolicy::query()->updateOrCreate(
                ['key' => $key, 'version' => 1],
                $payload
            );
        }
    }
}
