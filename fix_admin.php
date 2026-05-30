<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

$user = User::where('username', 'admin')->first();
if (!$user) {
    $user = User::create([
        'username' => 'admin',
        'full_name' => 'System Administrator',
        'email' => 'admin@sportgo.vn',
        'phone' => '0999999999',
        'password' => Hash::make('123456'),
        'status' => 'active'
    ]);
    echo "Created admin user.\n";
} else {
    $user->update(['status' => 'active']);
    echo "Updated admin user status.\n";
}

$role = Role::firstOrCreate(
    ['name' => 'admin'],
    ['display_name' => 'Administrator', 'is_system' => true]
);

$user->roles()->sync([$role->id]);
echo "Assigned admin role successfully.\n";
