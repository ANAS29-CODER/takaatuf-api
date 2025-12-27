<?php

use App\Http\Controllers\API\Auth\AuthController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function () {
   Route::get('/oauth/{provider}/callback', [AuthController::class, 'callback'])
    ->where('provider', 'google|facebook|Google|Facebook');

Route::get('/oauth/{provider}/redirect', [AuthController::class, 'redirect'])
    ->where('provider', 'google|facebook|Google|Facebook');
});
// Login عادي
Route::post('/login', [AuthController::class, 'login']);

// Logout (محمي)
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


?>
