<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
     public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Only act on admins
        if (!$user || $user->role === 'user') {
            return $next($request);
        }

        // If admin has 2FA disabled, allow with any admin token
        if (!$user->two_factor_enabled) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        // Must have admin + 2fa:passed abilities
        if (!$token || !$token->can('admin') || !$token->can('2fa:passed')) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'twoFARequired' => true,
            ], 423);
        }

        return $next($request);
    }
}
