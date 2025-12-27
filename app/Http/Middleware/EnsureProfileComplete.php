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
   public function handle($request, Closure $next)
{
    $user = $request->user();

    if ($user && !$user->profile_completed) {
        return response()->json([
            'message' => 'Profile incomplete',
            'code' => 'PROFILE_INCOMPLETE'
        ], 403);
    }

    return $next($request);
}

}
