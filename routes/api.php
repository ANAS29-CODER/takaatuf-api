<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Profile\ProfileController;
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
        ->where('provider', 'google|facebook|Google|Facebook');

    Route::get('/oauth/{provider}/callback', [AuthController::class, 'callback'])
        ->where('provider', 'google|facebook|Google|Facebook');
});

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Email Verification

// Verification link (auth)
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // يفعل الإيميل
    return response()->json([
        'message' => 'Email verified successfully'
    ]);
})->middleware(['auth:sanctum','signed'])
->name('verification.verify');

// Resend verification email (needs auth)
Route::post('/email/resend', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Verification email resent']);
})->middleware(['auth:sanctum']);


// Authenticated Routes

Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'showProfile']);
    Route::post('/profile', [ProfileController::class, 'updateProfile']);

    // Admin
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);

    // Protected test (requires profile completion) // test
    Route::middleware('profile.complete')->get('/protected-test', function () {
        return response()->json(['ok' => true, 'message' => 'You are allowed']);
    });

});

