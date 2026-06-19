<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Api\Admin\Auth\AdminForgotPasswordController;
use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\FinanceOperationController as AdminFinanceOperationController;
use App\Http\Controllers\Api\Admin\PartnerApplicationController as AdminPartnerApplicationController;
use App\Http\Controllers\Api\Admin\PartnerContractController as AdminPartnerContractController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\Auth\SetPasswordController;
use App\Http\Controllers\Api\Owner\BookingManagementController as OwnerBookingManagementController;
use App\Http\Controllers\Api\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Api\Owner\PartnerApplicationController as OwnerPartnerApplicationController;
use App\Http\Controllers\Api\Owner\PartnerContractController as OwnerPartnerContractController;
use App\Http\Controllers\Api\Owner\BookingConfigController as OwnerBookingConfigController;
use App\Http\Controllers\Api\Payment\SepayPaymentController;
use App\Http\Controllers\Api\PolicyAcceptanceController;
use App\Http\Controllers\Api\Owner\PricingController as OwnerPricingController;
use App\Http\Controllers\Api\Owner\PlatformFeeController as OwnerPlatformFeeController;
use App\Http\Controllers\Api\Owner\ScheduleLockController as OwnerScheduleLockController;
use App\Http\Controllers\Api\Owner\StaffController as OwnerStaffController;
use App\Http\Controllers\Api\Owner\VenuePolicyController as OwnerVenuePolicyController;
use App\Http\Controllers\Api\Owner\VoucherController as OwnerVoucherController;
use App\Http\Controllers\Api\Owner\FinanceController as OwnerFinanceController;
use App\Http\Controllers\Api\Owner\RefundController as OwnerRefundController;
use App\Http\Controllers\Api\PartnerApplicationDocumentDownloadController;
use App\Http\Controllers\Api\PartnerDocumentDownloadController;
use App\Http\Controllers\Api\User\PartnerApplicationController as UserPartnerApplicationController;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureOwnerRole;
use App\Http\Middleware\EnforceVenueAccessRestrictions;
use Illuminate\Support\Facades\Route;

Route::get('/banners/active/{position?}', [AdminBannerController::class, 'getActiveBanners']);

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
        Route::patch('/finance/withdrawals/{id}/status', [AdminFinanceOperationController::class, 'updateWithdrawal']);
        Route::post('/finance/withdrawals/{id}/payout-qr', [AdminFinanceOperationController::class, 'withdrawalPayoutQr']);
        Route::post('/finance/withdrawals/{id}/payout-check', [AdminFinanceOperationController::class, 'checkWithdrawalPayout']);
        Route::post('/finance/withdrawals/export', [AdminFinanceOperationController::class, 'exportWithdrawals']);

        Route::get('/partner-applications', [AdminPartnerApplicationController::class, 'index']);
        Route::get('/partner-applications/{id}', [AdminPartnerApplicationController::class, 'show']);
        Route::post('/partner-applications/{id}/approve', [AdminPartnerApplicationController::class, 'approve']);
        Route::post('/partner-applications/{id}/reject', [AdminPartnerApplicationController::class, 'reject']);
        Route::post('/partner-applications/{id}/sign-document', [AdminPartnerApplicationController::class, 'signDocument']);
        Route::get('/partner-applications/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::post('/partner-applications/{id}/terminate', [AdminPartnerApplicationController::class, 'terminate']);
        Route::post('/partner-applications/{id}/confirm-termination', [AdminPartnerApplicationController::class, 'confirmTermination']);

        Route::get('/partner-profiles', [AdminPartnerApplicationController::class, 'index']);
        Route::get('/partner-profiles/{id}', [AdminPartnerApplicationController::class, 'show']);
        Route::post('/partner-profiles/{id}/approve', [AdminPartnerApplicationController::class, 'approve']);
        Route::post('/partner-profiles/{id}/reject', [AdminPartnerApplicationController::class, 'reject']);
        Route::post('/partner-profiles/{id}/sign-document', [AdminPartnerApplicationController::class, 'signDocument']);
        Route::get('/partner-profiles/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::post('/partner-profiles/{id}/terminate', [AdminPartnerApplicationController::class, 'terminate']);
        Route::post('/partner-profiles/{id}/confirm-termination', [AdminPartnerApplicationController::class, 'confirmTermination']);

        // Partner Contracts
        Route::post('/contracts/{id}/send-email', [AdminPartnerContractController::class, 'sendEmail']);
        Route::post('/contracts/{id}/approve-signature', [AdminPartnerContractController::class, 'approveSignature']);
        Route::post('/contracts/{id}/terminate', [AdminPartnerContractController::class, 'terminate']);
        Route::post('/contracts/{id}/approve-termination', [AdminPartnerContractController::class, 'approveTermination']);

        Route::get('/banners', [AdminBannerController::class, 'index']);
        Route::post('/banners', [AdminBannerController::class, 'store']);
        Route::post('/banners/reorder', [AdminBannerController::class, 'reorder']);
        Route::patch('/banners/{id}', [AdminBannerController::class, 'update']);
        Route::delete('/banners/{id}', [AdminBannerController::class, 'destroy']);

        Route::get('/reports', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'index']);
        Route::get('/reports/{id}', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'show']);
        Route::patch('/reports/{id}/review', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'review']);
        Route::patch('/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'resolve']);
        Route::post('/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'resolve']);
        Route::get('/violation-records/{targetType}/{targetId}', [\App\Http\Controllers\Api\Admin\AdminReportController::class, 'violationRecord']);
        Route::apiResource('violation-types', \App\Http\Controllers\Api\Admin\ViolationTypeController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/complaints', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'index']);
        Route::get('/complaints/{id}', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'show']);
        Route::patch('/complaints/{id}/assign', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'assign']);
        Route::patch('/complaints/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminComplaintController::class, 'resolve']);

        Route::apiResource('court-types', \App\Http\Controllers\Api\Admin\CourtTypeController::class);

        Route::patch('/amenities/{id}/review', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'review']);
        Route::apiResource('amenities', \App\Http\Controllers\Api\Admin\AmenityController::class);

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
        // Venue Location Change Requests (Admin duyệt/từ chối thay đổi vị trí)
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/approve', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'approveLocationChange']);
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/reject', [\App\Http\Controllers\Api\Admin\VenueClusterController::class, 'rejectLocationChange']);

        // Content Moderation
        Route::get('/moderation/queue', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'queue']);
        Route::post('/moderation/posts/{type}/{id}/approve', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'approvePost']);
        Route::post('/moderation/posts/{type}/{id}/reject', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'rejectPost']);
        Route::post('/moderation/posts/{type}/{id}/hide', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'hidePost']);
        Route::delete('/moderation/posts/{type}/{id}', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'deletePost']);
        Route::post('/moderation/reports/{id}/resolve', [\App\Http\Controllers\Api\Admin\AdminContentModerationController::class, 'resolveReport']);

        // User Lock Management
        Route::post('/user-lock-policy', [\App\Http\Controllers\Api\Admin\UserController::class, 'saveAutoLockConfig']);
        Route::post('/users/{user}/lock', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'lock']);
        Route::post('/users/{user}/unlock', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'unlock']);
        Route::get('/users/{user}/lock-logs', [\App\Http\Controllers\Api\Admin\UserLockController::class, 'lockLogs']);

        // Admin Comment & Post Detail (phục vụ chi tiết user)
        Route::get('/comments/{comment}', [\App\Http\Controllers\Api\Admin\AdminCommentController::class, 'show']);
        Route::post('/comments/{comment}/action', [\App\Http\Controllers\Api\Admin\AdminCommentController::class, 'processAction']);
        Route::get('/posts/{post}', [\App\Http\Controllers\Api\Admin\AdminPostController::class, 'show']);
        Route::post('/posts/{post}/action', [\App\Http\Controllers\Api\Admin\AdminPostController::class, 'processAction']);
    });

Route::middleware(['auth:sanctum', EnsureOwnerRole::class, EnforceVenueAccessRestrictions::class])
    ->prefix('owner')
    ->group(function (): void {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index']);
        Route::get('/booking-configs', [OwnerBookingConfigController::class, 'index']);
        Route::put('/booking-configs/{venueClusterId}', [OwnerBookingConfigController::class, 'update']);

        // Wallet & Withdrawals
        Route::get('/wallet', [\App\Http\Controllers\Api\Owner\WalletController::class, 'getWallet']);
        Route::post('/wallet/withdraw', [\App\Http\Controllers\Api\Owner\WalletController::class, 'withdraw']);
        Route::get('/wallet/withdrawals', [\App\Http\Controllers\Api\Owner\WalletController::class, 'getWithdrawals']);
        // Partner Profile
        Route::get('/partner-applications', [OwnerPartnerApplicationController::class, 'myApplications']);
        Route::get('/partner-application', [OwnerPartnerApplicationController::class, 'myApplication']);
        Route::get('/my-partner-profile', [OwnerPartnerApplicationController::class, 'myApplication']);
        Route::get('/my-partner-profile/documents', [OwnerPartnerApplicationController::class, 'documents']);
        Route::get('/my-partner-profile/documents/{id}/download', PartnerDocumentDownloadController::class);
        Route::post('/my-partner-profile/request-termination', [OwnerPartnerApplicationController::class, 'requestTermination']);
        Route::post('/partner-applications/new-cluster', [OwnerPartnerApplicationController::class, 'storeNewCluster']);
        Route::post('/contracts/{id}/sign', [OwnerPartnerContractController::class, 'sign']);
        Route::post('/contracts/{id}/request-termination', [OwnerPartnerContractController::class, 'requestTermination']);

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
        Route::get('/vouchers', [OwnerVoucherController::class, 'index']);
        Route::get('/vouchers/{id}', [OwnerVoucherController::class, 'show']);
        Route::post('/vouchers', [OwnerVoucherController::class, 'store']);
        Route::put('/vouchers/{id}', [OwnerVoucherController::class, 'update']);
        Route::patch('/vouchers/{id}/deactivate', [OwnerVoucherController::class, 'deactivate']);
        // Venue Court Approval Requests (Owner gửi yêu cầu quy mô)
        Route::get('/venue-clusters/{clusterId}/approval-requests', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/approval-requests', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'store']);
        Route::patch('/venue-clusters/{clusterId}/approval-requests/{requestId}/cancel', [\App\Http\Controllers\Api\Owner\VenueCourtApprovalController::class, 'cancel']);
        // Venue Location Change Requests (Owner gửi yêu cầu thay đổi vị trí)
        Route::get('/venue-clusters/{clusterId}/location-change-requests', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'index']);
        Route::post('/venue-clusters/{clusterId}/location-change-requests', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'store']);
        Route::patch('/venue-clusters/{clusterId}/location-change-requests/{requestId}/cancel', [\App\Http\Controllers\Api\Owner\VenueLocationChangeController::class, 'cancel']);

        Route::get('/venue-policies', [OwnerVenuePolicyController::class, 'index']);
        Route::post('/venue-policies/rules', [OwnerVenuePolicyController::class, 'storeRule']);
        Route::delete('/venue-policies/rules/{id}', [OwnerVenuePolicyController::class, 'destroyRule']);
        Route::post('/venue-policies/notices', [OwnerVenuePolicyController::class, 'storeNotice']);
        Route::put('/venue-policies/notices/{id}', [OwnerVenuePolicyController::class, 'updateNotice']);
        Route::get('/pricing', [OwnerPricingController::class, 'index']);
        Route::patch('/booking-configs/{venueClusterId}/duration', [OwnerPricingController::class, 'updateDuration']);
        Route::post('/price-slots', [OwnerPricingController::class, 'storePriceSlot']);
        Route::patch('/price-slots/{id}', [OwnerPricingController::class, 'updatePriceSlot']);
        Route::delete('/price-slots/{id}', [OwnerPricingController::class, 'destroyPriceSlot']);
        Route::post('/holiday-prices', [OwnerPricingController::class, 'storeHolidayPrice']);
        Route::patch('/holiday-prices/{id}', [OwnerPricingController::class, 'updateHolidayPrice']);
        Route::delete('/holiday-prices/{id}', [OwnerPricingController::class, 'destroyHolidayPrice']);
        Route::get('/platform-fees', [OwnerPlatformFeeController::class, 'index']);
        Route::get('/platform-fees/{id}', [OwnerPlatformFeeController::class, 'show']);
        Route::post('/platform-fees/{id}/payment-proof', [OwnerPlatformFeeController::class, 'submitProof']);
        Route::get('/schedule-locks', [OwnerScheduleLockController::class, 'index']);
        Route::post('/schedule-locks', [OwnerScheduleLockController::class, 'store']);
        Route::delete('/schedule-locks/{id}', [OwnerScheduleLockController::class, 'destroy']);
        Route::post('/amenities/request', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'requestAmenity']);

        // Finance / Wallet
        Route::get('/finance/wallets', [OwnerFinanceController::class, 'wallets']);
        Route::get('/finance/ledgers', [OwnerFinanceController::class, 'ledgers']);
        Route::get('/finance/withdrawals', [OwnerFinanceController::class, 'withdrawals']);
        Route::post('/finance/withdrawals', [OwnerFinanceController::class, 'storeWithdrawal']);
        Route::get('/refunds', [OwnerRefundController::class, 'index']);
        Route::patch('/refunds/{id}/decision', [OwnerRefundController::class, 'decide']);
        Route::get('/bookings', [OwnerBookingManagementController::class, 'index']);
        Route::get('/bookings/schedule', [OwnerBookingManagementController::class, 'schedule']);
        Route::post('/bookings/counter', [OwnerBookingManagementController::class, 'storeCounter']);
        Route::post('/bookings/recurring', [OwnerBookingManagementController::class, 'storeRecurring']);
        Route::get('/bookings/{id}', [OwnerBookingManagementController::class, 'show']);
        Route::post('/bookings/{id}/payments/collect', [OwnerBookingManagementController::class, 'collectPayment']);
        Route::patch('/bookings/{id}/status', [OwnerBookingManagementController::class, 'updateStatus']);
        Route::patch('/bookings/{id}/court', [OwnerBookingManagementController::class, 'changeCourt']);
    });

Route::middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/user/partner-application', [UserPartnerApplicationController::class, 'show']);
        Route::get('/user/partner-application/banks', [UserPartnerApplicationController::class, 'banks']);
        Route::get('/user/partner-application/provinces', [UserPartnerApplicationController::class, 'provinces']);
        Route::get('/user/partner-application/provinces/{provinceCode}/wards', [UserPartnerApplicationController::class, 'wards']);
        Route::post('/user/partner-application/verify-bank-account', [UserPartnerApplicationController::class, 'verifyBankAccount']);
        Route::post('/user/partner-application/resolve-map', [UserPartnerApplicationController::class, 'resolveMap']);
        Route::post('/user/partner-application/preview', [UserPartnerApplicationController::class, 'preview']);
        Route::post('/user/partner-application', [UserPartnerApplicationController::class, 'store']);
        Route::post('/user/partner-application/{id}/cancel', [UserPartnerApplicationController::class, 'cancel']);
        Route::get('/user/partner-application/documents', [UserPartnerApplicationController::class, 'documents']);
        Route::get('/user/partner-application/documents/{documentId}/download', PartnerApplicationDocumentDownloadController::class);
        Route::get('/user/partner-application/pending-contract', [UserPartnerApplicationController::class, 'pendingContract']);
        Route::post('/user/partner-application/sign-contract', [UserPartnerApplicationController::class, 'signContract']);
        Route::get('/files/documents/{id}/download', PartnerDocumentDownloadController::class);

        Route::get('/policies/required', [PolicyAcceptanceController::class, 'required']);
        Route::post('/policies/{policy}/accept', [PolicyAcceptanceController::class, 'accept']);

        Route::post('venue-clusters/resolve-map', [\App\Http\Controllers\Api\Owner\VenueClusterController::class, 'resolveMapUrl']);
        Route::get('/court-types', [\App\Http\Controllers\Api\Admin\CourtTypeController::class, 'index']); // Read-only: Owner cần xem danh sách loại sân
        Route::get('/amenities', [\App\Http\Controllers\Api\Admin\AmenityController::class, 'index']); // Read-only: Owner cần xem danh sách tiện ích
        Route::get('/bookings/init', [\App\Http\Controllers\Api\Player\BookingController::class, 'initData']);
        Route::get('/bookings/schedule', [\App\Http\Controllers\Api\Player\BookingController::class, 'schedule']);
        Route::get('/bookings/check-availability', [\App\Http\Controllers\Api\Player\BookingController::class, 'checkAvailability']);
        Route::post('/bookings', [\App\Http\Controllers\Api\Player\BookingController::class, 'store']);
        Route::get('/bookings/{id}', [\App\Http\Controllers\Api\Player\BookingController::class, 'show']);
        Route::post('/bookings/{id}/cancel', [\App\Http\Controllers\Api\Player\BookingController::class, 'cancel']);
        Route::post('/bookings/{id}/payments/sepay', [SepayPaymentController::class, 'create']);
        Route::post('/bookings/{id}/payments/cancel', [SepayPaymentController::class, 'cancel']);
    });

Route::post('/sepay/ipn', [SepayPaymentController::class, 'ipn']);
