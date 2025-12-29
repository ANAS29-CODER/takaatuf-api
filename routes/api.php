<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Profile\ProfileController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

// OAuth Routes (لازم sessions)
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

// Login (email/pass)
Route::post('/login', [AuthController::class, 'login']);

// Logout (محمي)
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// ✅ Profile routes (محمي بس بالتوكن فقط)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'showProfile']);
    Route::post('/profile', [ProfileController::class, 'updateProfile']);
});

Route::middleware(['auth:sanctum', 'profile.complete'])->get('/protected-test', function () {
    return response()->json(['ok' => true, 'message' => 'You are allowed']);
});

// // ✅ باقي المنصة (اللي بتحتاج بروفايل مكتمل) هون بتحطي profile.complete
// Route::middleware(['auth:sanctum', 'profile.complete'])->group(function () {
//     // مثال:
//     // Route::get('/dashboard', ...);
//     // Route::post('/requests', ...);
// });




?>
