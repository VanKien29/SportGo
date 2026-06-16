<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\OwnerWallet;
use App\Models\OwnerBankAccount;
use App\Models\OwnerWalletLedger;
use App\Models\OwnerWithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;

$rand = rand(1000, 9999);
$email = "wallet_test_$rand@example.com";
$password = "password";

$owner = User::create([
    'username' => 'wallet_test_' . $rand,
    'email' => $email,
    'full_name' => 'Chủ Sân Test Ví',
    'password' => bcrypt($password),
    'phone' => '0999' . rand(100000, 999999),
    'email_verified_at' => now(),
]);

// Cấp quyền
$roleId = \Illuminate\Support\Facades\DB::table('roles')->where('name', 'venue_owner')->value('id');
if ($roleId) {
    \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
        'user_id' => $owner->id,
        'role_id' => $roleId,
        'granted_by' => 1,
        'created_at' => now(),
    ]);
}

$venueCluster = VenueCluster::create([
    'owner_id' => $owner->id,
    'name' => 'Cụm sân Test Ví',
    'slug' => 'cum-san-test-vi-' . $rand,
    'address' => 'Hà Nội',
    'latitude' => 10,
    'longitude' => 10,
    'status' => 'active'
]);

$application = \App\Models\PartnerApplication::create([
    'user_id' => $owner->id,
    'approved_venue_cluster_id' => $venueCluster->id,
    'business_name' => 'Kinh doanh Test Vi',
    'tax_code' => '123456789',
    'phone_contact' => '0999888777',
    'venue_address' => 'Hà Nội',
    'venue_name' => 'Cụm sân Test Ví',
    'venue_latitude' => 10,
    'venue_longitude' => 10,
    'status' => 'approved',
]);

\App\Models\PartnerContract::create([
    'partner_application_id' => $application->id,
    'contract_template_id' => 1,
    'contract_number' => 'CT-VI-' . rand(1000, 9999),
    'generated_file_path' => 'test.pdf',
    'status' => 'signed'
]);

$wallet = OwnerWallet::create([
    'owner_id' => $owner->id,
    'venue_cluster_id' => $venueCluster->id,
    'available_balance' => 15000000,
    'pending_withdrawal_balance' => 0,
    'total_earned' => 25000000,
    'total_withdrawn' => 10000000,
]);

$bankAccount = OwnerBankAccount::create([
    'owner_id' => $owner->id,
    'bank_name' => 'Vietcombank',
    'bank_code' => 'VCB',
    'account_number' => '0123456789',
    'account_holder_name' => 'CHU SAN TEST VI',
    'is_default' => true,
    'status' => 'active'
]);

// Lịch sử nhận tiền
OwnerWalletLedger::create([
    'owner_wallet_id' => $wallet->id,
    'owner_id' => $owner->id,
    'venue_cluster_id' => $venueCluster->id,
    'type' => 'credit',
    'direction' => 'credit',
    'amount' => 25000000,
    'balance_before' => 0,
    'balance_after' => 25000000,
    'reference_code' => 'BK-' . strtoupper(Str::random(8)),
    'description' => 'Doanh thu đặt sân tháng này.',
    'status' => 'completed'
]);

// Lịch sử rút tiền
$withdrawal = OwnerWithdrawalRequest::create([
    'owner_id' => $owner->id,
    'owner_wallet_id' => $wallet->id,
    'owner_bank_account_id' => $bankAccount->id,
    'request_code' => 'WR-' . strtoupper(Str::random(8)),
    'amount' => 10000000,
    'status' => 'completed',
    'owner_note' => 'Rút doanh thu đợt 1',
    'requested_at' => Carbon::now()->subDays(5),
]);

OwnerWalletLedger::create([
    'owner_wallet_id' => $wallet->id,
    'owner_id' => $owner->id,
    'venue_cluster_id' => $venueCluster->id,
    'type' => 'debit',
    'direction' => 'debit',
    'amount' => 10000000,
    'balance_before' => 25000000,
    'balance_after' => 15000000,
    'reference_code' => $withdrawal->request_code,
    'description' => 'Đã chi trả yêu cầu rút ' . $withdrawal->request_code,
    'status' => 'completed'
]);

echo "Xong! Đã tạo tài khoản test ví:\n";
echo "Email: $email\n";
echo "Mật khẩu: $password\n";
echo "Số dư: 15,000,000 VND\n";
