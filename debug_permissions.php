<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('username', 'admin')->first();
$token = $user->createToken('debug-test')->plainTextToken;
$baseUrl = config('app.url');

echo "App URL: $baseUrl\n\n";

function callApi($url, $method, $token, $data = null) {
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json'];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return [$code, $response, $error];
}

// 1. Test /api/auth/me
[$code, $res] = callApi("$baseUrl/api/auth/me", 'GET', $token);
echo "1. GET /api/auth/me → HTTP $code\n";
$data = json_decode($res, true);
echo "   role_group: " . ($data['role_group'] ?? 'N/A') . "\n\n";

// 2. Test GET roles
[$code, $res] = callApi("$baseUrl/api/admin/permissions/roles", 'GET', $token);
echo "2. GET /api/admin/permissions/roles → HTTP $code\n";
if ($code !== 200) echo "   Response: $res\n\n";
else echo "   OK\n\n";

// 3. Test POST tạo role mới
[$code, $res] = callApi("$baseUrl/api/admin/permissions/roles", 'POST', $token, [
    'name' => 'test_role_' . time(),
    'display_name' => 'Test Role Debug',
    'description' => 'Testing',
    'permissions' => [],
]);
echo "3. POST /api/admin/permissions/roles → HTTP $code\n";
echo "   Response: $res\n\n";

// Cleanup token
$user->tokens()->where('name', 'debug-test')->delete();
