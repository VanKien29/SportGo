<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'superadmin')->first();
$token = $user->createToken('test')->plainTextToken;
echo "TOKEN: " . $token . "\n";
