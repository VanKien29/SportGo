<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::whereHas('roles', function($q) {
    $q->where('name', 'owner');
})->first();

$statuses = \App\Models\PartnerApplication::where('user_id', $user->id)->pluck('status');
echo "User ID: " . $user->id . "\n";
echo "Statuses: " . json_encode($statuses) . "\n";

// Get latest approved
$base = \App\Models\PartnerApplication::where('user_id', $user->id)
    ->whereIn('status', ['approved', 'completed'])
    ->latest()
    ->first();
echo "Has Base: " . ($base ? 'Yes' : 'No') . "\n";
