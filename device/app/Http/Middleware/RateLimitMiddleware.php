<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RateLimitMiddleware
{
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  int  $maxAttempts  Maximum number of requests allowed
     * @param  int  $decayMinutes  Time window in minutes
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): SymfonyResponse
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiterFacade::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiterFacade::availableIn($key);

            /** @var \Illuminate\Http\JsonResponse $response */
            $response = response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], SymfonyResponse::HTTP_TOO_MANY_REQUESTS);

            $response->headers->set('Retry-After', (string) $seconds);
            $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', '0');

            return $response;
        }

        RateLimiterFacade::hit($key, $decayMinutes * 60);

        $remaining = RateLimiterFacade::remaining($key, $maxAttempts);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    /**
     * Resolve the request signature used to identify unique requests.
     *
     * Uses the authenticated user's ID if available, otherwise falls back to IP address.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($request->user()) {
            return 'user:'.$request->user()->getAuthIdentifier();
        }

        // Use IP address as fallback for unauthenticated requests
        return 'ip:'.$request->ip();
    }
}
