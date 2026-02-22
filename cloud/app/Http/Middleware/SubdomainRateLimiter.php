<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SubdomainRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $key = 'tunnel-proxy:'.$host;

        if (RateLimiter::tooManyAttempts($key, 60)) {
            abort(429, 'Too many requests to this subdomain.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
