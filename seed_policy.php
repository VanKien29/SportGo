<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SystemPolicy;

$policy = SystemPolicy::where('is_active', true)->first();

if (!$policy) {
    SystemPolicy::create([
        'key' => 'general',
        'title' => 'Chính Sách Hệ Thống SportGo',
        'content' => '<h3>Chào mừng bạn đến với SportGo!</h3><p>Để đảm bảo trải nghiệm tốt nhất cho mọi người dùng, vui lòng tuân thủ các quy định sau:</p><ul><li>Không đặt sân ảo hoặc hủy sân sát giờ thi đấu.</li><li>Luôn giữ thái độ văn minh, lịch sự tại sân tập.</li><li>Mọi khiếu nại vui lòng liên hệ tổng đài hỗ trợ 24/7.</li></ul><p><b>Chúc bạn có những giờ phút tập luyện thể thao vui vẻ!</b></p>',
        'version' => 1,
        'type' => 'general',
        'is_active' => true
    ]);
    echo "Created sample policy successfully.\n";
} else {
    echo "Active policy already exists: " . $policy->title . "\n";
}
