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

// Public Global SEO Settings
Route::get('v3/seo-settings', [\Modules\SEO\Http\Controllers\SEOController::class, 'getSeoSettingsApi']);

// Provider-signed callbacks are verified by their dedicated controller handlers.
Route::post('webhooks/stripe', [WebPaymentApiController::class, 'stripeWebhook'])->middleware('throttle:300,1');
Route::post('webhooks/razorpay', [WebPaymentApiController::class, 'razorpayWebhook'])->middleware('throttle:300,1');
Route::post('webhooks/revenuecat', [WebPaymentApiController::class, 'revenueCatWebhook'])->middleware('throttle:300,1');


Route::get('/optimize', [QueryOptimizeController::class, 'optimize'])->name('optimize');

Route::middleware(['auth:sanctum', 'checkApiDevice'])->get('/user', function (Request $request) {
    return $request->user();
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

        Route::get('media/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'media'])->middleware('explicit.permission:media.view');
        Route::get('subscriptions/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'subscriptions'])->middleware('explicit.permission:subscriptions.view');
        Route::get('finance/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'finance'])->middleware('explicit.permission:finance.payments.view');
        Route::get('marketing/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'marketing'])->middleware('explicit.permission:marketing.campaigns.manage');
        Route::get('analytics/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'analytics'])->middleware('explicit.permission:analytics.audience.view');
        Route::get('operations/overview', [\App\Http\Controllers\API\AdminOperationsController::class, 'operations'])->middleware('explicit.permission:operations.health.view');
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
});


Route::get('app-configuration', [APISettingController::class, 'appConfiguraton']);

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
