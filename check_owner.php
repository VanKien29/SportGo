<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::whereHas('roles', function($q) { $q->where('name', 'owner'); })->first();
if (!$user) { echo "No owner found"; exit; }
echo "Owner ID: " . $user->id . "\n";
echo "Clusters owned: " . App\Models\VenueCluster::where('owner_id', $user->id)->count() . "\n";
