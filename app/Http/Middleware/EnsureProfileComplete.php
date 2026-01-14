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

        if (empty($user->city_neighborhood)) {
            $missingFields[] = 'city_neighborhood';
        }

        if (!$user->profile_completed) {
            return response()->json([
                'message' => 'Profile is incomplete. Please complete your profile before creating a request.',
                'missing_fields' => $missingFields
            ], 403);
        }

        return $next($request);
    }

}
