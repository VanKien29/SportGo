<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\PartnerApplication::first()->user;

if (!$user) {
    echo "User not found\n";
    exit;
}

// Get latest approved
$base = \App\Models\PartnerApplication::where('user_id', $user->id)
    ->whereIn('status', ['approved', 'completed'])
    ->latest()
    ->first();
if (!$base) {
    echo "NO BASE APPLICATION FOUND FOR USER.\n";
    exit;
}
echo "Found base application: " . $base->id . "\n";

$request = Illuminate\Http\Request::create('/api/owner/partner-applications/new-cluster', 'POST', [
    'venue_name' => 'Test Cluster',
    'venue_address' => 'Hanoi',
    'venue_latitude' => 21,
    'venue_longitude' => 105,
    'court_count_total' => 2
]);

$request->setUserResolver(function() use ($user) { return $user; });

$controller = new \App\Http\Controllers\Api\Owner\PartnerApplicationController();
try {
    $response = $controller->storeNewCluster($request);
    echo "Content: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
