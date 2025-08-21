<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
     public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        // Assuming you have a `role` column on the users table
        if ($request->user()->role === 'user') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Forbidden: not allowed for user role.'], 403)
                : route('login');
        }

        return $next($request);
    }
}
