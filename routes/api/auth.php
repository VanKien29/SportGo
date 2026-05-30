<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\SetPasswordController;
use App\Http\Controllers\Api\SystemPolicyController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/set-password', [SetPasswordController::class, 'store']);

    // System Policies
    Route::get('/system-policies/check-acceptance', [SystemPolicyController::class, 'checkAcceptance']);
    Route::post('/system-policies/accept', [SystemPolicyController::class, 'accept']);
});
