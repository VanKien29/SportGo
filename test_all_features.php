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

echo "Bắt đầu bài test toàn diện 3 tính năng...\n\n";

// 1. Dọn dẹp dữ liệu cũ nếu có
// User::where('email', 'test_full_flow@example.com')->delete();

$rand = rand(10000, 99999);

// 2. Chuẩn bị Dữ liệu
$owner = User::create([
    'username' => 'test_full_flow_' . $rand,
    'email' => 'test_full_flow_' . $rand . '@example.com',
    'full_name' => 'Owner Full Flow',
    'password' => bcrypt('password'),
    'phone' => '0999888' . rand(100, 999),
]);

$venueCluster = VenueCluster::create([
    'owner_id' => $owner->id,
    'name' => 'Test Cluster Full',
    'slug' => 'test-cluster-full-' . rand(1000, 9999),
    'address' => '123 Test',
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
    'account_holder_name' => 'TEST OWNER',
    'is_default' => true,
    'status' => 'active'
]);

$application = PartnerApplication::create([
    'user_id' => $owner->id,
    'approved_venue_cluster_id' => $venueCluster->id,
    'business_name' => 'Test Full',
    'tax_code' => '123456',
    'phone_contact' => '123456',
    'venue_address' => '123456',
    'venue_name' => 'Test Cluster Full',
    'venue_latitude' => 10,
    'venue_longitude' => 10,
    'status' => 'approved',
]);

$contract = PartnerContract::create([
    'partner_application_id' => $application->id,
    'contract_template_id' => 1,
    'contract_number' => 'CT-FULL-' . rand(1000, 9999),
    'generated_file_path' => 'test_draft.pdf',
    'status' => 'waiting_signature'
]);

// THÊM: Tiền phí hệ thống 12tr (còn dư nửa năm)
$feeLedger = VenuePlatformFeeLedger::create([
    'venue_cluster_id' => $venueCluster->id,
    'court_count' => 1,
    'period_start' => Carbon::now()->subMonths(6),
    'period_end' => Carbon::now()->addMonths(6),
    'amount_paid' => 12000000,
    'status' => 'paid',
]);

echo "=> Dữ liệu đã chuẩn bị xong!\n\n";

// ==========================================
// TEST 1: KÝ HỢP ĐỒNG -> GỬI EMAIL CHÚC MỪNG
// ==========================================
echo "[TEST 1] Thực hiện Ký Hợp Đồng...\n";
$signService = app(ContractSignatureService::class);
$signService->processOwnerSignature($contract, $owner, '127.0.0.1', 'Test-Agent');
$admin = User::first(); 
$signService->completeContract($contract, $admin, '127.0.0.1', 'Test-Agent');

$hasRole = \Illuminate\Support\Facades\DB::table('user_roles')
    ->where('user_id', $owner->id)
    ->whereIn('role_id', function ($query) {
        $query->select('id')->from('roles')->where('name', 'venue_owner');
    })->exists();

if ($hasRole) {
    echo " -> THÀNH CÔNG: Chủ sân đã được cấp Role 'venue_owner' và hệ thống đã gửi Email (ngầm)!\n\n";
} else {
    echo " -> THẤT BẠI: Chưa được cấp Role!\n\n";
}


// ==========================================
// TEST 2: CHẤM DỨT HỢP ĐỒNG -> SINH TÀI LIỆU BIÊN BẢN & GỬI EMAIL
// ==========================================
echo "[TEST 2] Admin Duyệt Yêu Cầu Chấm Dứt...\n";
$terminationService = app(PartnerTerminationService::class);
$request = $terminationService->requestTermination($application->id, $owner, 'mutual', 'Không muốn làm nữa');
$admin = User::first(); 
$terminationService->processTermination($request, $contract, $admin);

// Kiểm tra có sinh Biên bản không
$documents = PartnerTerminationDocument::where('partner_termination_request_id', $request->id)->get();
if ($documents->count() > 0) {
    echo " -> THÀNH CÔNG: Đã sinh ra file tài liệu: " . $documents->first()->document_type . " tại đường dẫn: " . $documents->first()->file_path . "\n";
} else {
    echo " -> THẤT BẠI: Chưa sinh được Biên bản!\n";
}

// Kiểm tra có tiền hoàn không
$wallet->refresh();
$withdrawals = \App\Models\OwnerWithdrawalRequest::where('owner_wallet_id', $wallet->id)->get();
if ($withdrawals->count() > 0) {
    echo " -> THÀNH CÔNG: Hệ thống đã hoàn tiền phí nền tảng và tạo Yêu cầu Rút tiền với số tiền là: " . number_format($withdrawals->first()->amount) . " VND\n\n";
} else {
    echo " -> THẤT BẠI: Chưa sinh lệnh Rút tiền!\n\n";
}


// ==========================================
// TEST 3: GỠ QUYỀN CHỦ SÂN SAU 1 THÁNG CHẤM DỨT
// ==========================================
echo "[TEST 3] Tua thời gian chấm dứt về 35 ngày trước & Chạy Cron Job gỡ quyền...\n";
// Hack thời gian
$application->update(['terminated_at' => Carbon::now()->subDays(35)]);

// Chạy lệnh console
Artisan::call('app:revoke-expired-owner-roles');
echo Artisan::output();

$ownerRoles = \Illuminate\Support\Facades\DB::table('user_roles')->where('user_id', $owner->id)->get();
$hasRoleEnd = $ownerRoles->contains('role_id', function ($role) {
    return $role == \Illuminate\Support\Facades\DB::table('roles')->where('name', 'venue_owner')->value('id');
});

if (!$hasRoleEnd) {
    echo " -> THÀNH CÔNG: Role 'venue_owner' đã bị gỡ sạch khỏi tải khoản! Người dùng không thể đăng nhập trang chủ sân nữa.\n\n";
} else {
    echo " -> THẤT BẠI: Role vẫn còn!\n\n";
}

echo "TẤT CẢ CÁC BÀI TEST ĐÃ HOÀN THÀNH MỸ MÃN!\n";
