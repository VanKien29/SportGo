<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\VenuePost;
use Illuminate\Support\Facades\Gate;

$user = App\Models\User::where('username', 'owner')->first();
if (!$user) {
    echo "User not found\n";
    exit;
}

echo "Policy for VenuePost: " . get_class(Gate::getPolicyFor(VenuePost::class)) . "\n";
echo "Gate allows viewAny: " . (Gate::forUser($user)->allows('viewAny', VenuePost::class) ? 'Yes' : 'No') . "\n";
echo "Gate inspect viewAny: \n";
try {
    Gate::forUser($user)->authorize('viewAny', VenuePost::class);
    echo "Authorized successfully!\n";
} catch (\Exception $e) {
    echo "Auth failed: " . $e->getMessage() . "\n";
}
