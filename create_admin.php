<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Use DB to directly update
\Illuminate\Support\Facades\DB::update(
    'UPDATE users SET email_verified_at = ?, phone_verified_at = ?, status = ? WHERE email = ?',
    [
        now()->toDateTimeString(),
        now()->toDateTimeString(),
        'active',
        'admin@test.com'
    ]
);

$user = \App\Models\User::where('email', 'admin@test.com')->first();
if (!$user) {
    $user = \App\Models\User::create([
        'username' => 'admin_test',
        'full_name' => 'Admin Test',
        'email' => 'admin@test.com',
        'phone' => '0123456789',
        'password' => bcrypt('123456'),
    ]);
    
    \Illuminate\Support\Facades\DB::update(
        'UPDATE users SET email_verified_at = ?, phone_verified_at = ? WHERE id = ?',
        [
            now()->toDateTimeString(),
            now()->toDateTimeString(),
            $user->id
        ]
    );
}

// Assign admin role
$adminRole = \App\Models\Role::where('name', 'admin')->first();
if ($adminRole) {
    $user->roles()->detach();
    $user->roles()->attach($adminRole->id);
    echo "✓ Admin role assigned\n";
} else {
    echo "⚠ Admin role not found\n";
}

echo "\n✓ Admin account ready!\n";
echo "Email: admin@test.com\n";
echo "Username: admin_test\n";
echo "Password: 123456\n";
echo "Role: admin\n";
