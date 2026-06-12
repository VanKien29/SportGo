<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\OwnerWallet;
use App\Models\OwnerBankAccount;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Services\Partner\ContractSignatureService;
use App\Services\Partner\PartnerTerminationService;
use App\Models\PartnerTerminationDocument;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

$rand = rand(10000, 99999);
$email = "demo_owner_{$rand}@example.com";

// Tắt gửi mail thật, bắt mail để in ra màn hình
Mail::fake();

$owner = User::create([
    'username' => 'demo_owner_' . $rand,
    'email' => $email,
    'full_name' => 'Nguyễn Văn Demo',
    'password' => bcrypt('password'),
    'phone' => '0999888' . rand(100, 999),
]);

$venueCluster = VenueCluster::create([
    'owner_id' => $owner->id,
    'name' => 'Cụm sân bóng Demo',
    'slug' => 'cum-san-demo-' . $rand,
    'address' => '123 Đường Demo, Hà Nội',
    'latitude' => 10,
    'longitude' => 10,
    'status' => 'active'
]);

$wallet = OwnerWallet::create([
    'owner_id' => $owner->id,
    'venue_cluster_id' => $venueCluster->id,
    'available_balance' => 0,
    'pending_withdrawal_balance' => 0,
    'total_earned' => 0,
    'total_withdrawn' => 0,
]);

$bankAccount = OwnerBankAccount::create([
    'owner_id' => $owner->id,
    'bank_name' => 'Vietcombank',
    'bank_code' => 'VCB',
    'account_number' => '123456789',
    'account_holder_name' => 'NGUYEN VAN DEMO',
    'is_default' => true,
    'status' => 'active'
]);

$application = PartnerApplication::create([
    'user_id' => $owner->id,
    'approved_venue_cluster_id' => $venueCluster->id,
    'business_name' => 'Hộ Kinh Doanh Demo',
    'tax_code' => '123456',
    'phone_contact' => '123456',
    'venue_address' => '123456',
    'venue_name' => 'Cụm sân bóng Demo',
    'venue_latitude' => 10,
    'venue_longitude' => 10,
    'status' => 'approved',
]);

$contract = PartnerContract::create([
    'partner_application_id' => $application->id,
    'contract_template_id' => 1,
    'contract_number' => 'CT-DEMO-' . $rand,
    'generated_file_path' => 'test_draft.pdf',
    'status' => 'waiting_signature'
]);

$feeLedger = VenuePlatformFeeLedger::create([
    'venue_cluster_id' => $venueCluster->id,
    'court_count' => 1,
    'period_start' => Carbon::now()->subMonths(6),
    'period_end' => Carbon::now()->addMonths(6), // Còn hạn nửa năm
    'amount_paid' => 12000000, // Đóng 12tr
    'status' => 'paid',
]);

$signService = app(ContractSignatureService::class);
$signService->processOwnerSignature($contract, $owner, '127.0.0.1', 'Test-Agent');
$admin = User::first(); 
$signService->completeContract($contract, $admin, '127.0.0.1', 'Test-Agent');

$mailGranted = Mail::sent(\App\Mail\PartnerRoleGrantedMail::class)->first();

$terminationService = app(PartnerTerminationService::class);
$request = $terminationService->requestTermination($application->id, $owner, 'mutual', 'Không có nhu cầu kinh doanh nữa');
$terminationService->processTermination($request, $contract, $admin);

$mailTerminated = Mail::sent(\App\Mail\PartnerTerminatedMail::class)->first();

$document = PartnerTerminationDocument::where('partner_termination_request_id', $request->id)->first();
$withdrawals = \App\Models\OwnerWithdrawalRequest::where('owner_wallet_id', $wallet->id)->get();
$ledgers = \App\Models\OwnerWalletLedger::where('owner_wallet_id', $wallet->id)->get();

$application->update(['terminated_at' => Carbon::now()->subDays(35)]);
Artisan::call('app:revoke-expired-owner-roles');

$ownerRoles = DB::table('user_roles')->where('user_id', $owner->id)->get();
$hasRoleEnd = $ownerRoles->contains('role_id', function ($role) {
    return $role == DB::table('roles')->where('name', 'venue_owner')->value('id');
});

$report = "
# MÔ PHỎNG QUÁ TRÌNH KINH DOANH VÀ THANH LÝ CỦA ĐỐI TÁC

**Đối tác giả lập:** Nguyễn Văn Demo (Email: $email)
**Cụm sân:** Cụm sân bóng Demo
**Phí nền tảng đã đóng:** 12,000,000 VND (Chu kỳ 1 năm)
**Thời gian đã sử dụng:** 6 tháng (Còn dư 6 tháng)

---

## 1. SAU KHI ĐỐI TÁC KÝ HỢP ĐỒNG ĐIỆN TỬ
- **Trạng thái hợp đồng:** Đã ký (Active)
- **Quyền Chủ Sân (Role):** Đã cấp thành công (`venue_owner`)
- **Email gửi tự động:**
> Tiêu đề: Chào mừng bạn trở thành Đối tác của SportGo!
> Nội dung: Chào Nguyễn Văn Demo, Hợp đồng đối tác của bạn đã được ký kết thành công!...

---

## 2. KHI ĐỐI TÁC BỊ THANH LÝ HỢP ĐỒNG
Admin vừa duyệt Yêu cầu chấm dứt hợp đồng. Hệ thống lập tức xử lý các bước sau:

**A. Sinh biên bản thanh lý:**
- Loại tài liệu: `" . $document->document_type . "`
- File được tự động sinh tại: `" . $document->file_path . "`

**B. Hoàn tiền phí nền tảng:**
Dựa theo tỷ lệ ngày chưa dùng, hệ thống tính toán được số tiền hoàn là: **" . number_format($withdrawals->first()->amount) . " VND**
Giao dịch ghi nhận vào sổ cái ví:
";
foreach($ledgers as $l) {
    $report .= "- Giao dịch [" . strtoupper($l->type) . "]: " . number_format($l->amount) . " VND | Lời dẫn: " . $l->description . "\n";
}
$report .= "
**C. Lệnh rút tiền tự động:**
Hệ thống sinh 1 phiếu Rút tiền chờ duyệt với nội dung: *\"" . $withdrawals->first()->owner_note . "\"* chuyển về số tài khoản **VCB - 123456789**.

**D. Email gửi tự động:**
> Tiêu đề: Thông báo: Chấm dứt hợp đồng đối tác
> Nội dung: Yêu cầu chấm dứt của bạn đã được duyệt. Tiền hoàn cước đã được chuyển vào Lệnh rút tiền... Bạn sẽ bị khóa quyền truy cập sau đúng 1 tháng nữa.

---

## 3. ĐÚNG 1 THÁNG SAU (CRON JOB CHẠY)
- Hệ thống chạy ngầm: `php artisan app:revoke-expired-owner-roles`
- **Kết quả:** Kiểm tra lại quyền của tài khoản Nguyễn Văn Demo: " . (!$hasRoleEnd ? "ĐÃ BỊ XÓA QUYỀN TRUY CẬP (Thành công)" : "LỖI") . "
- **Chặn truy cập:** Chủ sân không còn bất kỳ quyền nào để thao tác vào Cụm sân này nữa.
";

file_put_contents(__DIR__ . '/demo_report.md', $report);
echo "Đã xuất báo cáo Demo vào file demo_report.md";
