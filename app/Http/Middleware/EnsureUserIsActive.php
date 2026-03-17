<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * Block all actions for users with is_active = 0.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (int) ($user->is_active ?? 1) === 0) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => 'Account is inactive. Please contact support.',
                ], 403)
                : redirect()->route('login');
        }

        return $next($request);
    }
}

