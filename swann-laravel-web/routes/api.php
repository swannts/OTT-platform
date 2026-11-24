<?php

use App\Http\Controllers\Auth\API\AuthController;
use App\Http\Controllers\Backend\API\DashboardController;
use App\Http\Controllers\Backend\API\NotificationsController;
use App\Http\Controllers\Backend\API\InvoiceController;
use App\Http\Controllers\Backend\API\ImageBlurHashController;

use App\Http\Controllers\Backend\API\SettingController as APISettingController;
use Modules\Frontend\Http\Controllers\PerviewPaymentController;
use Modules\Frontend\Http\Controllers\QueryOptimizeController;

use Modules\User\Http\Controllers\API\UserController;
use Modules\Entertainment\Http\Controllers\API\EntertainmentsController;
use Modules\LiveTV\Http\Controllers\API\LiveTVsController;
use App\Http\Controllers\TvAuthController;
use App\Http\Controllers\Backend\SettingController;
use App\Http\Controllers\Auth\WebQrLoginController;
use Modules\CastCrew\Http\Controllers\API\CastCrewController;
use Modules\Frontend\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\API\ShortDramaConfigController;
use App\Http\Controllers\API\WebPaymentApiController;
use App\Http\Controllers\API\PageApiController;
use App\Http\Controllers\API\BlogApiController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Short Drama feature config (no auth) — core route; works without Modules/ShortDrama
Route::get('/short-drama-config', [ShortDramaConfigController::class, 'index']);

// Public Blog Articles & Categories
Route::get('v3/blogs', [BlogApiController::class, 'index']);
Route::get('v3/blogs/{slug}', [BlogApiController::class, 'show']);
Route::get('v3/blog-categories', [BlogApiController::class, 'categories']);
Route::get('v3/marketing/campaigns/{slug}', [\App\Http\Controllers\API\AdminMarketingController::class, 'publicLanding']);
Route::get('v3/marketing/featured-collections', [\App\Http\Controllers\API\AdminMarketingController::class, 'publicFeaturedCollections']);
Route::get('marketing/click/{token}', [\App\Http\Controllers\API\MarketingConsentController::class, 'trackClick'])->where('token', '[A-Fa-f0-9]{64}')->name('marketing.track-click');
Route::get('marketing/open/{token}', [\App\Http\Controllers\API\MarketingConsentController::class, 'trackOpen'])->where('token', '[A-Fa-f0-9]{64}')->name('marketing.track-open');
Route::get('marketing/unsubscribe/{token}', [\App\Http\Controllers\API\MarketingConsentController::class, 'unsubscribePrompt'])->where('token', '[A-Fa-f0-9]{64}')->name('marketing.unsubscribe');
Route::post('marketing/unsubscribe/{token}', [\App\Http\Controllers\API\MarketingConsentController::class, 'unsubscribe'])->where('token', '[A-Fa-f0-9]{64}');

// Public Global SEO Settings
Route::get('v3/seo-settings', [\Modules\SEO\Http\Controllers\SEOController::class, 'getSeoSettingsApi']);

// Provider-signed callbacks are verified by their dedicated controller handlers.
Route::post('webhooks/stripe', [WebPaymentApiController::class, 'stripeWebhook'])->middleware('throttle:300,1');
Route::post('webhooks/razorpay', [WebPaymentApiController::class, 'razorpayWebhook'])->middleware('throttle:300,1');
Route::post('webhooks/paystack', [WebPaymentApiController::class, 'paystackWebhook'])->middleware('throttle:300,1');
Route::post('webhooks/revenuecat', [WebPaymentApiController::class, 'revenueCatWebhook'])->middleware('throttle:300,1');


Route::get('/optimize', [QueryOptimizeController::class, 'optimize'])->name('optimize');

Route::middleware(['auth:sanctum', 'checkApiDevice'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me/marketing-consents', [\App\Http\Controllers\API\MarketingConsentController::class, 'show']);
    Route::put('me/marketing-consents', [\App\Http\Controllers\API\MarketingConsentController::class, 'update']);
});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('social-login', 'socialLogin');
    Route::post('forgot-password', 'forgotPassword');
    Route::get('csrf-token', 'getCsrfToken');
});

Route::post('check-mobile-exists', [OTPController::class, 'checkMobileExists'])->name('api.check.mobile.exists');

Route::prefix('admin')->group(function () {
    Route::post('login', [\App\Http\Controllers\API\AdminApiController::class, 'login'])->middleware('throttle:5,1');
    Route::post('invitations/accept', [\App\Http\Controllers\API\AdminInvitationController::class, 'accept'])->middleware('throttle:5,1');
    Route::middleware(['auth:sanctum', 'explicit.permission:admin.access'])->group(function () {
        Route::get('me', [\App\Http\Controllers\API\AdminApiController::class, 'me']);
        Route::post('logout', [\App\Http\Controllers\API\AdminApiController::class, 'logout']);
        Route::get('dashboard', [\App\Http\Controllers\API\AdminApiController::class, 'dashboard'])->middleware('explicit.permission:admin.dashboard.view');

        Route::get('roles', [\App\Http\Controllers\API\AdminRoleManagementController::class, 'index']);
        Route::post('roles', [\App\Http\Controllers\API\AdminRoleManagementController::class, 'store']);
        Route::get('roles/{role}', [\App\Http\Controllers\API\AdminRoleManagementController::class, 'show']);
        Route::put('roles/{role}', [\App\Http\Controllers\API\AdminRoleManagementController::class, 'update']);
        Route::delete('roles/{role}', [\App\Http\Controllers\API\AdminRoleManagementController::class, 'destroy']);
        Route::get('permissions', [\App\Http\Controllers\API\AdminPermissionController::class, 'index'])->middleware('explicit.permission:admin.permissions.view');

        Route::get('staff', [\App\Http\Controllers\API\AdminStaffController::class, 'index'])->middleware('explicit.permission:admin.staff.view');
        Route::post('staff/invitations', [\App\Http\Controllers\API\AdminInvitationController::class, 'store'])->middleware('explicit.permission:admin.staff.invite');
        Route::put('staff/{user}/roles', [\App\Http\Controllers\API\AdminStaffController::class, 'updateRoles'])->middleware('explicit.permission:admin.staff.roles.update');
        Route::patch('staff/{user}/status', [\App\Http\Controllers\API\AdminStaffController::class, 'updateStatus'])->middleware('explicit.permission:admin.staff.status.update');
        Route::post('staff/{user}/sessions/revoke', [\App\Http\Controllers\API\AdminStaffController::class, 'revokeSessions'])->middleware('explicit.permission:admin.staff.sessions.revoke');
        Route::get('staff/{user}/permissions', [\App\Http\Controllers\API\AdminStaffController::class, 'effectivePermissions'])->middleware('explicit.permission:admin.staff.view');

        Route::get('audit-logs', [\App\Http\Controllers\API\AdminAuditController::class, 'index'])->middleware('explicit.permission:audit.view');
        Route::get('audit-logs/export', [\App\Http\Controllers\API\AdminAuditController::class, 'export'])->middleware('explicit.permission:audit.export');

        Route::get('content/review-queue', [\App\Http\Controllers\API\AdminContentWorkflowController::class, 'queue']);
        Route::post('content/{content}/submit', [\App\Http\Controllers\API\AdminContentWorkflowController::class, 'submit'])->middleware('explicit.permission:content.submit');
        Route::post('content/reviews/{submission}/approve', [\App\Http\Controllers\API\AdminContentWorkflowController::class, 'approve'])->middleware('explicit.permission:content.approve');
        Route::post('content/reviews/{submission}/reject', [\App\Http\Controllers\API\AdminContentWorkflowController::class, 'reject'])->middleware('explicit.permission:content.reject');
        Route::post('content/reviews/{submission}/publish', [\App\Http\Controllers\API\AdminContentWorkflowController::class, 'publish'])->middleware('explicit.permission:content.publish');
        Route::get('content/{contentType}/{contentId}/license-window', [\App\Http\Controllers\API\AdminContentLicensingController::class, 'show']);
        Route::put('content/{contentType}/{contentId}/license-window', [\App\Http\Controllers\API\AdminContentLicensingController::class, 'update'])->middleware('explicit.permission:content.schedule');

        Route::get('media/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'media'])->middleware('explicit.permission:media.view');
        Route::get('media/assets', [\App\Http\Controllers\API\AdminMediaController::class, 'index'])->middleware('explicit.permission:media.view');
        Route::get('media/assets/{asset}', [\App\Http\Controllers\API\AdminMediaController::class, 'show'])->middleware('explicit.permission:media.view');
        Route::get('media/assets/{asset}/quality', [\App\Http\Controllers\API\AdminMediaController::class, 'quality'])->middleware('explicit.permission:media.view');
        Route::put('media/assets/{asset}/content', [\App\Http\Controllers\API\AdminMediaController::class, 'attachToContent']);
        Route::post('media/uploads', [\App\Http\Controllers\API\AdminMediaController::class, 'beginUpload'])->middleware(['explicit.permission:media.upload', 'throttle:20,1']);
        Route::put('media/uploads/{session}/chunks/{index}', [\App\Http\Controllers\API\AdminMediaController::class, 'uploadChunk'])->whereNumber('index')->middleware(['explicit.permission:media.upload', 'throttle:120,1']);
        Route::post('media/uploads/{session}/complete', [\App\Http\Controllers\API\AdminMediaController::class, 'completeUpload'])->middleware(['explicit.permission:media.upload', 'throttle:5,1']);
        Route::post('media/assets/{asset}/retry', [\App\Http\Controllers\API\AdminMediaController::class, 'retry'])->middleware(['explicit.permission:media.transcode.retry', 'throttle:5,1']);
        Route::delete('media/assets/{asset}', [\App\Http\Controllers\API\AdminMediaController::class, 'destroy'])->middleware('explicit.permission:media.delete');
        Route::post('media/assets/{asset}/tracks', [\App\Http\Controllers\API\AdminMediaController::class, 'addTrack'])->middleware('throttle:20,1');
        Route::delete('media/assets/{asset}/tracks/{track}', [\App\Http\Controllers\API\AdminMediaController::class, 'deleteTrack'])->whereNumber('track');
        Route::get('media/drm', [\App\Http\Controllers\API\AdminMediaController::class, 'drmConfiguration'])->middleware('explicit.permission:media.drm.configure');
        Route::get('subscriptions/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'subscriptions'])->middleware('explicit.permission:subscriptions.view');
        Route::get('subscriptions/plans', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'index'])->middleware('explicit.permission:subscriptions.view');
        Route::post('subscriptions/plans', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'store'])->middleware('explicit.permission:subscriptions.plans.create');
        Route::put('subscriptions/plans/{plan}', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'update'])->middleware('explicit.permission:subscriptions.plans.update');
        Route::post('subscriptions/plans/{plan}/archive', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'archive'])->middleware('explicit.permission:subscriptions.plans.archive');
        Route::get('subscriptions/subscribers', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'subscribers'])->middleware('explicit.permission:subscriptions.subscribers.view');
        Route::get('subscriptions/performance', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'performance'])->middleware('explicit.permission:subscriptions.view');
        Route::post('subscriptions/entitlement-simulation', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'simulate'])->middleware('explicit.permission:subscriptions.view');
        Route::get('subscriptions/coupons', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'coupons'])->middleware('explicit.permission:subscriptions.coupons.manage');
        Route::post('subscriptions/coupons', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'storeCoupon'])->middleware('explicit.permission:subscriptions.coupons.manage');
        Route::put('subscriptions/coupons/{coupon}', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'updateCoupon'])->middleware('explicit.permission:subscriptions.coupons.manage');
        Route::post('subscriptions/coupons/{coupon}/archive', [\App\Http\Controllers\API\AdminSubscriptionController::class, 'archiveCoupon'])->middleware('explicit.permission:subscriptions.coupons.manage');
        Route::get('finance/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'finance'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/dashboard', [\App\Http\Controllers\API\AdminFinanceController::class, 'overview'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/payments', [\App\Http\Controllers\API\AdminFinanceController::class, 'payments'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/payments/export', [\App\Http\Controllers\API\AdminFinanceController::class, 'export'])->middleware('explicit.permission:finance.reports.export');
        Route::get('finance/payments/{payment}/invoice', [\App\Http\Controllers\API\AdminFinanceController::class, 'invoice'])->middleware('explicit.permission:finance.invoices.view');
        Route::post('finance/payments/{payment}/reconcile', [\App\Http\Controllers\API\AdminFinanceController::class, 'reconcilePayment'])->middleware('explicit.permission:finance.reconciliation.view');
        Route::get('finance/payments/{payment}', [\App\Http\Controllers\API\AdminFinanceController::class, 'paymentDetail'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/refunds', [\App\Http\Controllers\API\AdminFinanceController::class, 'refunds'])->middleware('explicit.permission:finance.payments.view');
        Route::post('finance/refunds', [\App\Http\Controllers\API\AdminFinanceController::class, 'requestRefund'])->middleware('explicit.permission:finance.refunds.request');
        Route::post('finance/refunds/{refund}/review', [\App\Http\Controllers\API\AdminFinanceController::class, 'reviewRefund'])->middleware('explicit.permission:finance.refunds.approve');
        Route::post('finance/refunds/{refund}/execute', [\App\Http\Controllers\API\AdminFinanceController::class, 'executeRefund'])->middleware('explicit.permission:finance.refunds.approve');
        Route::get('finance/disputes', [\App\Http\Controllers\API\AdminFinanceController::class, 'disputes'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/failed-events', [\App\Http\Controllers\API\AdminFinanceController::class, 'failedProviderEvents'])->middleware('explicit.permission:finance.payments.view');
        Route::get('finance/reconciliation', [\App\Http\Controllers\API\AdminFinanceController::class, 'reconciliation'])->middleware('explicit.permission:finance.reconciliation.view');
        Route::get('finance/reconciliation/history', [\App\Http\Controllers\API\AdminFinanceController::class, 'reconciliationHistory'])->middleware('explicit.permission:finance.reconciliation.view');
        Route::get('finance/reports', [\App\Http\Controllers\API\AdminFinanceController::class, 'report'])->middleware('explicit.permission:finance.reconciliation.view');
        Route::get('marketing/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'marketing'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('marketing/dashboard', [\App\Http\Controllers\API\AdminMarketingController::class, 'overview'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('marketing/campaigns', [\App\Http\Controllers\API\AdminMarketingController::class, 'campaigns'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::post('marketing/campaigns', [\App\Http\Controllers\API\AdminMarketingController::class, 'createCampaign'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::put('marketing/campaigns/{campaign}', [\App\Http\Controllers\API\AdminMarketingController::class, 'updateCampaign'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::post('marketing/campaigns/{campaign}/submit', [\App\Http\Controllers\API\AdminMarketingController::class, 'submitForApproval'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::post('marketing/campaigns/{campaign}/approve', [\App\Http\Controllers\API\AdminMarketingController::class, 'approveCampaign'])->middleware('explicit.permission:marketing.campaigns.approve');
        Route::post('marketing/campaigns/{campaign}/reject', [\App\Http\Controllers\API\AdminMarketingController::class, 'rejectCampaign'])->middleware('explicit.permission:marketing.campaigns.approve');
        Route::post('marketing/campaigns/{campaign}/schedule', [\App\Http\Controllers\API\AdminMarketingController::class, 'scheduleCampaign'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::post('marketing/campaigns/{campaign}/pause', [\App\Http\Controllers\API\AdminMarketingController::class, 'pauseCampaign'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('marketing/campaigns/{campaign}/analytics', [\App\Http\Controllers\API\AdminMarketingController::class, 'analytics'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('marketing/segments', [\App\Http\Controllers\API\AdminMarketingController::class, 'segments'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('marketing/banners', [\App\Http\Controllers\API\AdminMarketingController::class, 'banners'])->middleware('explicit.permission:marketing.banners.manage');
        Route::post('marketing/banners', [\App\Http\Controllers\API\AdminMarketingController::class, 'saveBanner'])->middleware('explicit.permission:marketing.banners.manage');
        Route::put('marketing/banners/{banner}', [\App\Http\Controllers\API\AdminMarketingController::class, 'saveBanner'])->middleware('explicit.permission:marketing.banners.manage');
        Route::get('analytics/overview', [\App\Http\Controllers\API\AdminAnalyticsController::class, 'dashboard']);
        Route::get('analytics/export', [\App\Http\Controllers\API\AdminAnalyticsController::class, 'exportCsv'])->middleware('explicit.permission:analytics.export');
        Route::get('analytics/reports', [\App\Http\Controllers\API\AdminAnalyticsController::class, 'reports'])->middleware('explicit.permission:analytics.export');
        Route::post('analytics/reports', [\App\Http\Controllers\API\AdminAnalyticsController::class, 'scheduleReport'])->middleware('explicit.permission:analytics.export');
        Route::get('analytics/reports/{report}', [\App\Http\Controllers\API\AdminAnalyticsController::class, 'report'])->whereNumber('report')->middleware('explicit.permission:analytics.export');
        Route::get('operations/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'operations'])->middleware('explicit.permission:operations.health.view');
        Route::prefix('operations')->group(function (): void {
            Route::get('health', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'health'])->middleware('explicit.permission:operations.health.view');
            Route::get('playback', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'playback'])->middleware('explicit.permission:analytics.playback.view');
            Route::get('jobs', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'jobs'])->middleware('explicit.permission:operations.jobs.view');
            Route::post('jobs/{job}/retry', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'retryJob'])->middleware('explicit.permission:operations.jobs.retry');
            Route::get('incidents', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'incidents'])->middleware('explicit.permission:operations.incidents.manage');
            Route::post('incidents', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'createIncident'])->middleware('explicit.permission:operations.incidents.manage');
            Route::patch('incidents/{incident}', [\App\Http\Controllers\API\AdminTechnicalOperationsController::class, 'updateIncident'])->whereNumber('incident')->middleware('explicit.permission:operations.incidents.manage');
        });

        Route::prefix('support')->group(function (): void {
            Route::get('overview', [\App\Http\Controllers\API\AdminSupportController::class, 'overview'])->middleware('explicit.permission:support.tickets.manage');
            Route::get('customers', [\App\Http\Controllers\API\AdminSupportController::class, 'customers'])->middleware('explicit.permission:support.accounts.view');
            Route::get('customers/{customer}', [\App\Http\Controllers\API\AdminSupportController::class, 'customer'])->whereNumber('customer')->middleware('explicit.permission:support.accounts.view');
            Route::get('customers/{customer}/payments', [\App\Http\Controllers\API\AdminSupportController::class, 'payments'])->whereNumber('customer')->middleware('explicit.permission:support.payments.view');
            Route::get('customers/{customer}/devices', [\App\Http\Controllers\API\AdminSupportController::class, 'devices'])->whereNumber('customer')->middleware('explicit.permission:support.devices.view');
            Route::get('customers/{customer}/playback', [\App\Http\Controllers\API\AdminSupportController::class, 'playback'])->whereNumber('customer')->middleware('explicit.permission:support.playback.view');
            Route::post('customers/{customer}/playback-sessions/{session}/revoke', [\App\Http\Controllers\API\AdminSupportController::class, 'revokePlaybackSession'])->whereNumber(['customer', 'session'])->middleware('explicit.permission:support.sessions.revoke');
            Route::post('customers/{customer}/devices/{device}/revoke', [\App\Http\Controllers\API\AdminSupportController::class, 'revokeDeviceSession'])->whereNumber(['customer', 'device'])->middleware('explicit.permission:support.sessions.revoke');
            Route::get('tickets', [\App\Http\Controllers\API\AdminSupportController::class, 'tickets'])->middleware('explicit.permission:support.tickets.manage');
            Route::get('assignees', [\App\Http\Controllers\API\AdminSupportController::class, 'assignees'])->middleware('explicit.permission:support.tickets.manage');
            Route::post('tickets', [\App\Http\Controllers\API\AdminSupportController::class, 'createTicket'])->middleware('explicit.permission:support.tickets.manage');
            Route::get('tickets/{ticket}', [\App\Http\Controllers\API\AdminSupportController::class, 'showTicket'])->whereNumber('ticket')->middleware('explicit.permission:support.tickets.manage');
            Route::patch('tickets/{ticket}', [\App\Http\Controllers\API\AdminSupportController::class, 'updateTicket'])->whereNumber('ticket')->middleware('explicit.permission:support.tickets.manage');
            Route::post('tickets/{ticket}/notes', [\App\Http\Controllers\API\AdminSupportController::class, 'addNote'])->whereNumber('ticket')->middleware('explicit.permission:support.tickets.manage');
            Route::post('tickets/{ticket}/escalate', [\App\Http\Controllers\API\AdminSupportController::class, 'escalate'])->whereNumber('ticket')->middleware('explicit.permission:support.tickets.manage');
        });
    });
});

Route::post('/store-access-token', [SettingController::class, 'storeToken']);
Route::post('/token-revoke', [SettingController::class, 'revokeToken']);
Route::get('get-tranding-data', [DashboardController::class, 'getTrandingData']);
Route::get('v3/image-blurhash', [ImageBlurHashController::class, 'show'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:300,1');

Route::get('v2/dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV2']);
Route::get('v2/dashboard-detail', [DashboardController::class, 'DashboardDetailV2']);
Route::get('v2/episode-details', [EntertainmentsController::class, 'episodeDetailsV2']);
Route::get('v2/livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV2']);
Route::get('v2/tvshow-details', [EntertainmentsController::class, 'tvshowDetailsV2']);
Route::get('v2/movie-details', [EntertainmentsController::class, 'movieDetailsV2']);

Route::get('v2/pay-per-view-list', [DashboardController::class, 'getPayPerViewUnlockedContent']);

// Episode / TV Show helpers
Route::get('tvshows/{tvshow}/plan-level', function ($tvshowId) {
    $tvshow = \Modules\Entertainment\Models\Entertainment::with('plan')->find($tvshowId);
    if (!$tvshow || !$tvshow->plan) {
        return response()->json(['level' => null]);
    }
    return response()->json(['level' => (int) $tvshow->plan->level]);
});

Route::middleware(['auth:sanctum', 'checkApiDevice', 'throttle:api'])->group(function () {
    Route::get('logout', [AuthController::class, 'logout']);
    Route::get('user-detail', [AuthController::class, 'userDetails']);
    Route::post('/web-qr-scan', [WebQrLoginController::class, 'scan'])->name('api.web-qr.scan');

    Route::apiResource('setting', SettingController::class);
    Route::apiResource('notification', NotificationsController::class);

    Route::get('notification-list', [NotificationsController::class, 'notificationList']);
    Route::get('notification-count', [NotificationsController::class, 'notificationCount']);


    Route::get('gallery-list', [DashboardController::class, 'globalGallery']);
    Route::get('search-list', [DashboardController::class, 'searchList']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);

    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('delete-account', [AuthController::class, 'deleteAccount']);

    Route::get('unlocked-content', [PerviewPaymentController::class, 'allUnlockVideos']);

    Route::get('download-invoice/{id}', [InvoiceController::class, 'download']);
    Route::get('pay-per-view-invoice/{id}', [InvoiceController::class, 'downloadPayPerViewInvoice']);



    ### v2 api`s

    Route::get('v2/profile-details', [UserController::class, 'profileDetailsV2']);

    Route::post('/change-pin', [AuthController::class, 'changePin'])->name('change-pin');

    Route::get('/send-otp', [AuthController::class, 'sendOtp'])->name('send-otp')->middleware('throttle:1,1'); // 1 request per minute
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp')->middleware('throttle:5,1'); // 5 attempts per minute
    
    Route::post('/verify-pin', [AuthController::class, 'verifyPin'])->name('verify-pin');

    Route::post('/update-parental-lock', [AuthController::class, 'changeParentalLock'])->name('update-parental-lock');
    Route::post('/tv/confrim-session', [TvAuthController::class, 'confirmSession'])->name('confirmSession');

});

Route::prefix('v3')->middleware(['throttle:api'])->group(function () {
    Route::get('/payment-methods', [APISettingController::class, 'getPaymentMethods'])->name('payment.methods');
    Route::get('app-configuration', [APISettingController::class, 'appConfiguratonV3']);
    Route::get('content-details', [EntertainmentsController::class, 'contentDetailsV3']);
    Route::get('dashboard-detail', [DashboardController::class, 'DashboardDetailV3']);
    Route::get('dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV3']);
    Route::get('livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV3']);
    Route::get('pay-per-view-list', [DashboardController::class, 'getPayPerViewUnlockedContentV3']);
    Route::get('banner-data', [DashboardController::class, 'getEntertainmentDataV3']);
    Route::get('cast-details', [CastCrewController::class, 'castCrewDetailsV3'])->name('api.cast_crew_details_v3');

    // Web & Client Subscription / Pricing APIs
    Route::get('subscription/plans', [WebPaymentApiController::class, 'getPlans']);
    Route::post('subscription/calculate-price', [WebPaymentApiController::class, 'calculateSubscriptionPrice']);
    Route::post('coupon/validate', [WebPaymentApiController::class, 'validateCoupon']);

    // Web & Client Pay-Per-View Pricing
    Route::post('pay-per-view/calculate-price', [WebPaymentApiController::class, 'calculatePayPerViewPrice']);

    // Pages & Policies (Terms, Privacy, About)
    Route::get('pages/{slug}', [PageApiController::class, 'getPageDetails']);
});
Route::prefix('v3')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('profile-details', [UserController::class, 'profileDetailsV3'])->name('api.v3.profile-details');
    Route::get('rented-content-list', [PerviewPaymentController::class, 'allUnlockVideosV3']);
    Route::post('delete-notification', [NotificationsController::class, 'deleteNotification']);

    // Authenticated User Subscription Management & Checkout
    Route::get('user/subscription', [WebPaymentApiController::class, 'getUserSubscription']);
    Route::get('user/billing-history', [WebPaymentApiController::class, 'getBillingHistory']);
    Route::post('user/subscription/sync-in-app-purchase', [WebPaymentApiController::class, 'syncInAppPurchaseSubscription']);
    Route::post('subscription/cancel', [WebPaymentApiController::class, 'cancelSubscription']);
    Route::post('subscription/process-payment', [WebPaymentApiController::class, 'processSubscriptionPayment']);
    Route::post('subscription/verify-payment', [WebPaymentApiController::class, 'verifySubscriptionPayment']);
    Route::post('mobile/payment-intent', [WebPaymentApiController::class, 'createMobilePaymentIntent']);
    Route::post('mobile/verify-payment-intent', [WebPaymentApiController::class, 'verifyMobilePaymentIntent']);

    // Authenticated Pay-Per-View Checkout & Verification
    Route::post('pay-per-view/process-payment', [WebPaymentApiController::class, 'processPayPerViewPayment']);
    Route::post('pay-per-view/verify-payment', [WebPaymentApiController::class, 'verifyPayPerViewPayment']);

    // Playback URLs are returned only after Laravel verifies profile ownership and entitlement.
    Route::post('playback/details', [EntertainmentsController::class, 'playbackDetails'])->middleware('throttle:30,1');
    Route::post('playback/diagnostics', [\App\Http\Controllers\API\PlaybackDiagnosticsController::class, 'store'])->middleware('throttle:30,1');
    Route::post('playback/sessions/{sessionId}/heartbeat', [EntertainmentsController::class, 'heartbeatPlayback'])->whereUuid('sessionId')->middleware('throttle:120,1');
    Route::post('playback/sessions/{sessionId}/end', [EntertainmentsController::class, 'endPlayback'])->whereUuid('sessionId')->middleware('throttle:30,1');
});


Route::get('app-configuration', [APISettingController::class, 'appConfiguraton']);
Route::get('v3/playback/media/{assetId}/{file}', [Modules\Entertainment\Http\Controllers\API\PlaybackMediaController::class, 'show'])
    ->where('file', '.*')->middleware('signed')->name('playback.media');

Route::prefix('tv')->group(function () {
    Route::get('/initiate-session', [TvAuthController::class, 'initiateSession']);
    Route::post('/check-session', [TvAuthController::class, 'checkSession']);
});


/*
| Short Drama add-on installer — registered in the main app so it works when
| the ShortDrama module is not yet installed or enabled.
*/
Route::prefix('admin/short-drama/installer')->middleware(['auth:sanctum', 'throttle:api', 'can:manage-short-drama-admin'])->group(function () {
    Route::get('status', [App\Http\Controllers\API\Admin\ShortDramaPluginInstallerController::class, 'status']);
    Route::post('install', [App\Http\Controllers\API\Admin\ShortDramaPluginInstallerController::class, 'install']);
    Route::delete('uninstall', [App\Http\Controllers\API\Admin\ShortDramaPluginInstallerController::class, 'uninstall']);
});
