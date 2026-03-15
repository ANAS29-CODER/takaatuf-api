<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\VerificationController;
use App\Http\Controllers\API\KnowldgeRequester\KnowledgeRequestController;
use App\Http\Controllers\API\KnowledgeProvider\KnowledgeProviderController;
use App\Http\Controllers\API\KnowledgeProvider\TaskPageController;
use App\Http\Controllers\API\PayoutController;
use App\Http\Controllers\API\PayPalController;
use App\Http\Controllers\API\Profile\ProfileController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// OAuth Routes (session)
Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function () {
    Route::get('/oauth/{provider}/redirect', [AuthController::class, 'redirect'])
        ->where('provider', '(google|facebook)');

    Route::get('/oauth/{provider}/callback', [AuthController::class, 'callback'])
        ->where('provider', '(google|facebook)');
});
Route::middleware('auth:sanctum')->get('/auth/user', [AuthController::class, 'user']);

Route::post('/oauth/updateEmail', [AuthController::class, 'updateEmail'])
    ->middleware('auth:sanctum');

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

// PayPal OAuth Callback (public route - user redirected from PayPal)
Route::get('/paypal/callback', [PayPalController::class, 'callback'])->name('paypal.callback');

Route::get('/verify-email', [VerificationController::class, 'verify']);
Route::get(
    '/email/verify/{id}/{hash}',
    [VerificationController::class, 'verify']
)->middleware(['signed'])->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification.resend');

// Authenticated Routes

Route::middleware('auth:sanctum', 'verified')->prefix('profile')->group(function () {
    Route::get('/kp', [ProfileController::class, 'kpProfile']);
    Route::get('/kr', [ProfileController::class, 'krProfile']);
    Route::post('/complete', [ProfileController::class, 'completeProfile']);
    Route::post('/confirm-location', [ProfileController::class, 'confirmLocation']);
    Route::post('/payment', [ProfileController::class, 'updatePayment']);
    Route::post('/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/location', [ProfileController::class, 'updateWorkingLocation']);
});

Route::group([
    'middleware' => ['auth:sanctum'],
], function () {

    Route::group(['middleware' => 'role:KP'], function () {
        // Knowledge Provider Dashboard
        Route::prefix('dashboard/kp')->group(function () {

            Route::get('/', [KnowledgeProviderController::class, 'dashboard']);
            Route::get('/earnings', [KnowledgeProviderController::class, 'earningsSummary']);
            Route::get('/active-requests', [KnowledgeProviderController::class, 'activeRequests']);
            Route::get('/available-requests', [KnowledgeProviderController::class, 'availableRequests']);
            Route::get('/completed-requests', [KnowledgeProviderController::class, 'completedRequests']);
        });

        // Knowledge Request Actions
        Route::get('/requests/{id}', [KnowledgeProviderController::class, 'showRequest']);
        Route::post('/requests/{id}/apply', [KnowledgeProviderController::class, 'applyToRequest']);
        Route::put('/requests/{id}/progress', [KnowledgeProviderController::class, 'updateProgress']);

        // Task Page Routes
        Route::prefix('task/{requestId}')->group(function () {
            Route::get('/details', [TaskPageController::class, 'requestDetails']);
            Route::get('/', [TaskPageController::class, 'show']);
            Route::get('/status', [TaskPageController::class, 'getStatus']);
            Route::post('/draft', [TaskPageController::class, 'saveDraft']);
            Route::post('/media', [TaskPageController::class, 'uploadMedia']);
            Route::delete('/media/{mediaId}', [TaskPageController::class, 'removeMedia']);
            Route::post('/submit', [TaskPageController::class, 'submitWork']);
        });

        // Wallet Management
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::post('/add-wallet', [WalletController::class, 'store']);
        Route::get('/wallets/{id}', [WalletController::class, 'show']);
        Route::put('/update-wallets/{id}', [WalletController::class, 'update']);
        Route::delete('/delete-wallets/{id}', [WalletController::class, 'destroy']);
        Route::post('/wallet/{id}/primary', [WalletController::class, 'setPrimary']);

        // Payout Management
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts/request', [PayoutController::class, 'requestPayout']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
    });

    // Knowledge Requester (KR) routes
    Route::group(['middleware' => 'role:KR'], function () {

        Route::prefix('dashboard/kr')->group(function () {
            Route::get('/', [KnowledgeRequestController::class, 'index']);
            Route::post('/submit-request ', [KnowledgeRequestController::class, 'store']);
        });

        // Payment Routes for Knowledge Requester
        Route::prefix('payment/{requestId}')->group(function () {
            Route::get('/', [PaymentController::class, 'show']);
            Route::post('/calculate-fees', [PaymentController::class, 'calculateFees']);
            Route::post('/create-order', [PaymentController::class, 'createOrder']);
            Route::post('/capture', [PaymentController::class, 'capture']);
        });
        // PayPal routes for Knowledge Requester
        Route::prefix('paypal')->group(function () {
            Route::get('/status', [PayPalController::class, 'status']);
            Route::get('/account', [PayPalController::class, 'show']);
            Route::post('/connect', [PayPalController::class, 'connect']);
            Route::post('/email', [PayPalController::class, 'updateEmail']);
            Route::post('/disconnect', [PayPalController::class, 'disconnect']);
        });
    });
});

// Admin Routes - Protected by auth and admin role middleware
Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:sanctum', 'role:admin'],
], function () {
    // Dashboard Overview
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);

    // Knowledge Request Moderation
    Route::get('/requests/pending', [AdminDashboardController::class, 'pendingRequests']);
    Route::get('/requests', [AdminDashboardController::class, 'allRequests']);
    Route::get('/requests/{id}', [AdminDashboardController::class, 'showRequest']);
    Route::post('/requests/{id}/kr/approve', [AdminDashboardController::class, 'approveRequest']);
    Route::post('/requests/{id}/kr/reject', [AdminDashboardController::class, 'rejectRequest']);

    // KP Application Management
    Route::get('/kp-applications/pending', [AdminDashboardController::class, 'pendingKPApplications']);
    Route::get('/requests/{requestId}/kp-applications', [AdminDashboardController::class, 'getKPApplicationsForRequest']);
    Route::post('/kp-applications/approve', [AdminDashboardController::class, 'approveKPApplication']);
    Route::post('/kp-applications/reject', [AdminDashboardController::class, 'rejectKPApplication']);

    // Budget Management
    // // Admin can update budget and pay per KP for a request, which creates a new budget history entry
    Route::put('/requests/{requestId}/budget', [AdminDashboardController::class, 'updateBudget']);
    Route::get('/requests/{requestId}/budget-history', [AdminDashboardController::class, 'getBudgetHistory']);

    // Payout Management
    Route::get('/payouts/pending', [AdminDashboardController::class, 'pendingPayouts']);
    Route::get('/payouts', [AdminDashboardController::class, 'allPayouts']);
    Route::get('/payouts/{id}', [AdminDashboardController::class, 'showPayout']);
    Route::post('/payouts/{id}/complete', [AdminDashboardController::class, 'completePayout']);
    Route::post('/payouts/{id}/fail', [AdminDashboardController::class, 'failPayout']);

    // Work Submission Management
    Route::get('/submissions/pending', [AdminDashboardController::class, 'pendingSubmissions']);
    Route::get('/submissions/{id}', [AdminDashboardController::class, 'showSubmission']);
    Route::post('/submissions/{knowledge_request_id}/{kp_id}/approve', [AdminDashboardController::class, 'approveSubmission']);
    Route::post('/submissions/{knowledge_request_id}/{kp_id}/reject', [AdminDashboardController::class, 'rejectSubmission']);

    // Audit Logs
    Route::get('/audit-logs', [AdminDashboardController::class, 'auditLogs']);
});
