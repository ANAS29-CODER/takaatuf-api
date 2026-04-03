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
    public function handle(Request $request, Closure $next, string $role_type): Response
    {
        $role = strtolower($role_type);

        // Map shorthand role names to full role names
        $roleMap = [
            'kp' => 'Knowledge Provider',
            'kr' => 'Knowledge Requester',
            'admin' => 'Admin',
        ];

        $role = $roleMap[$role] ?? $role;

        $user = auth()->user();

        abort_if(
            $user->role !== $role,
            403,
            'Unauthorized: This action requires the ' . $role . ' role.'
        );

        return $next($request);
    }
}
