<?php

namespace AnyMedia\Interpresso\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePasswordRequests
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'interpresso:password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->view('interpresso::forgot-password', ['retryAfter' => $seconds], 429, ['Retry-After' => (string) $seconds]);
        }
        RateLimiter::hit($key, 60);
        return $next($request);
    }
}
