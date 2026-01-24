<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\VerificationController;
use App\Http\Controllers\API\KnowldgeRequest\KnowledgeRequestController;
use App\Http\Controllers\API\PayoutController;
use App\Http\Controllers\API\Profile\ProfileController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\Payment\PaymentController;
use App\Models\User;
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
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::get('/email/verify/{id}/{hash}',
    [VerificationController::class, 'verify']
)->name('verification.verify');

// Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {

//     if (! $request->hasValidSignature()) {
//         return response()->json([
//             'message' => 'Invalid or expired verification link.'
//         ], 400);
//     }
//     $user = User::find($id);

//     if (! $user) {
//         return response()->json([
//             'message' => 'User not found.'
//         ], 404);
//     }

//     if (! hash_equals(
//         sha1($user->getEmailForVerification()),
//         $hash
//     )) {
//         return response()->json([
//             'message' => 'Invalid verification hash.'
//         ], 400);
//     }

//     if (! $user->hasVerifiedEmail()) {
//         $user->markEmailAsVerified();
//     }
//     return response()->json([
//         'message' => 'Email verified successfully.'
//     ]);

// })->name('verification.verify');


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
    Route::put('/profile/location', [ProfileController::class, 'updateWorkingLocation']);


});


Route::group([
    'middleware' => ['auth:sanctum', 'profile.completed']
], function () {

        Route::group(['middleware' => 'role:KP'], function () {
        //wallet
        Route::get('/wallets', [WalletController::class, 'index']);
        Route::post('/wallets', [WalletController::class, 'store']);
        Route::get('/wallets/{id}', [WalletController::class, 'show']);
        Route::put('/wallets/{id}', [WalletController::class, 'update']);
        Route::delete('/wallets/{id}', [WalletController::class, 'destroy']);
        Route::post('/wallets/{id}/primary', [WalletController::class, 'setPrimary']);

        //paypout
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::post('/payouts/request', [PayoutController::class, 'requestPayout']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);


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
