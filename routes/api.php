<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Api\Admin\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\FinanceOperationController as AdminFinanceOperationController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\SetPasswordController;
use App\Http\Controllers\Api\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Api\Payment\SepayPaymentController;
use App\Http\Controllers\Api\Payment\BankWithdrawalCallbackController;
use App\Http\Controllers\Api\PolicyAcceptanceController;
use App\Http\Controllers\Api\Owner\PricingController as OwnerPricingController;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureOwnerRole;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
    Route::post('/register/resend-otp', [AuthController::class, 'resendRegisterOtp']);
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
    });
});

Route::prefix('admin/auth')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password/send-otp', [AdminForgotPasswordController::class, 'sendOtp']);
    Route::post('/forgot-password/verify-otp', [AdminForgotPasswordController::class, 'verifyOtp']);
    Route::post('/forgot-password/reset', [AdminForgotPasswordController::class, 'reset']);

    Route::middleware(['auth:sanctum', EnsureAdminRole::class])->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', EnsureAdminRole::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::patch('/users/{id}/lock', [AdminUserController::class, 'lock']);
        Route::patch('/users/{id}/unlock', [AdminUserController::class, 'unlock']);
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
        Route::post('/payments/{id}/retry', [AdminPaymentController::class, 'retry']);
        Route::patch('/payments/{id}/status', [AdminPaymentController::class, 'updateStatus']);
        Route::get('/finance/refunds', [AdminFinanceOperationController::class, 'refunds']);
        Route::patch('/finance/refunds/{id}/status', [AdminFinanceOperationController::class, 'updateRefund']);
        Route::get('/finance/withdrawals', [AdminFinanceOperationController::class, 'withdrawals']);
        Route::patch('/finance/withdrawals/{id}/status', [AdminFinanceOperationController::class, 'updateWithdrawal']);
        Route::post('/finance/withdrawals/export', [AdminFinanceOperationController::class, 'exportWithdrawals']);

        // Court Types CRUD
        Route::apiResource('court-types', \App\Http\Controllers\Api\Admin\CourtTypeController::class);

        Route::get('/permissions', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'permissions']);
        Route::get('/roles/{id}/users', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'users']);
        Route::get('/roles', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'index']);
        Route::post('/roles', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'store']);
        Route::get('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'show']);
        Route::put('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'update']);
        Route::delete('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'destroy']);
        Route::put('/roles/{id}/permissions', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'updatePermissions']);

        Route::get('/policies/action-codes', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'actionCodes']);
        Route::get('/policies/rule-templates', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'ruleTemplates']);
        Route::get('/policies', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'index']);
        Route::post('/policies', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'store']);
        Route::get('/policies/{id}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'show']);
        Route::put('/policies/{id}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'update']);
        Route::post('/policies/{id}/clone-version', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'cloneVersion']);
        Route::post('/policies/{id}/publish', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'publish']);
        Route::patch('/policies/{id}/status', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateStatus']);
        Route::post('/policies/{id}/bindings', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'storeBinding']);
        Route::delete('/policies/{id}/bindings/{bindingId}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'destroyBinding']);
        Route::post('/policies/{id}/rules', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'storeRule']);
        Route::put('/policies/{id}/rules/{ruleId}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateRule']);
        Route::patch('/policies/{id}/rules/{ruleId}/toggle', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'toggleRule']);
        Route::get('/policies/{id}/evaluation-logs', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'evaluationLogs']);
    });

Route::middleware(['auth:sanctum', EnsureOwnerRole::class])
    ->prefix('owner')
    ->group(function (): void {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index']);

        // Venue Clusters & Venue Courts
        Route::apiResource('venue-clusters', \App\Http\Controllers\Api\Owner\VenueClusterController::class)->only(['index', 'show', 'update']);
        Route::apiResource('venue-courts', \App\Http\Controllers\Api\Owner\VenueCourtController::class);
        Route::get('/pricing', [OwnerPricingController::class, 'index']);
        Route::patch('/booking-configs/{venueClusterId}/duration', [OwnerPricingController::class, 'updateDuration']);
        Route::post('/price-slots', [OwnerPricingController::class, 'storePriceSlot']);
        Route::patch('/price-slots/{id}', [OwnerPricingController::class, 'updatePriceSlot']);
        Route::delete('/price-slots/{id}', [OwnerPricingController::class, 'destroyPriceSlot']);
    });

Route::middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/policies/required', [PolicyAcceptanceController::class, 'required']);
        Route::post('/policies/{policy}/accept', [PolicyAcceptanceController::class, 'accept']);

        Route::post('venue-clusters/resolve-map', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'resolveMapUrl']);
        Route::get('/court-types', [\App\Http\Controllers\Api\Admin\CourtTypeController::class, 'index']); // Read-only: Owner cần xem danh sách loại sân
        Route::get('/bookings/init', [\App\Http\Controllers\Api\Player\BookingController::class, 'initData']);
        Route::get('/bookings/schedule', [\App\Http\Controllers\Api\Player\BookingController::class, 'schedule']);
        Route::get('/bookings/check-availability', [\App\Http\Controllers\Api\Player\BookingController::class, 'checkAvailability']);
        Route::post('/bookings', [\App\Http\Controllers\Api\Player\BookingController::class, 'store']);
        Route::get('/bookings/{id}', [\App\Http\Controllers\Api\Player\BookingController::class, 'show']);
        Route::post('/bookings/{id}/payments/sepay', [SepayPaymentController::class, 'create']);
        Route::post('/bookings/{id}/payments/cancel', [SepayPaymentController::class, 'cancel']);
    });

Route::post('/sepay/ipn', [SepayPaymentController::class, 'ipn']);
Route::post('/callback/bank/withdraw', BankWithdrawalCallbackController::class);
