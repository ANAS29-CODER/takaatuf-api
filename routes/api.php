<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\ApI\KnowldgeRequest\KnowledgeRequestController;
use App\Http\Controllers\API\Profile\ProfileController;
use App\Http\Controllers\API\Wallet\WalletController;
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
Route::post('/login', [AuthController::class, 'login']);

// Email Verification Routes
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    try {
        $request->fulfill();
        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Invalid or expired verification link.'
        ], 400);
    }
})->middleware(['auth:sanctum', 'signed'])
->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    $user = $request->user();

    if ($user->hasVerifiedEmail()) {
        return response()->json([
            'message' => 'Email is already verified'
        ], 400);
    }
    $user->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Verification email resent successfully'
    ], 200);
})->middleware(['auth:sanctum', 'throttle:6,1']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'showProfile'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'updateProfile'])->name('profile.edit');


});


Route::group([
    'middleware' => ['auth:sanctum', 'profile.completed']
], function () {

    // Knowledge Provider (KP) routes
    Route::group(['middleware' => 'role:KP'], function () {
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::post('/wallets', [WalletController::class, 'store']);
        Route::get('/wallets/{id}', [WalletController::class, 'show']);
        Route::put('/wallets/{id}', [WalletController::class, 'update']);
        Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
        Route::post('/wallets/{id}/primary', [WalletController::class, 'setPrimary']);
    });

    // Knowledge Requester (KR) routes
    Route::group(['middleware' => 'role:KR'], function () {
      Route::post('/kr/create', [KnowledgeRequestController::class, 'store']);
         Route::get('/dashboard/kr', [KnowledgeRequestController::class, 'index']);
         Route::get('/payment/{request_id}', [PaymentController::class, 'create'])->name('payment.create');
    });

});

    // Admin
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);


  // // Admin Routes
    // Route::middleware('admin')->group(function () {
    //     Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
    // });


