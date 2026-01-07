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
        // إذا كانت profile_completed false، قم بإعادة التوجيه إلى صفحة البروفايل
        if (auth()->user()->profile_completed === false) {
            return redirect('profile')->with('message', 'Please complete your profile before proceeding.');
        }

        return $next($request);

}
}
