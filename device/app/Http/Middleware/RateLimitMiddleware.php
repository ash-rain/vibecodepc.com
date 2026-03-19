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
     * @param  int  $maxAttempts  Maximum number of requests allowed for unauthenticated users
     * @param  int  $decayMinutes  Time window in minutes for unauthenticated users
     * @param  int|null  $authMaxAttempts  Maximum number of requests allowed for authenticated users (defaults to $maxAttempts if not specified)
     * @param  int|null  $authDecayMinutes  Time window in minutes for authenticated users (defaults to $decayMinutes if not specified)
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1, ?int $authMaxAttempts = null, ?int $authDecayMinutes = null): SymfonyResponse
    {
        $isAuthenticated = $request->user() !== null;

        // Use authenticated-specific limits if user is logged in and custom limits are provided
        $effectiveMaxAttempts = ($isAuthenticated && $authMaxAttempts !== null) ? $authMaxAttempts : $maxAttempts;
        $effectiveDecayMinutes = ($isAuthenticated && $authDecayMinutes !== null) ? $authDecayMinutes : $decayMinutes;

        $key = $this->resolveRequestSignature($request);

        if (RateLimiterFacade::tooManyAttempts($key, $effectiveMaxAttempts)) {
            $seconds = RateLimiterFacade::availableIn($key);

            /** @var \Illuminate\Http\JsonResponse $response */
            $response = response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $seconds,
            ], SymfonyResponse::HTTP_TOO_MANY_REQUESTS);

            $response->headers->set('Retry-After', (string) $seconds);
            $response->headers->set('X-RateLimit-Limit', (string) $effectiveMaxAttempts);
            $response->headers->set('X-RateLimit-Remaining', '0');

            return $response;
        }

        RateLimiterFacade::hit($key, $effectiveDecayMinutes * 60);

        $remaining = RateLimiterFacade::remaining($key, $effectiveMaxAttempts);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $effectiveMaxAttempts);
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
