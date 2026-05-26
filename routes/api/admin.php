<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);

Route::prefix('users')->group(function (): void {
    Route::get('/', [UserController::class, 'index']);
    Route::patch('/{id}/lock', [UserController::class, 'lock']);
    Route::patch('/{id}/unlock', [UserController::class, 'unlock']);
});
