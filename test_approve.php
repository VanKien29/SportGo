<?php
$user = App\Models\User::firstOrCreate(
    ['email' => 'testpartner@example.com'],
    [
        'id' => Illuminate\Support\Str::uuid()->toString(),
        'username' => 'testpartner',
        'password' => bcrypt('password'),
        'full_name' => 'Test Partner',
        'phone' => '0123456789',
        'status' => 'active'
    ]
);

$app = App\Models\PartnerApplication::create([
    'id' => Illuminate\Support\Str::uuid()->toString(),
    'user_id' => $user->id,
    'business_name' => 'Sân Bóng Test',
    'tax_code' => '1234567890',
    'venue_name' => 'Sân Bóng Test',
    'venue_address' => 'Hà Nội',
    'venue_map_url' => 'http://map',
    'venue_latitude' => 21.0,
    'venue_longitude' => 105.0,
    'status' => 'pending'
]);

echo "Created application ID: " . $app->id . "\n";

// Now approve it
$controller = app(App\Http\Controllers\Api\Admin\PartnerApplicationController::class);
$request = new Illuminate\Http\Request();
$request->merge([
    'initial_court_name' => 'Sân 1',
    'court_type_id' => App\Models\CourtType::first()->id ?? 1,
    'bank_account_name' => 'TEST BANK',
    'account_holder_name' => 'TEST PARTNER',
    'bank_account_number' => '123456789',
    'bank_name' => 'TESTBANK',
    'review_note' => 'Approved by test script'
]);
$request->setUserResolver(function() { return App\Models\User::where('email', 'admin@sportgo.vn')->first() ?? App\Models\User::first(); });

try {
    $response = $controller->approve($request, $app->id);
    echo "Approve Response: " . json_encode($response->getData()) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
