<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Lấy token của user admin
$user = App\Models\User::where('username', 'admin')->first();
if (!$user) { echo "Không tìm thấy user admin\n"; exit; }

$token = $user->createToken('debug-test')->plainTextToken;
echo "Token: " . $token . "\n\n";

// Test /api/auth/me
$ch = curl_init('http://localhost/api/auth/me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "GET /api/auth/me → HTTP $httpCode\n";
$data = json_decode($response, true);
echo "role_group: " . ($data['role_group'] ?? 'KHÔNG CÓ') . "\n";
echo "roles: " . implode(', ', $data['roles'] ?? []) . "\n\n";

// Xóa token test
$user->tokens()->where('name', 'debug-test')->delete();
echo "Done.\n";
