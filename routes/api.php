<?php

use Illuminate\Support\Facades\Route;

// Auth Routes (Login, Register, etc.)
Route::prefix('auth')->group(base_path('routes/api/auth.php'));

// Banner công khai cho trang người dùng (không cần đăng nhập)
Route::get('banners/active/{position?}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'getActiveBanners']);
Route::get('admin/banners/active/{position?}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'getActiveBanners']);

// Admin Routes (Policies, Banners, Partner Applications, etc.)
Route::middleware(['auth:sanctum', 'ensureAdminRole'])
    ->prefix('admin')
    ->group(base_path('routes/api/admin.php'));
