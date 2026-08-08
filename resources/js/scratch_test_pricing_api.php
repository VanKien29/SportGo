<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\Api\Owner\PricingController;
use Illuminate\Http\Request;

$owners = User::all();

foreach ($owners as $user) {
    $request = Request::create('/api/owner/pricing-rules', 'GET');
    $request->setUserResolver(fn() => $user);

    $controller = new PricingController();
    try {
        $response = $controller->index($request);
        $data = $response->getData(true);
        echo "User ID: {$user->id} | Name: {$user->name} | Clusters returned: " . count($data['clusters'] ?? []) . "\n";
        if (!empty($data['clusters'])) {
            foreach ($data['clusters'] as $cl) {
                echo "   - Cluster ID: {$cl['id']} | Name: {$cl['name']}\n";
            }
        }
    } catch (\Throwable $e) {
        echo "User ID: {$user->id} | Error: " . $e->getMessage() . "\n";
    }
}
