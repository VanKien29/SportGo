<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = '019eb4cb-27b8-7080-90b3-fbf00e71fef1';
$user = \App\Models\User::find($userId);

if (!$user) {
    echo "User not found\n";
    exit;
}

$request = Illuminate\Http\Request::create('/api/owner/partner-applications/new-cluster', 'POST', [
    'venue_name' => 'Test Cluster Real',
    'venue_address' => 'Hanoi',
    'venue_latitude' => 21,
    'venue_longitude' => 105,
    'court_count_total' => 2
]);
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(function() use ($user) { return $user; });

$controller = new \App\Http\Controllers\Api\Owner\PartnerApplicationController();
try {
    $response = $controller->storeNewCluster($request);
    echo "Content: " . $response->getContent() . "\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation: " . json_encode($e->errors()) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
