<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\OwnerWallet;
use App\Models\VenuePlatformFeeLedger;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerTerminationService;
use Carbon\Carbon;

// 1. Find or create an owner user
$owner = User::firstOrCreate(['email' => 'owner_refund_test@example.com'], [
    'username' => 'owner_refund_test',
    'full_name' => 'Owner Refund Test',
    'password' => bcrypt('password'),
    'phone' => '0' . rand(100000000, 999999999),
]);

// 2. Create venue cluster
$venueCluster = VenueCluster::create([
    'owner_id' => $owner->id,
    'name' => 'Test Cluster Refund',
    'slug' => 'test-cluster-refund-' . rand(),
    'address' => '123 Test',
    'latitude' => 10,
    'longitude' => 10,
    'status' => 'active'
]);

// 3. Create Owner Wallet
$wallet = OwnerWallet::create([
    'owner_id' => $owner->id,
    'venue_cluster_id' => $venueCluster->id,
    'available_balance' => 1000000, // 1M from bookings
    'pending_withdrawal_balance' => 0,
    'total_earned' => 1000000,
    'total_withdrawn' => 0,
]);

// 3.5 Create Bank Account
$bankAccount = \App\Models\OwnerBankAccount::create([
    'owner_id' => $owner->id,
    'bank_name' => 'Test Bank',
    'bank_code' => 'TEST',
    'account_number' => '123456789',
    'account_holder_name' => 'TEST OWNER',
    'is_default' => true,
    'status' => 'active'
]);

// 4. Create Venue Platform Fee Ledger (Paid for 1 year, 6 months remaining)
$start = Carbon::now()->subMonths(6);
$end = Carbon::now()->addMonths(6);
$totalDays = $start->diffInDays($end);

$feeLedger = VenuePlatformFeeLedger::create([
    'venue_cluster_id' => $venueCluster->id,
    'court_count' => 1,
    'period_start' => $start,
    'period_end' => $end,
    'amount_paid' => 12000000, // Paid 12M
    'status' => 'paid',
]);

// 5. Create Partner Application and Contract
$application = PartnerApplication::create([
    'user_id' => $owner->id,
    'approved_venue_cluster_id' => $venueCluster->id,
    'business_name' => 'Test',
    'tax_code' => '123',
    'phone_contact' => '123',
    'venue_address' => '123',
    'venue_name' => 'Test Cluster',
    'venue_latitude' => 10,
    'venue_longitude' => 10,
    'status' => 'approved',
]);

$contract = PartnerContract::create([
    'partner_application_id' => $application->id,
    'contract_template_id' => 1,
    'contract_number' => 'CT-REFUND-' . rand(),
    'generated_file_path' => 'test',
    'status' => 'completed'
]);

// 6. Request Termination
$terminationService = app(PartnerTerminationService::class);
$request = $terminationService->requestTermination($application->id, $owner, 'mutual', 'Test Refund');

// 7. Admin processes termination
$admin = User::first(); // Assuming first user is admin
$terminationService->processTermination($request, $contract, $admin);

// 8. Output results
$wallet->refresh();
$withdrawals = $wallet->withdrawalRequests;
$ledgers = $wallet->ledgers;

echo "Wallet available balance: " . $wallet->available_balance . "\n";
echo "Number of withdrawal requests: " . $withdrawals->count() . "\n";
foreach ($withdrawals as $w) {
    echo "Withdrawal amount: " . $w->amount . "\n";
}
echo "Number of ledgers: " . $ledgers->count() . "\n";
foreach ($ledgers as $l) {
    echo "Ledger [{$l->type}] Amount: {$l->amount} Desc: {$l->description}\n";
}

// Cleanup
$ledgers->each->delete();
$withdrawals->each->delete();
$feeLedger->delete();
\App\Models\PartnerLiquidation::where('termination_request_id', $request->id)->delete();
\App\Models\PartnerHistory::where('partner_application_id', $application->id)->delete();
$request->delete();
$contract->delete();
$application->delete();
$wallet->delete();
$venueCluster->delete();
$owner->delete();

echo "Test completed.\n";
