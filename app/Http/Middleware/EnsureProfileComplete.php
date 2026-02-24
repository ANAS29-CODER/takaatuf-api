<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

  public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    $missingFields = [];

    if (empty($user->full_name)) {
        $missingFields[] = 'full_name';
    }

    if (empty($user->city_neighborhood)) {
        $missingFields[] = 'city_neighborhood';
    }

    if (empty($user->role)) {
        $missingFields[] = 'role';
    }

    if ($user->role === 'Knowledge Provider' && !$user->wallets()->exists()) {
        $missingFields[] = 'wallet';
    }

  if ($user->role === 'Knowledge Requester' && !$user->paypalAccount()->whereNotNull('paypal_email')->exists()) {
    $missingFields[] = 'paypal_account';
}

    if (!empty($missingFields)) {
        return response()->json([
            'message' => 'Profile is incomplete. Please complete your profile before proceeding.',
            'missing_fields' => $missingFields
        ], 403);
    }

    return $next($request);
}


}
