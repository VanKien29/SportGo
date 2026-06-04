<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\PartnerApplicationController;
use App\Http\Controllers\Admin\PermissionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('/login', function () {
    return redirect('/');
})->name('login');

Route::get('/register', function () {
    return redirect('/');
})->name('register');

// Blade admin (legacy) — SPA dùng /admin/banners, /admin/partner-applications qua Vue
Route::middleware(['auth', 'ensureAdminRoleWeb'])->prefix('admin/legacy/banners')->name('admin.banners.')->group(function () {
    Route::get('/', [BannerController::class, 'index'])->name('index');
    Route::get('/create', [BannerController::class, 'create'])->name('create');
    Route::post('/', [BannerController::class, 'store'])->name('store');
    Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
    Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
    Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'ensureAdminRoleWeb'])->prefix('admin/legacy/partner-applications')->name('admin.partner-applications.')->group(function () {
    Route::get('/', [PartnerApplicationController::class, 'index'])->name('index');
    Route::get('/{application}', [PartnerApplicationController::class, 'show'])->name('show');
    Route::get('/{application}/approve', [PartnerApplicationController::class, 'approve_form'])->name('approve_form');
    Route::post('/{application}/approve', [PartnerApplicationController::class, 'approve'])->name('approve');
    Route::get('/{application}/reject', [PartnerApplicationController::class, 'reject_form'])->name('reject_form');
    Route::post('/{application}/reject', [PartnerApplicationController::class, 'reject'])->name('reject');
});

// Admin Routes - Permission Management
Route::middleware(['auth', 'ensureAdminRoleWeb'])->prefix('admin/permissions')->name('admin.permissions.')->group(function () {
    // Roles
    Route::get('/', [PermissionController::class, 'index'])->name('index');
    Route::get('/roles/create', [PermissionController::class, 'create_role'])->name('create_role');
    Route::post('/roles', [PermissionController::class, 'store_role'])->name('store_role');
    Route::get('/roles/{role}/edit', [PermissionController::class, 'edit_role'])->name('edit_role');
    Route::put('/roles/{role}', [PermissionController::class, 'update_role'])->name('update_role');
    Route::delete('/roles/{role}', [PermissionController::class, 'destroy_role'])->name('destroy_role');
    
    // Permissions
    Route::get('/list', [PermissionController::class, 'permissions_list'])->name('list');
    Route::get('/create', [PermissionController::class, 'create_permission'])->name('create_permission');
    Route::post('/', [PermissionController::class, 'store_permission'])->name('store_permission');
    Route::delete('/{permission}', [PermissionController::class, 'destroy_permission'])->name('destroy_permission');
});

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
