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
    
    if (!auth()->user()->profile_completed) {
        return redirect()->route('profile.edit')->with('message', 'Please complete your profile before proceeding.');
    }

    return $next($request);
}

}
