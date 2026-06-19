<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

$u = new User();
$u->id = (string) Str::uuid();
$u->username = 'newpartner';
$u->full_name = 'Người Dùng Mới';
$u->email = 'newpartner@sportgo.vn';
$u->phone = '0999888776';
$u->password = Hash::make('password123');
$u->status = 'active';
$u->save();

echo "User created successfully.\n";
