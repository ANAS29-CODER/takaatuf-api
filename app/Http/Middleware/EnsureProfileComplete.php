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
        // تأكد من أن المستخدم قام بتسجيل الدخول
        $user = $request->user();

        // تحقق من أن البروفايل مكتمل
        if (!$user || !$user->profile_completed) {
            return response()->json(['message' => 'Profile incomplete. Please complete your profile first.'], 403);
        }

        return $next($request);
    }
}
