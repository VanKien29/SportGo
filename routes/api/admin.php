<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\SystemPolicyController;
use App\Http\Controllers\Api\Admin\PartnerApplicationController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Models\CourtType;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);

Route::prefix('users')->group(function (): void {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::patch('/{id}/lock', [UserController::class, 'lock']);
    Route::patch('/{id}/unlock', [UserController::class, 'unlock']);
    Route::put('/{id}/roles', [UserController::class, 'assignRoles']);
});

Route::apiResource('system-policies', SystemPolicyController::class);

// Quản lý đơn đăng kí làm chủ sân
Route::prefix('partner-applications')->group(function (): void {
    Route::get('/', [PartnerApplicationController::class, 'index']);
    Route::get('/{id}', [PartnerApplicationController::class, 'show']);
    Route::post('/{id}/approve', [PartnerApplicationController::class, 'approve']);
    Route::post('/{id}/reject', [PartnerApplicationController::class, 'reject']);
});

// Loại sân (dùng khi duyệt đơn đăng kí)
Route::get('/court-types', function () {
    return response()->json([
        'status' => 'success',
        'data' => CourtType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
    ]);
});

// Quản lý banner
Route::prefix('banners')->group(function (): void {
    Route::get('/', [BannerController::class, 'index']);
    Route::post('/', [BannerController::class, 'store']);
    Route::patch('/{id}', [BannerController::class, 'update']);
    Route::delete('/{id}', [BannerController::class, 'destroy']);
    Route::post('/reorder', [BannerController::class, 'reorder']);
});

// Quản lý phân quyền
Route::prefix('permissions')->group(function (): void {
    // Roles
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::post('/roles', [PermissionController::class, 'storeRole']);
    Route::put('/roles/{role}', [PermissionController::class, 'updateRole']);
    Route::delete('/roles/{role}', [PermissionController::class, 'destroyRole']);

    // Permissions
    Route::get('/list', [PermissionController::class, 'permissions']);
    Route::post('/', [PermissionController::class, 'storePermission']);
    Route::delete('/{permission}', [PermissionController::class, 'destroyPermission']);
});

