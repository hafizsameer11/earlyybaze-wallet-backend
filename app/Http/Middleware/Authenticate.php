<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
     public function handle($request, Closure $next, ...$guards)
    {
        // First, let Laravel authenticate the user
        $this->authenticate($request, $guards);

        // If authenticated, log request
        if (auth()->check()) {
            ApiRequestLog::create([
                'email'  => auth()->user()->email,
                'method' => $request->method(),
                'url'    => $request->fullUrl(),
                'headers'=> $request->headers->all(),
                'body'   => $request->except(['password', 'password_confirmation']), // don’t log sensitive
            ]);
        }

        return $next($request);
    }
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}
