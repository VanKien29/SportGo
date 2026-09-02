<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminUiSettingsController;
use App\Http\Controllers\Api\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Api\Admin\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\FinanceOperationController as AdminFinanceOperationController;
use App\Http\Controllers\Api\Admin\SystemWalletController as AdminSystemWalletController;
use App\Http\Controllers\Api\Admin\SystemSettingController as AdminSystemSettingController;
use App\Http\Controllers\Api\Admin\PlatformFeeLedgerController as AdminPlatformFeeLedgerController;
use App\Http\Controllers\Api\Admin\PlatformFeeArrangementController as AdminPlatformFeeArrangementController;
use App\Http\Controllers\Api\Admin\PlatformFeePlanVersionController as AdminPlatformFeePlanVersionController;
use App\Http\Controllers\Api\Admin\PlatformFeePromotionController as AdminPlatformFeePromotionController;
use App\Http\Controllers\Api\Admin\PlatformFeeTierController as AdminPlatformFeeTierController;
use App\Http\Controllers\Api\Admin\PartnerApplicationController as AdminPartnerApplicationController;
use App\Http\Controllers\Api\Admin\PartnerContractController as AdminPartnerContractController;
use App\Http\Controllers\Api\Admin\PartnerTerminationRequestController as AdminPartnerTerminationRequestController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Api\Admin\MembershipPackageController as AdminMembershipPackageController;
use App\Http\Controllers\Api\Admin\AdminServiceCategoryController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\SetPasswordController;
use App\Http\Controllers\Api\Owner\BookingManagementController as OwnerBookingManagementController;
use App\Http\Controllers\Api\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Api\Owner\PartnerApplicationController as OwnerPartnerApplicationController;
use App\Http\Controllers\Api\Owner\PartnerContractController as OwnerPartnerContractController;
use App\Http\Controllers\Api\Owner\PartnerTerminationController as OwnerPartnerTerminationController;
use App\Http\Controllers\Api\Owner\BookingConfigController as OwnerBookingConfigController;
use App\Http\Controllers\Api\Payment\SepayPaymentController;
use App\Http\Controllers\Api\Common\PolicyAcceptanceController;
use App\Http\Controllers\Api\Owner\PricingController as OwnerPricingController;
use App\Http\Controllers\Api\Owner\PlatformFeeController as OwnerPlatformFeeController;
use App\Http\Controllers\Api\Owner\ScheduleLockController as OwnerScheduleLockController;
use App\Http\Controllers\Api\Owner\StaffController as OwnerStaffController;
use App\Http\Controllers\Api\Owner\StaffShiftController;
use App\Http\Controllers\Api\Owner\StaffDashboardController;
use App\Http\Controllers\Api\Owner\VenuePolicyController as OwnerVenuePolicyController;
use App\Http\Controllers\Api\Owner\VoucherController as OwnerVoucherController;
use App\Http\Controllers\Api\Owner\FinanceController as OwnerFinanceController;
use App\Http\Controllers\Api\Owner\RefundController as OwnerRefundController;
use App\Http\Controllers\Api\Owner\UiSettingsController as OwnerUiSettingsController;
use App\Http\Controllers\Api\Partner\PartnerApplicationDocumentDownloadController;
use App\Http\Controllers\Api\Partner\PartnerDocumentDownloadController;
use App\Http\Controllers\Api\User\PartnerApplicationController as UserPartnerApplicationController;
use App\Http\Controllers\Api\Owner\VenueUnlockRequestController;
use App\Http\Controllers\Api\Owner\CourtTypeRequestController;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureOwnerRole;
use App\Http\Middleware\EnsureActiveStaffShift;
use App\Http\Middleware\EnsureVenueStaffMenuPermission;
use App\Http\Middleware\EnforceVenueAccessRestrictions;
use App\Http\Middleware\RejectInactiveUser;
use App\Http\Controllers\Api\Admin\VenuePostController as AdminVenuePostController;
use App\Http\Controllers\Api\Public\SystemPostController as PublicSystemPostController;
use App\Http\Controllers\Api\Public\UserProfileController as PublicUserProfileController;
use App\Http\Controllers\Api\Admin\SystemPostController as AdminSystemPostController;
use App\Http\Controllers\Api\Owner\VenuePostController as OwnerVenuePostController;
use App\Http\Controllers\Api\Owner\OwnerVenueServiceController;
use App\Http\Controllers\Api\Player\VenuePostController as PlayerVenuePostController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Public\LocationController;
use App\Http\Controllers\Api\Public\VenueController;
use App\Http\Controllers\Api\Public\PublicAffiliateProductController;
use App\Http\Controllers\Api\Public\SystemProfileController;
use App\Http\Controllers\Api\Public\OfferController;
use App\Http\Controllers\Api\Public\PolicyController as PublicPolicyController;
use App\Http\Controllers\Api\Public\ReportController as PublicReportController;
use App\Http\Controllers\Api\Common\ChatController;

// Broadcasting auth endpoint — must use Sanctum so Bearer token is accepted
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return app(\Illuminate\Broadcasting\BroadcastManager::class)->auth($request);
})->middleware(['auth:sanctum', RejectInactiveUser::class]);

Route::get('/banners/active/{position?}', [AdminBannerController::class, 'getActiveBanners']);
Route::get('/system-profile', [SystemProfileController::class, 'show']);
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/policies', [PublicPolicyController::class, 'index']);

Route::get('/locations/provinces', [LocationController::class, 'provinces']);
Route::get('/locations/wards', [LocationController::class, 'wards']);
Route::get('/court-types', [\App\Http\Controllers\Api\Admin\CourtTypeController::class, 'index']);
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/filter-options', [VenueController::class, 'filterOptions']);
Route::get('/venues/{id}', [VenueController::class, 'show']);
Route::get('/venues/{id}/schedule', [VenueController::class, 'schedule']);
Route::get('/venues/{clusterId}/affiliate-products', [PublicAffiliateProductController::class, 'index']);
Route::post('/affiliate-products/{id}/click', [PublicAffiliateProductController::class, 'trackClick']);
Route::get('/matchmaking-posts', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'index']);
Route::get('/bookings/init', [\App\Http\Controllers\Api\Player\BookingController::class, 'initData']);
Route::get('/bookings/schedule', [\App\Http\Controllers\Api\Player\BookingController::class, 'schedule']);

Route::get('/chat/ai-history', [\App\Http\Controllers\Api\AiChatController::class, 'history']);
Route::post('/chat/ai-assistant', [\App\Http\Controllers\Api\AiChatController::class, 'ask']);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/register/verify-otp', [AuthController::class, 'verifyRegisterOtp'])->middleware('throttle:auth-otp-verify');
    Route::post('/register/resend-otp', [AuthController::class, 'resendRegisterOtp'])->middleware('throttle:auth-otp-send');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:auth-otp-send');
    Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:auth-otp-verify');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])->middleware('throttle:auth-otp-verify');
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
    Route::post('/google/exchange', [GoogleAuthController::class, 'exchange']);
    Route::middleware(['auth:sanctum', RejectInactiveUser::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/profile/email/request-otp', [AuthController::class, 'requestEmailChangeOtp'])->middleware('throttle:auth-otp-send');
        Route::post('/profile/email/verify-otp', [AuthController::class, 'verifyEmailChangeOtp'])->middleware('throttle:auth-otp-verify');
        Route::post('/profile/phone/change', [AuthController::class, 'changePhone']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
        Route::post('/profile/cover', [AuthController::class, 'uploadCover']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/files/download', [\App\Http\Controllers\Api\Common\FileDownloadController::class, 'download']);
        Route::post('/set-password', [SetPasswordController::class, 'store']);
    });
});

Route::prefix('admin/auth')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password/send-otp', [AdminForgotPasswordController::class, 'sendOtp'])->middleware('throttle:auth-otp-send');
    Route::post('/forgot-password/verify-otp', [AdminForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:auth-otp-verify');
    Route::post('/forgot-password/reset', [AdminForgotPasswordController::class, 'reset'])->middleware('throttle:auth-otp-verify');

    Route::middleware(['auth:sanctum', RejectInactiveUser::class, EnsureAdminRole::class])->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', RejectInactiveUser::class, EnsureAdminRole::class, EnsureAdminPermission::class])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/system-profile', [AdminSystemSettingController::class, 'show']);
        Route::post('/system-profile', [AdminSystemSettingController::class, 'update']);
        Route::get('/pending-counts', [\App\Http\Controllers\Api\Admin\AdminPendingCountsController::class, 'index']);
        Route::get('/work-center', [\App\Http\Controllers\Api\Common\WorkCenterController::class, 'admin']);
        Route::patch('/work-center/notifications/{notificationId}/read', [\App\Http\Controllers\Api\Common\WorkCenterController::class, 'markNotificationRead']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/auto-lock-config', [\App\Http\Controllers\Api\Admin\UserController::class, 'autoLockConfig']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::patch('/users/{id}/lock', [AdminUserController::class, 'lock']);
        Route::patch('/users/{id}/unlock', [AdminUserController::class, 'unlock']);
        Route::get('/vouchers', [AdminVoucherController::class, 'index']);
        Route::get('/vouchers/{id}', [AdminVoucherController::class, 'show']);
        Route::post('/vouchers', [AdminVoucherController::class, 'store']);
        Route::put('/vouchers/{id}', [AdminVoucherController::class, 'update']);
        Route::patch('/vouchers/{id}/deactivate', [AdminVoucherController::class, 'deactivate']);
        Route::patch('/vouchers/{id}/activate', [AdminVoucherController::class, 'activate']);
        Route::get('/membership-packages', [AdminMembershipPackageController::class, 'index']);
        Route::put('/membership-packages/{id}', [AdminMembershipPackageController::class, 'update']);
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
        Route::post('/payments/{id}/retry', [AdminPaymentController::class, 'retry']);
        Route::patch('/payments/{id}/status', [AdminPaymentController::class, 'updateStatus']);
        Route::get('/finance/refunds', [AdminFinanceOperationController::class, 'refunds']);
        Route::patch('/finance/refunds/{id}/status', [AdminFinanceOperationController::class, 'updateRefund']);
        Route::post('/finance/refunds/{id}/payout-qr', [AdminFinanceOperationController::class, 'refundPayoutQr']);
        Route::post('/finance/refunds/{id}/payout-check', [AdminFinanceOperationController::class, 'checkRefundPayout']);
        Route::post('/finance/refunds/export', [AdminFinanceOperationController::class, 'exportRefunds']);
        Route::get('/finance/withdrawals', [AdminFinanceOperationController::class, 'withdrawals']);
        Route::get('/finance/user-withdrawals', [AdminFinanceOperationController::class, 'userWithdrawals']);
        Route::patch('/finance/user-withdrawals/{id}/pay', [AdminFinanceOperationController::class, 'payUserWithdrawal']);
        Route::post('/finance/user-withdrawals/{id}/payout-qr', [AdminFinanceOperationController::class, 'userWithdrawalPayoutQr']);
        Route::post('/finance/user-withdrawals/{id}/payout-check', [AdminFinanceOperationController::class, 'checkUserWithdrawalPayout']);
        Route::patch('/finance/withdrawals/{id}/status', [AdminFinanceOperationController::class, 'updateWithdrawal']);
        Route::post('/finance/withdrawals/{id}/payout-qr', [AdminFinanceOperationController::class, 'withdrawalPayoutQr']);
        Route::post('/finance/withdrawals/{id}/payout-check', [AdminFinanceOperationController::class, 'checkWithdrawalPayout']);
        Route::post('/finance/withdrawals/export', [AdminFinanceOperationController::class, 'exportWithdrawals']);
        Route::get('/finance/system-wallet', [AdminSystemWalletController::class, 'show']);
        Route::post('/finance/system-wallet/sync', [AdminSystemWalletController::class, 'sync']);
        Route::put('/finance/system-wallet/settings', [AdminSystemWalletController::class, 'updateSettings']);
        Route::get('/platform-fee-ledgers', [AdminPlatformFeeLedgerController::class, 'index']);
        Route::post('/platform-fee-ledgers/preview', [AdminPlatformFeeLedgerController::class, 'preview']);
        Route::post('/platform-fee-ledgers', [AdminPlatformFeeLedgerController::class, 'store']);
        Route::get('/platform-fee-ledgers/{id}', [AdminPlatformFeeLedgerController::class, 'show']);
        Route::get('/platform-fee-ledgers/{id}/email-logs', [AdminPlatformFeeLedgerController::class, 'emailLogs']);
        Route::post('/platform-fee-ledgers/{id}/reminders', [AdminPlatformFeeLedgerController::class, 'sendReminder']);
        Route::patch('/platform-fee-ledgers/{id}/pay', [AdminPlatformFeeLedgerController::class, 'pay']);
        Route::patch('/platform-fee-ledgers/{id}/overdue', [AdminPlatformFeeLedgerController::class, 'overdue']);
        Route::patch('/platform-fee-ledgers/{id}/cancel', [AdminPlatformFeeLedgerController::class, 'cancel']);
        Route::patch('/platform-fee-ledgers/{id}/lock-venue', [AdminPlatformFeeLedgerController::class, 'lockVenue']);
        Route::patch('/platform-fee-ledgers/{id}/unlock-venue', [AdminPlatformFeeLedgerController::class, 'unlockVenue']);
        Route::get('/platform-fee-plans', [AdminPlatformFeePlanVersionController::class, 'index']);
        Route::post('/platform-fee-plans', [AdminPlatformFeePlanVersionController::class, 'store']);
        Route::get('/platform-fee-plans/{id}', [AdminPlatformFeePlanVersionController::class, 'show']);
        Route::put('/platform-fee-plans/{id}', [AdminPlatformFeePlanVersionController::class, 'update']);
        Route::post('/platform-fee-plans/{id}/schedule', [AdminPlatformFeePlanVersionController::class, 'schedule']);
        Route::post('/platform-fee-plans/{id}/cancel-schedule', [AdminPlatformFeePlanVersionController::class, 'cancelSchedule']);
        Route::delete('/platform-fee-plans/{id}', [AdminPlatformFeePlanVersionController::class, 'destroy']);
        Route::get('/platform-fee-arrangements', [AdminPlatformFeeArrangementController::class, 'index']);
        Route::get('/platform-fee-arrangements/preview', [AdminPlatformFeeArrangementController::class, 'preview']);
        Route::get('/platform-fee-arrangements/{id}', [AdminPlatformFeeArrangementController::class, 'show']);
        Route::post('/platform-fee-arrangements', [AdminPlatformFeeArrangementController::class, 'store']);
        Route::post('/platform-fee-arrangements/{id}/cancel', [AdminPlatformFeeArrangementController::class, 'cancel']);
        Route::get('/platform-fee-promotions', [AdminPlatformFeePromotionController::class, 'index']);
        Route::post('/platform-fee-promotions', [AdminPlatformFeePromotionController::class, 'store']);
        Route::put('/platform-fee-promotions/{id}', [AdminPlatformFeePromotionController::class, 'update']);
        Route::post('/platform-fee-promotions/{id}/publish', [AdminPlatformFeePromotionController::class, 'publish']);
        Route::post('/platform-fee-promotions/{id}/deactivate', [AdminPlatformFeePromotionController::class, 'deactivate']);
        Route::delete('/platform-fee-promotions/{id}', [AdminPlatformFeePromotionController::class, 'destroy']);
        Route::get('/platform-fee-tiers', [AdminPlatformFeeTierController::class, 'index']);
        Route::post('/platform-fee-tiers', [AdminPlatformFeeTierController::class, 'store']);
        Route::put('/platform-fee-tiers/{id}', [AdminPlatformFeeTierController::class, 'update']);
        Route::patch('/platform-fee-tiers/{id}/deactivate', [AdminPlatformFeeTierController::class, 'deactivate']);
        Route::patch('/platform-fee-tiers/{id}/reactivate', [AdminPlatformFeeTierController::class, 'reactivate']);
        Route::delete('/platform-fee-tiers/{id}', [AdminPlatformFeeTierController::class, 'destroy']);
        Route::get('/platform-fee-settings', [AdminPlatformFeeTierController::class, 'settings']);
        Route::put('/platform-fee-settings', [AdminPlatformFeeTierController::class, 'updateSettings']);

        Route::get('/ui-settings', [AdminUiSettingsController::class, 'getSettings']);
        Route::post('/ui-settings', [AdminUiSettingsController::class, 'updateSettings']);

        Route::get('/partner-applications', [AdminPartnerApplicationController::class, 'index']);
        Route::get('/partner-applications/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::get('/partner-applications/{id}', [AdminPartnerApplicationController::class, 'show']);
        Route::post('/partner-applications/{id}/approve', [AdminPartnerApplicationController::class, 'approve']);
        Route::post('/partner-applications/{id}/reject', [AdminPartnerApplicationController::class, 'reject']);
        Route::post('/partner-applications/{id}/sign-document/request-otp', [AdminPartnerApplicationController::class, 'requestSignDocumentOtp']);
        Route::post('/partner-applications/{id}/sign-document/verify-otp', [AdminPartnerApplicationController::class, 'verifySignDocumentOtp']);
        Route::post('/partner-applications/{id}/sign-document', [AdminPartnerApplicationController::class, 'signDocument']);
        Route::post('/partner-applications/{id}/terminate', [AdminPartnerApplicationController::class, 'terminate']);
        Route::post('/partner-applications/{id}/confirm-termination', [AdminPartnerApplicationController::class, 'confirmTermination']);

        Route::get('/partner-profiles', [AdminPartnerApplicationController::class, 'index']);
        Route::get('/partner-profiles/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::get('/partner-profiles/{id}', [AdminPartnerApplicationController::class, 'show']);
        Route::post('/partner-profiles/{id}/approve', [AdminPartnerApplicationController::class, 'approve']);
        Route::post('/partner-profiles/{id}/reject', [AdminPartnerApplicationController::class, 'reject']);
        Route::post('/partner-profiles/{id}/sign-document/request-otp', [AdminPartnerApplicationController::class, 'requestSignDocumentOtp']);
        Route::post('/partner-profiles/{id}/sign-document/verify-otp', [AdminPartnerApplicationController::class, 'verifySignDocumentOtp']);
        Route::post('/partner-profiles/{id}/sign-document', [AdminPartnerApplicationController::class, 'signDocument']);
        Route::post('/partner-profiles/{id}/terminate', [AdminPartnerApplicationController::class, 'terminate']);
        Route::post('/partner-profiles/{id}/confirm-termination', [AdminPartnerApplicationController::class, 'confirmTermination']);

        Route::get('/partner-termination-requests', [AdminPartnerTerminationRequestController::class, 'index']);
        Route::get('/partner-termination-requests/settings', [AdminPartnerTerminationRequestController::class, 'settings']);
        Route::put('/termination-settings', [AdminPartnerTerminationRequestController::class, 'updateSettings']);
        Route::get('/partner-termination-requests/{id}', [AdminPartnerTerminationRequestController::class, 'show']);
        Route::post('/partner-termination-requests/{id}/mark-ready-final-document', [AdminPartnerTerminationRequestController::class, 'markReadyFinalDocument']);
        Route::post('/partner-termination-requests/{id}/final-document/preview', [AdminPartnerTerminationRequestController::class, 'previewFinalDocument']);
        Route::post('/partner-termination-requests/{id}/final-document/sign/send-otp', [AdminPartnerTerminationRequestController::class, 'finalDocumentSignSendOtp']);
        Route::post('/partner-termination-requests/{id}/final-document/sign', [AdminPartnerTerminationRequestController::class, 'finalDocumentSign']);
        Route::post('/partner-termination-requests/{id}/manual-resolve-booking', [AdminPartnerTerminationRequestController::class, 'manualResolveBooking']);
        Route::post('/partner-termination-requests/{id}/unilateral-notice/sign/send-otp', [AdminPartnerTerminationRequestController::class, 'unilateralNoticeSignSendOtp']);
        Route::post('/partner-termination-requests/{id}/unilateral-notice/sign', [AdminPartnerTerminationRequestController::class, 'unilateralNoticeSign']);
        Route::post('/partner-termination-requests/{id}/unilateral-notice/withdraw', [AdminPartnerTerminationRequestController::class, 'withdrawUnilateralNotice']);
        Route::post('/partner-termination-requests/{id}/unilateral-notice/reconsideration/resolve', [AdminPartnerTerminationRequestController::class, 'resolveUnilateralReconsideration']);

        // Partner Contracts
        Route::post('/contracts/{id}/send-email', [AdminPartnerContractController::class, 'sendEmail']);
        Route::post('/contracts/{id}/approve-signature/request-otp', [AdminPartnerContractController::class, 'requestApproveSignatureOtp']);
        Route::post('/contracts/{id}/approve-signature/verify-otp', [AdminPartnerContractController::class, 'verifyApproveSignatureOtp']);
        Route::post('/contracts/{id}/approve-signature', [AdminPartnerContractController::class, 'approveSignature']);
        Route::post('/contracts/{id}/terminate', [AdminPartnerContractController::class, 'terminate']);
        Route::post('/contracts/{id}/approve-termination', [AdminPartnerContractController::class, 'approveTermination']);

        Route::get('/banners', [AdminBannerController::class, 'index']);
        Route::post('/banners', [AdminBannerController::class, 'store']);
        Route::post('/banners/reorder', [AdminBannerController::class, 'reorder']);
        Route::patch('/banners/{id}', [AdminBannerController::class, 'update']);
        Route::delete('/banners/{id}', [AdminBannerController::class, 'destroy']);

        Route::get('/reports/auto-resolve-config', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'autoResolveConfig']);
        Route::post('/report-resolve-policy', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'saveAutoResolveConfig']);
        Route::get('/reports', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'index']);
        Route::get('/reports/{id}', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'show']);
        Route::patch('/reports/{id}/review', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'review']);
        Route::patch('/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'resolve']);
        Route::post('/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'resolve']);
        Route::post('/reports/{id}/notify', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'sendNotification']);
        Route::get('/violation-records/{targetType}/{targetId}', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'violationRecord']);
        Route::apiResource('violation-types', \App\Http\Controllers\Api\Admin\ViolationTypeController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/complaints/auto-resolve-config', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'autoResolveConfig']);
        Route::post('/complaint-resolve-policy', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'saveAutoResolveConfig']);
        Route::get('/complaints', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'index']);
        Route::get('/complaints/{id}', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'show']);
        Route::patch('/complaints/{id}/assign', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'assign']);
        Route::patch('/complaints/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'resolve']);
        Route::post('/complaints/{id}/notify', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'sendNotification']);

        Route::apiResource('court-types', \App\Http\Controllers\Api\Admin\CourtTypeController::class);

        Route::patch('/amenities/{id}/review', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'review']);
        Route::apiResource('amenities', \App\Http\Controllers\Api\Admin\AmenityController::class);

        Route::patch('/service-categories/{id}/toggle-status', [AdminServiceCategoryController::class, 'toggleStatus']);
        Route::apiResource('service-categories', AdminServiceCategoryController::class);

        Route::get('/permissions', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'permissions']);
        Route::get('/roles/matrix', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'matrix']);
        Route::get('/roles/{id}/users', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'users']);
        Route::get('/roles', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'index']);
        Route::post('/roles', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'store']);
        Route::get('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'show']);
        Route::put('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'update']);
        Route::delete('/roles/{id}', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'destroy']);
        Route::put('/roles/{id}/permissions', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'updatePermissions']);
        Route::patch('/roles/{id}/permissions/toggle', [\App\Http\Controllers\Api\Admin\AdminRoleController::class, 'togglePermission']);

        Route::get('/policies/action-codes', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'actionCodes']);
        Route::get('/policies/rule-templates', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'ruleTemplates']);
        Route::get('/policies', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'index']);
        Route::post('/policies', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'store']);
        Route::get('/policies/{id}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'show']);
        Route::put('/policies/{id}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'update']);
        Route::delete('/policies/{id}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'destroy']);
        Route::put('/policies/{id}/configuration', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateConfiguration']);
        Route::put('/policies/{id}/cancel-refund-tiers', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateCancelRefundTiers']);
        Route::get('/policies/{id}/moderation-thresholds', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'scoreModerationThresholds']);
        Route::put('/policies/{id}/moderation-thresholds', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateModerationThresholds']);

        Route::post('/policies/{id}/clone-version', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'cloneVersion']);
        Route::post('/policies/{id}/publish', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'publish']);
        Route::patch('/policies/{id}/status', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateStatus']);
        Route::post('/policies/{id}/bindings', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'storeBinding']);
        Route::delete('/policies/{id}/bindings/{bindingId}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'destroyBinding']);
        Route::get('/policies/{id}/rules/{ruleId}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'showRule']);
        Route::post('/policies/{id}/rules', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'storeRule']);
        Route::put('/policies/{id}/rules/{ruleId}', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'updateRule']);
        Route::patch('/policies/{id}/rules/{ruleId}/toggle', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'toggleRule']);
        Route::get('/policies/{id}/evaluation-logs', [\App\Http\Controllers\Api\Admin\AdminPolicyController::class, 'evaluationLogs']);
        // Venue Cluster management
        Route::get('/venue-clusters', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'index']);
        Route::get('/venue-clusters/{id}', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'show']);
        Route::patch('/venue-clusters/{id}/lock', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'lock']);
        Route::patch('/venue-clusters/{id}/unlock', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'unlock']);
        Route::patch('/venue-clusters/{id}/amenities', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'updateAmenities']);
        Route::patch('/venue-clusters/{clusterId}/approval-requests/{requestId}/approve', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'approveRequest']);
        Route::patch('/venue-clusters/{clusterId}/approval-requests/{requestId}/reject', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'rejectRequest']);
        Route::patch('/venue-clusters/{clusterId}/approval-requests/{requestId}/supplement', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'requestSupplementForScale']);
        // Venue Location Change Requests (Admin duyệt/từ chối thay đổi vị trí)
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/approve', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'approveLocationChange']);
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/reject', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'rejectLocationChange']);
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/supplement', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'requestSupplementForLocationChange']);
        Route::patch('/venue-clusters/{clusterId}/unlock-requests/{requestId}/approve', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'approveUnlockRequest']);
        Route::patch('/venue-clusters/{clusterId}/unlock-requests/{requestId}/reject', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'rejectUnlockRequest']);
        Route::patch('/venue-clusters/{clusterId}/information-change-requests/{requestId}/approve', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'approveInformationChange']);
        Route::patch('/venue-clusters/{clusterId}/information-change-requests/{requestId}/reject', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'rejectInformationChange']);

        // Content Moderation
        Route::get('/moderation/config', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'getConfig']);
        Route::post('/moderation/config', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'saveConfig']);
        Route::get('/moderation/queue', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'queue']);
        Route::post('/moderation/posts/{type}/{id}/approve', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'approvePost']);
        Route::post('/moderation/posts/{type}/{id}/reject', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'rejectPost']);
        Route::post('/moderation/posts/{type}/{id}/hide', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'hidePost']);
        Route::post('/moderation/posts/{type}/{id}/notify-author', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'notifyAuthor']);
        Route::delete('/moderation/posts/{type}/{id}', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'deletePost']);
        Route::post('/moderation/posts/{type}/{id}/ai-recheck', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'aiRecheck']);
        Route::post('/moderation/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'resolveReport']);

        // Admin Venue Posts
        Route::get('/venue-posts', [AdminVenuePostController::class, 'index']);
        Route::get('/venue-posts/{id}', [AdminVenuePostController::class, 'show']);
        Route::patch('/venue-posts/{id}/approve', [AdminVenuePostController::class, 'approve']);
        Route::delete('/venue-posts/{id}', [AdminVenuePostController::class, 'destroy']);
        Route::post('/venue-posts/{id}/restore', [AdminVenuePostController::class, 'restore']);

        // Admin System Posts
        Route::post('/system-posts/upload-editor-image', [AdminSystemPostController::class, 'uploadEditorImage']);
        Route::apiResource('system-posts', AdminSystemPostController::class);

        // User Lock Management
        Route::post('/user-lock-policy', [\App\Http\Controllers\Api\Admin\UserController::class, 'saveAutoLockConfig']);
        Route::post('/users/{user}/lock', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'lock']);
        Route::post('/users/{user}/unlock', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'unlock']);
        Route::get('/users/{user}/lock-logs', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'lockLogs']);

        // Admin Comment & Post Detail (phục vụ chi tiết user)
        Route::get('/comments/{comment}', [\App\Http\Controllers\Api\Admin\AdminCommentController::class, 'show']);
        Route::post('/comments/{comment}/action', [\App\Http\Controllers\Api\Admin\AdminCommentController::class, 'processAction']);
        Route::get('/posts/{post}', [\App\Http\Controllers\Api\Admin\AdminPostController::class, 'show']);
        Route::get('/posts/{post}/likes', [\App\Http\Controllers\Api\Admin\AdminPostController::class, 'likes']);
        Route::post('/posts/{post}/action', [\App\Http\Controllers\Api\Admin\AdminPostController::class, 'processAction']);
    });

Route::middleware(['auth:sanctum', RejectInactiveUser::class, EnsureOwnerRole::class, EnsureVenueStaffMenuPermission::class, EnforceVenueAccessRestrictions::class])
    ->prefix('owner')
    ->group(function (): void {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index']);
        Route::get('/staff-dashboard/overview', [StaffDashboardController::class, 'overview']);
        Route::get('/ui-settings', [OwnerUiSettingsController::class, 'getSettings']);
        Route::post('/ui-settings', [OwnerUiSettingsController::class, 'updateSettings']);
        Route::get('/booking-configs', [OwnerBookingConfigController::class, 'index']);
        Route::put('/booking-configs/{venueClusterId}', [OwnerBookingConfigController::class, 'update']);

        // Wallet & Withdrawals
        Route::get('/wallet', [\App\Http\Controllers\Api\Owner\WalletController::class, 'getWallet']);
        Route::post('/wallet/withdraw', [\App\Http\Controllers\Api\Owner\WalletController::class, 'withdraw']);
        Route::get('/wallet/withdrawals', [\App\Http\Controllers\Api\Owner\WalletController::class, 'getWithdrawals']);
        // Partner Profile
        Route::get('/partner-applications', [OwnerPartnerApplicationController::class, 'myApplications']);
        Route::get('/partner-application/terms', [OwnerPartnerApplicationController::class, 'onboardingTerms']);
        Route::get('/partner-application', [OwnerPartnerApplicationController::class, 'myApplication']);
        Route::get('/my-partner-profile', [OwnerPartnerApplicationController::class, 'myApplication']);
        Route::get('/my-partner-profile/documents', [OwnerPartnerApplicationController::class, 'documents']);
        Route::get('/my-partner-profile/documents/{id}/download', PartnerDocumentDownloadController::class);
        Route::post('/my-partner-profile/request-termination', [OwnerPartnerApplicationController::class, 'requestTermination']);
        Route::post('/partner-applications/new-cluster', [OwnerPartnerApplicationController::class, 'storeNewCluster']);
        Route::post('/contracts/{id}/sign', [OwnerPartnerContractController::class, 'sign']);
        Route::post('/contracts/{id}/request-termination', [OwnerPartnerContractController::class, 'requestTermination']);

        Route::get('/venue-clusters/{id}/termination/eligibility', [OwnerPartnerTerminationController::class, 'eligibility']);
        Route::post('/venue-clusters/{id}/termination/preview', [OwnerPartnerTerminationController::class, 'preview']);
        Route::post('/venue-clusters/{id}/termination/send-otp', [OwnerPartnerTerminationController::class, 'sendOtp']);
        Route::post('/venue-clusters/{id}/termination/submit', [OwnerPartnerTerminationController::class, 'submit']);
        Route::get('/termination-requests/{id}', [OwnerPartnerTerminationController::class, 'show']);
        Route::get('/termination-requests/{id}/future-bookings', [OwnerPartnerTerminationController::class, 'futureBookings']);
        Route::post('/termination-requests/{id}/future-bookings/bulk-action', [OwnerPartnerTerminationController::class, 'bulkAction']);
        Route::post('/termination-requests/{id}/withdrawals', [OwnerPartnerTerminationController::class, 'storeWithdrawal']);
        Route::post('/termination-requests/{id}/cancel/preview', [OwnerPartnerTerminationController::class, 'cancelPreview']);
        Route::post('/termination-requests/{id}/cancel/send-otp', [OwnerPartnerTerminationController::class, 'cancelSendOtp']);
        Route::post('/termination-requests/{id}/cancel', [OwnerPartnerTerminationController::class, 'cancel']);
        Route::post('/termination-requests/{id}/final-document/sign/send-otp', [OwnerPartnerTerminationController::class, 'finalDocumentSignSendOtp']);
        Route::post('/termination-requests/{id}/final-document/sign', [OwnerPartnerTerminationController::class, 'finalDocumentSign']);
        Route::post('/termination-requests/{id}/unilateral-notice/acknowledge', [OwnerPartnerTerminationController::class, 'acknowledgeUnilateralNotice']);
        Route::post('/termination-requests/{id}/unilateral-notice/reconsideration', [OwnerPartnerTerminationController::class, 'requestUnilateralReconsideration']);

        // Venue Clusters & Venue Courts
        Route::apiResource('venue-clusters', \App\Http\Controllers\Api\Owner\VenueClusterController::class)->only(['index', 'show', 'update']);
        Route::post('/venue-clusters/{id}/media', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'uploadMedia']);
        Route::delete('/venue-clusters/{clusterId}/media/{mediaId}', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'deleteMedia']);
        Route::put('/venue-courts/bulk-layout', [\App\Http\Controllers\Api\Owner\VenueCourtController::class, 'updateLayoutBulk']);
        Route::apiResource('venue-courts', \App\Http\Controllers\Api\Owner\VenueCourtController::class);
        Route::get('/staff', [OwnerStaffController::class, 'index']);
        Route::post('/staff', [OwnerStaffController::class, 'store']);
        Route::put('/staff/{id}', [OwnerStaffController::class, 'update']);
        Route::patch('/staff/{id}/deactivate', [OwnerStaffController::class, 'deactivate']);

        // Staff Shifts & Schedules
        Route::get('/staff-shifts', [StaffShiftController::class, 'listShifts']);
        Route::post('/staff-shifts', [StaffShiftController::class, 'storeShift']);
        Route::put('/staff-shifts/{id}', [StaffShiftController::class, 'updateShift']);
        Route::delete('/staff-shifts/{id}', [StaffShiftController::class, 'destroyShift']);
        Route::get('/staff-shifts/schedules', [StaffShiftController::class, 'listSchedules']);
        Route::post('/staff-shifts/schedules', [StaffShiftController::class, 'storeSchedules']);
        Route::put('/staff-shifts/schedules/{id}', [StaffShiftController::class, 'updateSchedule']);
        Route::delete('/staff-shifts/schedules/{id}', [StaffShiftController::class, 'destroySchedule']);
        Route::get('/staff-shifts/attendance-report', [StaffShiftController::class, 'attendanceReport']);
        Route::get('/staff-shifts/my-schedules', [StaffShiftController::class, 'mySchedules']);
        Route::get('/staff-shifts/schedules/{id}/handover-summary', [StaffShiftController::class, 'handoverSummary']);
        Route::post('/staff-shifts/schedules/{id}/check-in', [StaffShiftController::class, 'checkIn']);
        Route::post('/staff-shifts/schedules/{id}/check-out', [StaffShiftController::class, 'checkOut']);
        Route::get('/vouchers', [OwnerVoucherController::class, 'index']);
        Route::get('/vouchers/{id}', [OwnerVoucherController::class, 'show']);
        Route::post('/vouchers', [OwnerVoucherController::class, 'store']);
        Route::put('/vouchers/{id}', [OwnerVoucherController::class, 'update']);
        Route::patch('/vouchers/{id}/deactivate', [OwnerVoucherController::class, 'deactivate']);
        // Venue Court Approval Requests (Owner gửi yêu cầu quy mô)
        Route::get('/venue-clusters/{clusterId}/approval-requests', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/approval-requests/preview', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'preview']);
        Route::post('/venue-clusters/{clusterId}/approval-requests', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'store']);
        Route::post('/venue-clusters/{clusterId}/approval-requests/{requestId}/supplement', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'supplement']);
        Route::patch('/venue-clusters/{clusterId}/approval-requests/{requestId}/cancel', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'cancel']);
        // Venue Location Change Requests (Owner gửi yêu cầu thay đổi vị trí)
        Route::get('/venue-clusters/{clusterId}/location-change-requests', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/location-change-requests/preview', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'preview']);
        Route::post('/venue-clusters/{clusterId}/location-change-requests', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'store']);
        Route::post('/venue-clusters/{clusterId}/location-change-requests/{requestId}/supplement', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'supplement']);
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/cancel', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'cancel']);
        // Venue Information Change Requests (Owner gửi yêu cầu chỉnh sửa thông tin sân)
        Route::get('/venue-clusters/{clusterId}/information-change-requests', [\App\Http\Controllers\Api\Owner\VenueInformationChangeController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/information-change-requests', [\App\Http\Controllers\Api\Owner\VenueInformationChangeController::class, 'store']);
        Route::patch('/venue-clusters/{clusterId}/information-change-requests/{requestId}/cancel', [\App\Http\Controllers\Api\Owner\VenueInformationChangeController::class, 'cancel']);
        Route::post('/venue-clusters/{clusterId}/temp-media', [\App\Http\Controllers\Api\Owner\VenueInformationChangeController::class, 'uploadTempMedia']);

        Route::get('/venue-policies', [OwnerVenuePolicyController::class, 'index']);
        Route::post('/venue-policies/rules', [OwnerVenuePolicyController::class, 'storeRule']);
        Route::delete('/venue-policies/rules/{id}', [OwnerVenuePolicyController::class, 'destroyRule']);
        Route::post('/venue-policies/notices', [OwnerVenuePolicyController::class, 'storeNotice']);
        Route::put('/venue-policies/notices/{id}', [OwnerVenuePolicyController::class, 'updateNotice']);
        Route::get('/pricing', [OwnerPricingController::class, 'index']);
        Route::get('/pricing-rules', [OwnerPricingController::class, 'index']);
        Route::patch('/booking-configs/{venueClusterId}/duration', [OwnerPricingController::class, 'updateDuration']);
        Route::put('/base-prices/{courtTypeId}', [OwnerPricingController::class, 'updateBasePrice']);
        Route::post('/price-slots', [OwnerPricingController::class, 'storePriceSlot']);
        Route::patch('/price-slots/{id}', [OwnerPricingController::class, 'updatePriceSlot']);
        Route::delete('/price-slots/{id}', [OwnerPricingController::class, 'destroyPriceSlot']);
        Route::post('/holiday-prices', [OwnerPricingController::class, 'storeHolidayPrice']);
        Route::patch('/holiday-prices/{id}', [OwnerPricingController::class, 'updateHolidayPrice']);
        Route::delete('/holiday-prices/{id}', [OwnerPricingController::class, 'destroyHolidayPrice']);
        Route::get('/platform-fees', [OwnerPlatformFeeController::class, 'index']);
        Route::get('/platform-fees/overview', [OwnerPlatformFeeController::class, 'overview']);
        Route::patch('/platform-fees/settings', [OwnerPlatformFeeController::class, 'updateBillingSettings']);
        Route::get('/platform-fees/arrangements', [OwnerPlatformFeeController::class, 'arrangements']);
        Route::post('/platform-fees/arrangements/{id}/accept', [OwnerPlatformFeeController::class, 'acceptArrangement']);
        Route::post('/platform-fees/arrangements/{id}/reject', [OwnerPlatformFeeController::class, 'rejectArrangement']);
        Route::post('/platform-fees/prepay', [OwnerPlatformFeeController::class, 'createAdvancePayment']);
        Route::get('/platform-fees/{id}/balance-preview', [OwnerPlatformFeeController::class, 'previewBalancePayment']);
        Route::get('/platform-fees/{id}', [OwnerPlatformFeeController::class, 'show']);
        Route::post('/platform-fees/{id}/payment', [OwnerPlatformFeeController::class, 'createPayment']);
        Route::post('/platform-fees/{id}/pay-from-balance', [OwnerPlatformFeeController::class, 'payFromBalance']);
        Route::patch('/platform-fees/{id}/cancel', [OwnerPlatformFeeController::class, 'cancel']);
        Route::get('/schedule-locks', [OwnerScheduleLockController::class, 'index']);
        Route::post('/schedule-locks/preview', [OwnerScheduleLockController::class, 'preview']);
        Route::post('/schedule-locks/unlock', [OwnerScheduleLockController::class, 'unlockRanges']);
        Route::post('/schedule-locks', [OwnerScheduleLockController::class, 'store']);
        Route::delete('/schedule-locks/{id}', [OwnerScheduleLockController::class, 'destroy']);
        Route::post('/amenities/request', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'requestAmenity']);
        Route::post('/court-types/request', [CourtTypeRequestController::class, 'store']);

        // Finance / Wallet
        Route::get('/finance/wallets', [OwnerFinanceController::class, 'wallets']);
        Route::get('/finance/bank-accounts', [OwnerFinanceController::class, 'bankAccounts']);
        Route::post('/finance/bank-accounts', [OwnerFinanceController::class, 'storeBankAccount']);
        Route::patch('/finance/bank-accounts/{id}', [OwnerFinanceController::class, 'updateBankAccount']);
        Route::get('/finance/ledgers', [OwnerFinanceController::class, 'ledgers']);
        Route::get('/finance/withdrawals', [OwnerFinanceController::class, 'withdrawals']);
        Route::post('/finance/withdrawals', [OwnerFinanceController::class, 'storeWithdrawal']);
        Route::patch('/finance/withdrawals/{id}/cancel', [OwnerFinanceController::class, 'cancelWithdrawal']);
        Route::get('/refunds', [OwnerRefundController::class, 'index']);
        Route::patch('/refunds/{id}/decision', [OwnerRefundController::class, 'decide']);
        Route::get('/bookings', [OwnerBookingManagementController::class, 'index']);
        Route::get('/bookings/schedule', [OwnerBookingManagementController::class, 'schedule']);
        Route::get('/bookings/recurring-groups', [OwnerBookingManagementController::class, 'recurringGroups']);
        Route::get('/bookings/eligible-vouchers', [OwnerBookingManagementController::class, 'eligibleVouchers']);
        Route::post('/bookings/counter', [OwnerBookingManagementController::class, 'storeCounter'])
            ->middleware(EnsureActiveStaffShift::class);
        Route::post('/bookings/recurring/preview', [OwnerBookingManagementController::class, 'previewRecurring']);
        Route::post('/bookings/recurring', [OwnerBookingManagementController::class, 'storeRecurring'])
            ->middleware(EnsureActiveStaffShift::class);
        Route::post('/bookings/recurring-groups/{groupCode}/payments/collect', [OwnerBookingManagementController::class, 'collectRecurringGroupPayment'])
            ->middleware(EnsureActiveStaffShift::class);
        Route::get('/bookings/{id}', [OwnerBookingManagementController::class, 'show']);
        Route::post('/bookings/{id}/payments/collect', [OwnerBookingManagementController::class, 'collectPayment'])
            ->middleware(EnsureActiveStaffShift::class);
        Route::patch('/bookings/{id}/status', [OwnerBookingManagementController::class, 'updateStatus'])
            ->middleware(EnsureActiveStaffShift::class);
        Route::get('/bookings/{id}/court-options', [OwnerBookingManagementController::class, 'courtOptions']);
        Route::patch('/bookings/{id}/court', [OwnerBookingManagementController::class, 'changeCourt'])
            ->middleware(EnsureActiveStaffShift::class);
        // Owner Venue Posts
        Route::post('/venue-posts/upload-editor-image', [OwnerVenuePostController::class, 'uploadEditorImage']);
        Route::post('/venue-posts/{id}/restore', [OwnerVenuePostController::class, 'restore']);
        Route::apiResource('venue-posts', OwnerVenuePostController::class);

        // Matchmaking Posts (Giao lưu tại sân)
        Route::get('/matchmaking-posts', [\App\Http\Controllers\Api\Owner\OwnerPlayerPostController::class, 'index']);
        Route::get('/matchmaking-posts/eligible-bookings', [\App\Http\Controllers\Api\Owner\OwnerPlayerPostController::class, 'eligibleBookings']);
        Route::post('/matchmaking-posts', [\App\Http\Controllers\Api\Owner\OwnerPlayerPostController::class, 'store'])->middleware('throttle:5,1');
        Route::patch('/matchmaking-posts/{id}/hide', [\App\Http\Controllers\Api\Owner\OwnerPlayerPostController::class, 'hide']);
        Route::post('/matchmaking-posts/{id}/report', [\App\Http\Controllers\Api\Owner\OwnerPlayerPostController::class, 'report']);

        // Complaints
        Route::get('/complaints', [\App\Http\Controllers\Api\Owner\OwnerComplaintController::class, 'index']);
        Route::get('/complaints/{id}', [\App\Http\Controllers\Api\Owner\OwnerComplaintController::class, 'show']);
        Route::post('/complaints/{id}/reply', [\App\Http\Controllers\Api\Owner\OwnerComplaintController::class, 'reply']);

        // Cửa hàng tiếp thị liên kết (Affiliate Shop)
        Route::get('/venue-clusters/{clusterId}/affiliate-products', [\App\Http\Controllers\Api\Owner\OwnerAffiliateProductController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/affiliate-products', [\App\Http\Controllers\Api\Owner\OwnerAffiliateProductController::class, 'store']);
        Route::post('/affiliate-products/{id}', [\App\Http\Controllers\Api\Owner\OwnerAffiliateProductController::class, 'update']);
        Route::delete('/affiliate-products/{id}', [\App\Http\Controllers\Api\Owner\OwnerAffiliateProductController::class, 'destroy']);
        Route::patch('/affiliate-products/{id}/toggle-status', [\App\Http\Controllers\Api\Owner\OwnerAffiliateProductController::class, 'toggleStatus']);

        // Dịch vụ & Sản phẩm tại sân (On-site Services & Products)
        Route::get('/venue-clusters/{clusterId}/services', [OwnerVenueServiceController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/services', [OwnerVenueServiceController::class, 'store']);
        Route::put('/venue-services/{id}', [OwnerVenueServiceController::class, 'update']);
        Route::delete('/venue-services/{id}', [OwnerVenueServiceController::class, 'destroy']);
        Route::patch('/venue-services/{id}/toggle-status', [OwnerVenueServiceController::class, 'toggleStatus']);
    });

Route::middleware(['auth:sanctum', RejectInactiveUser::class, EnsureOwnerRole::class, EnsureVenueStaffMenuPermission::class])
    ->prefix('owner')
    ->group(function (): void {
        Route::get('/work-center', [\App\Http\Controllers\Api\Common\WorkCenterController::class, 'owner']);
        Route::patch('/work-center/notifications/{notificationId}/read', [\App\Http\Controllers\Api\Common\WorkCenterController::class, 'markNotificationRead']);
        Route::get('/venue-clusters/{clusterId}/unlock-requests', [VenueUnlockRequestController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/unlock-requests', [VenueUnlockRequestController::class, 'store']);
        Route::patch('/venue-clusters/{clusterId}/unlock-requests/{requestId}/cancel', [VenueUnlockRequestController::class, 'cancel']);
    });

Route::middleware(['auth:sanctum', RejectInactiveUser::class])
    ->group(function (): void {
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);

        Route::get('/user/partner-application', [UserPartnerApplicationController::class, 'show']);
        Route::get('/user/partner-application/terms', [UserPartnerApplicationController::class, 'onboardingTerms']);
        Route::get('/user/partner-application/{id}', [UserPartnerApplicationController::class, 'detail'])->whereNumber('id');
        Route::get('/user/partner-application/banks', [UserPartnerApplicationController::class, 'banks']);
        Route::get('/user/partner-application/provinces', [UserPartnerApplicationController::class, 'provinces']);
        Route::get('/user/partner-application/provinces/{provinceCode}/wards', [UserPartnerApplicationController::class, 'wards']);
        Route::post('/user/partner-application/resolve-map', [UserPartnerApplicationController::class, 'resolveMap']);
        Route::post('/user/partner-application/reverse-map', [UserPartnerApplicationController::class, 'reverseMap']);
        Route::post('/user/partner-application/preview', [UserPartnerApplicationController::class, 'preview']);
        Route::post('/user/partner-application', [UserPartnerApplicationController::class, 'store']);
        Route::post('/user/partner-application/{id}/draft', [UserPartnerApplicationController::class, 'updateDraft']);
        Route::post('/user/partner-application/{id}/submit', [UserPartnerApplicationController::class, 'submitSigned']);
        Route::post('/user/partner-application/{id}/supplement-documents', [UserPartnerApplicationController::class, 'supplementDocuments']);
        Route::post('/user/partner-application/{id}/cancel', [UserPartnerApplicationController::class, 'cancel']);
        Route::get('/user/partner-application/documents', [UserPartnerApplicationController::class, 'documents']);
        Route::get('/user/partner-application/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::get('/user/partner-application/pending-contract', [UserPartnerApplicationController::class, 'pendingContract']);
        Route::post('/user/partner-application/sign-contract/request-otp', [UserPartnerApplicationController::class, 'requestContractSignatureOtp']);
        Route::post('/user/partner-application/sign-contract/verify-otp', [UserPartnerApplicationController::class, 'verifyContractSignatureOtp']);
        Route::post('/user/partner-application/sign-contract', [UserPartnerApplicationController::class, 'signContract']);
        Route::post('/user/partner-application/{id}/sign-document/request-otp', [UserPartnerApplicationController::class, 'requestDocumentSignatureOtp']);
        Route::post('/user/partner-application/{id}/sign-document/verify-otp', [UserPartnerApplicationController::class, 'verifyDocumentSignatureOtp']);
        Route::post('/user/partner-application/{id}/sign-document', [UserPartnerApplicationController::class, 'signDocument']);
        Route::get('/files/documents/{id}/download', PartnerDocumentDownloadController::class);

        Route::get('/policies/required', [PolicyAcceptanceController::class, 'required']);
        Route::post('/policies/{policy}/accept', [PolicyAcceptanceController::class, 'accept']);

        Route::post('venue-clusters/resolve-map', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'resolveMapUrl']);
        Route::post('venue-clusters/reverse-map', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'reverseMap']);
        Route::get('/amenities', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'index']); // Read-only: Owner cần xem danh sách tiện ích
        Route::get('/service-categories', [AdminServiceCategoryController::class, 'index']);
        Route::get('/bookings/check-availability', [\App\Http\Controllers\Api\Player\BookingController::class, 'checkAvailability']);
        Route::get('/bookings/eligible-vouchers', [\App\Http\Controllers\Api\Player\BookingController::class, 'eligibleVouchers']);
        Route::get('/bookings', [\App\Http\Controllers\Api\Player\BookingController::class, 'index']);
        Route::get('/bookings/recurring-groups/{groupCode}', [\App\Http\Controllers\Api\Player\BookingController::class, 'recurringGroup']);
        Route::post('/bookings/recurring/preview', [\App\Http\Controllers\Api\Player\BookingController::class, 'previewRecurring']);
        Route::post('/bookings/recurring', [\App\Http\Controllers\Api\Player\BookingController::class, 'storeRecurring']);
        Route::post('/bookings', [\App\Http\Controllers\Api\Player\BookingController::class, 'store']);
        Route::get('/bookings/{id}', [\App\Http\Controllers\Api\Player\BookingController::class, 'show']);
        Route::post('/bookings/{id}/cancel', [\App\Http\Controllers\Api\Player\BookingController::class, 'cancel']);
        Route::post('/bookings/{id}/cancel/preview', [\App\Http\Controllers\Api\Player\BookingController::class, 'cancelPreview']);
        Route::post('/bookings/{id}/payments/sepay', [SepayPaymentController::class, 'create']);
        Route::post('/bookings/{id}/payments/cancel', [SepayPaymentController::class, 'cancel']);

        Route::get('/user/wallet', [\App\Http\Controllers\Api\Player\WalletController::class, 'show']);
        Route::post('/user/wallet/withdraw', [\App\Http\Controllers\Api\Player\WalletController::class, 'requestWithdrawal']);
        Route::get('/refunds', [\App\Http\Controllers\Api\Player\RefundController::class, 'index']);
        Route::get('/refunds/{id}', [\App\Http\Controllers\Api\Player\RefundController::class, 'show']);

        // Player Matchmaking Posts
        Route::get('/matchmaking-posts/mine', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'myPosts']);
        Route::get('/matchmaking-requests', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'myRequests']);
        Route::get('/matchmaking-requests/{id}', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'myRequest']);
        Route::get('/matchmaking-posts/eligible-bookings', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'eligibleBookings']);
        Route::post('/matchmaking-posts', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'store'])->middleware('throttle:5,1');
        Route::post('/matchmaking-posts/{id}/join', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'join']);
        Route::post('/matchmaking-posts/{id}/leave', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'leave']);

        // Matchmaking Management
        Route::patch('/matchmaking-posts/{id}', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'update']);
        Route::delete('/matchmaking-posts/{id}', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'close']);
        Route::get('/matchmaking-posts/{id}/participants', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'participants']);
        Route::post('/matchmaking-posts/{id}/participants/{userId}/approve', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'approveParticipant']);
        Route::post('/matchmaking-posts/{id}/participants/{userId}/reject', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'rejectParticipant']);
        Route::post('/matchmaking-posts/{id}/participants/{userId}/remove', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'removeParticipant']);
        Route::post('/matchmaking-posts/{id}/group/dissolve', [\App\Http\Controllers\Api\Player\PlayerPostController::class, 'dissolveGroup']);

        // Player/Client Venue Posts (Community Posts)
        Route::get('/my-community-posts', [PlayerVenuePostController::class, 'myPosts']);
        Route::post('/my-community-posts/{id}/restore', [PlayerVenuePostController::class, 'restore']);
        Route::post('/my-community-posts/{id}/appeal', [PlayerVenuePostController::class, 'appeal']);
        Route::post('/venue-posts', [PlayerVenuePostController::class, 'store'])->middleware('throttle:5,1');
        Route::match(['put', 'post', 'patch'], '/venue-posts/{id}', [PlayerVenuePostController::class, 'update']);
        Route::delete('/venue-posts/{id}', [PlayerVenuePostController::class, 'destroy']);

        Route::get('/vip-membership', [\App\Http\Controllers\Api\Player\VipMembershipController::class, 'index']);
        Route::post('/vip-membership/subscribe', [\App\Http\Controllers\Api\Player\VipMembershipController::class, 'subscribe']);

        // Player Venue Posts
        Route::post('/venue-posts/{id}/comments', [PlayerVenuePostController::class, 'comment']);
        Route::post('/venue-posts/{id}/likes', [PlayerVenuePostController::class, 'toggleLike']);
        Route::post('/partner-applications', [\App\Http\Controllers\Api\Player\PartnerApplicationController::class, 'store']);
        
        // Reports
        Route::post('/reports', [PublicReportController::class, 'store']);

        // Complaints (Player)
        Route::post('/complaints', [\App\Http\Controllers\Api\Player\ComplaintController::class, 'store'])->middleware('throttle:5,10');
        Route::get('/complaints', [\App\Http\Controllers\Api\Player\ComplaintController::class, 'index']);
        Route::get('/complaints/eligible-bookings', [\App\Http\Controllers\Api\Player\ComplaintController::class, 'eligibleBookings']);
        Route::get('/complaints/{id}', [\App\Http\Controllers\Api\Player\ComplaintController::class, 'show']);
        Route::post('/complaints/{id}/reply', [\App\Http\Controllers\Api\Player\ComplaintController::class, 'reply'])->middleware('throttle:10,1');

        // Chat routes
        Route::prefix('chat')
            ->middleware([EnsureVenueStaffMenuPermission::class.':chat', 'throttle:60,1'])
            ->group(function (): void {
            Route::get('/conversations', [ChatController::class, 'getConversations']);
            Route::post('/conversations', [ChatController::class, 'startConversation']);
            Route::get('/conversations/{id}/messages', [ChatController::class, 'getMessages']);
            Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
            Route::post('/messages/{id}/react', [ChatController::class, 'reactToMessage']);
            Route::post('/messages/{id}/pin', [ChatController::class, 'togglePinMessage']);
            Route::post('/messages/{id}/recall', [ChatController::class, 'recallMessage']);
            Route::delete('/messages/{id}', [ChatController::class, 'deleteMessageForSelf']);
            Route::get('/conversations/{id}/bookings', [ChatController::class, 'getEligibleBookings']);
            Route::get('/conversations/{id}/related-bookings', [ChatController::class, 'getRelatedBookings']);
            Route::post('/conversations/{id}/support-requests', [ChatController::class, 'createBookingSupportRequest']);
            Route::patch('/support-requests/{id}', [ChatController::class, 'updateBookingSupportRequest']);
            Route::post('/conversations/{id}/bookings', [ChatController::class, 'sendBooking']);
            Route::post('/conversations/{id}/read', [ChatController::class, 'markAsRead']);
            Route::post('/conversations/{id}/add-members', [ChatController::class, 'addMembers']);
            Route::post('/conversations/{id}/remove-member', [ChatController::class, 'removeMember']);
            Route::post('/conversations/{id}/leave', [ChatController::class, 'leaveConversation']);
            Route::post('/conversations/{id}/dissolve', [ChatController::class, 'dissolveConversation']);
            Route::delete('/conversations/{id}', [ChatController::class, 'deleteConversation']);
            Route::post('/conversations/{id}/clear', [ChatController::class, 'clearMessages']);
            Route::post('/conversations/{id}/unhide', [ChatController::class, 'unhideConversation']);
            Route::get('/users/search', [ChatController::class, 'searchUsers']);
        });
    });

// Public Player Venue Posts
Route::get('/venue-posts', [PlayerVenuePostController::class, 'index']);
Route::get('/venue-posts/{slug}', [PlayerVenuePostController::class, 'show']);

// Public System News
Route::get('/system-news', [\App\Http\Controllers\Api\Public\SystemPostController::class, 'index']);
Route::get('/system-news/{slug}', [\App\Http\Controllers\Api\Public\SystemPostController::class, 'show']);

Route::get('/users/{id}/profile', [PublicUserProfileController::class, 'show']);

Route::post('/sepay/ipn', [SepayPaymentController::class, 'ipn']);
