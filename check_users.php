<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DANH SÁCH USER ===\n";
$users = App\Models\User::with('roles')->get();
foreach ($users as $u) {
    $roles = $u->roles->pluck('name')->implode(', ') ?: '(no role)';
    echo "- {$u->username} | {$u->email} | status:{$u->status} | roles: {$roles}\n";
}
echo "\n=== DANH SÁCH ROLE ===\n";
$roles = App\Models\Role::all();
foreach ($roles as $r) {
    echo "- [{$r->id}] {$r->name} ({$r->display_name})\n";
}
