<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\VerificationController;
use App\Http\Controllers\API\KnowldgeRequest\KnowledgeRequestController;
use App\Http\Controllers\API\PayoutController;
use App\Http\Controllers\API\PayPalController;
use App\Http\Controllers\API\Profile\ProfileController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\API\KnowledgeProvider\KnowledgeProviderController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
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

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])  ->middleware('verified');;
// PayPal OAuth Callback (public route - user redirected from PayPal)
Route::get('/paypal/callback', [PayPalController::class, 'callback'])->name('paypal.callback');

Route::get('/email/verify/{id}/{hash}',
    [VerificationController::class, 'verify']
)->middleware(['signed'])->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.resend');

// Authenticated Routes
Route::middleware('auth:sanctum', 'verified')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'showProfile'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'updateProfile'])->name('profile.edit');
    Route::put('/profile/location', [ProfileController::class, 'updateWorkingLocation']);
});


Route::group([
    'middleware' => ['auth:sanctum', 'profile.completed']
], function () {

    Route::group(['middleware' => 'role:KP'], function () {
        // Knowledge Provider Dashboard
        Route::get('/dashboard/kp', [KnowledgeProviderController::class, 'dashboard']);
        Route::get('/dashboard/kp/earnings', [KnowledgeProviderController::class, 'earningsSummary']);
        Route::get('/dashboard/kp/active', [KnowledgeProviderController::class, 'activeRequests']);
        Route::get('/dashboard/kp/available', [KnowledgeProviderController::class, 'availableRequests']);
        Route::get('/dashboard/kp/completed', [KnowledgeProviderController::class, 'completedRequests']);

        // Knowledge Request Actions
        Route::get('/requests/{id}', [KnowledgeProviderController::class, 'showRequest']);
        Route::post('/requests/{id}/apply', [KnowledgeProviderController::class, 'applyToRequest']);
        Route::put('/requests/{id}/progress', [KnowledgeProviderController::class, 'updateProgress']);

        // Wallet Management
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::post('/wallets', [WalletController::class, 'store']);
        Route::get('/wallets/{id}', [WalletController::class, 'show']);
        Route::put('/wallets/{id}', [WalletController::class, 'update']);
        Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
        Route::post('/wallets/{id}/primary', [WalletController::class, 'setPrimary']);

        // Payout Management
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts/request', [PayoutController::class, 'requestPayout']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
    });

    // Knowledge Requester (KR) routes
    Route::group(['middleware' => 'role:KR'], function () {
        Route::post('/kr/create', [KnowledgeRequestController::class, 'store']);
        Route::get('/dashboard/kr', [KnowledgeRequestController::class, 'index']);
        Route::get('/payment/{request_id}', [PaymentController::class, 'create'])->name('payment.create');

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

// Admin
Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);


  // // Admin Routes
    // Route::middleware('admin')->group(function () {
    //     Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
    // });
