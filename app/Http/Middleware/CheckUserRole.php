<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($role === 'KP') {
            $role = 'Knowledge Provider';
        }
        if ($role === 'KR') {
            $role = 'Knowledge Requester';
        }

        $user = auth()->user();

        abort_if(
            $user->role !== $role,
            403,
            'Unauthorized. Insufficient permissions.'
        );

        return $next($request);
    }
}
