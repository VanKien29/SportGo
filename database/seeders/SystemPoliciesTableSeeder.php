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
            [
                'key' => 'terms',
                'title' => 'Điều khoản sử dụng SportGo',
                'type' => 'general',
                'policy_type' => 'terms',
                'content' => "Người dùng và chủ sân cần đọc, hiểu và xác nhận điều khoản sử dụng SportGo trước khi tiếp tục sử dụng hệ thống.\n\nKhi SportGo phát hành phiên bản chính sách mới có yêu cầu chấp nhận lại, hệ thống sẽ hiển thị màn xác nhận để người dùng đọc và đồng ý.",
                'require_reaccept' => true,
                'is_overridable' => false,
                'priority' => 100,
            ],
            [
                'key' => 'booking_cancellation',
                'title' => 'Chính sách hoàn hủy',
                'type' => 'booking',
                'policy_type' => 'booking_cancellation',
                'content' => "Chính sách này quy định điều kiện khách được hủy booking và mốc thời gian hoàn tiền tương ứng.\n\nBooking đã check-in hoặc hoàn tất không được hủy thường. Chủ sân hoặc hệ thống có thể hủy booking theo các mốc thời gian quy định. Khi khách hủy booking hợp lệ, tiền hoàn sẽ được tính tự động theo bảng mốc đã cấu hình và đi qua các bước xác nhận của chủ sân và admin.",
                'require_reaccept' => false,
                'is_overridable' => true,
                'priority' => 95,
            ],
            [
                'key' => 'platform_fee',
                'title' => 'Chính sách phí nền tảng',
                'type' => 'general',
                'policy_type' => 'platform_fee',
                'content' => "Chính sách này quy định việc nhắc phí, giới hạn quyền và khóa cụm sân khi chủ sân quá hạn phí nền tảng.\n\nKhi bị giới hạn, chủ sân chỉ được thực hiện các thao tác cần thiết như đóng phí, xem ví hoặc rút tiền nếu chính sách cho phép, xem hồ sơ và hợp đồng. Chủ sân không được tạo booking mới, sửa giá, tạo voucher, đăng bài hoặc thêm nhân viên.",
                'require_reaccept' => false,
                'is_overridable' => false,
                'priority' => 90,
            ],
            [
                'key' => 'venue_policy',
                'title' => 'Chính sách sân',
                'type' => 'general',
                'policy_type' => 'venue_policy',
                'content' => "Chính sách này quy định phạm vi chủ sân được cấu hình chính sách riêng.\n\nChính sách sân chỉ được áp dụng nếu không thấp hơn, không trái và không bỏ qua các ràng buộc của chính sách hệ thống. Nếu vi phạm, chính sách sân phải bị từ chối và lưu rõ lý do.",
                'require_reaccept' => false,
                'is_overridable' => false,
                'priority' => 85,
            ],
            [
                'key' => 'moderation',
                'title' => 'Chính sách kiểm duyệt & báo cáo',
                'type' => 'moderation',
                'policy_type' => 'moderation',
                'content' => "Chính sách này quy định cách SportGo tiếp nhận báo cáo, đưa nội dung vào chờ kiểm duyệt và xử lý nội dung vi phạm.\n\nHệ thống không tự khóa tài khoản nếu chưa có rule rõ ràng và chưa đủ dữ liệu cần thiết.",
                'require_reaccept' => false,
                'is_overridable' => false,
                'priority' => 80,
            ],
            [
                'key' => 'partner_contract',
                'title' => 'Chính sách đối tác & hợp đồng',
                'type' => 'general',
                'policy_type' => 'partner_contract',
                'content' => "Chính sách này quy định luồng duyệt hồ sơ đối tác, sinh hợp đồng, ký hợp đồng, chấm dứt hợp tác và thu quyền chủ sân.\n\nHồ sơ đối tác chỉ hoàn tất khi hợp đồng đã có đủ chữ ký chủ sân và SportGo. Sau khi chấm dứt hợp tác và hết thời gian chuyển tiếp, hệ thống thu quyền quản lý owner.",
                'require_reaccept' => false,
                'is_overridable' => false,
                'priority' => 90,
            ],
        ];

        foreach ($policies as $policy) {
            $payload = [
                'title' => $policy['title'],
                'content' => $policy['content'],
                'type' => $policy['type'],
                'is_active' => true,
                'effective_from' => now()->subDays(7),
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ];

            foreach (['policy_type', 'is_overridable', 'priority', 'require_reaccept'] as $column) {
                if (Schema::hasColumn('system_policies', $column)) {
                    $payload[$column] = $policy[$column];
                }
            }

            if (Schema::hasColumn('system_policies', 'status')) {
                $payload['status'] = 'active';
            }

            if (Schema::hasColumn('system_policies', 'published_at')) {
                $payload['published_at'] = now()->subDays(7);
            }

            if (Schema::hasColumn('system_policies', 'published_by')) {
                $payload['published_by'] = $admin?->id;
            }

            if (Schema::hasColumn('system_policies', 'change_summary')) {
                $payload['change_summary'] = 'Chuẩn hóa chính sách thành văn bản dễ đọc và quy tắc xử lý tự động theo nghiệp vụ.';
            }

            SystemPolicy::query()->updateOrCreate(
                ['key' => $policy['key'], 'version' => 1],
                $payload,
            );
        }
    }
}
