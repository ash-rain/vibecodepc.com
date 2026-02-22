<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\TunnelProxyController;
use App\Models\TunnelRequestLog;
use App\Services\CustomDomainService;
use App\Services\TunnelRoutingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class TunnelProxyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (! $this->isTunnelRequest($host)) {
            return $next($request);
        }

        // Rate limit per host
        $rateLimitKey = 'tunnel-proxy:'.$host;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            abort(429, 'Too many requests to this subdomain.');
        }
        RateLimiter::hit($rateLimitKey, 60);

        $startTime = microtime(true);

        $response = app(TunnelProxyController::class)($request);

        // Log the request
        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    /**
     * Determine if the request should be handled by the tunnel proxy.
     */
    private function isTunnelRequest(string $host): bool
    {
        // Skip if the host matches the main app domain
        $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);
        if ($appHost && strtolower($host) === strtolower($appHost)) {
            return false;
        }

        // Check if it's a subdomain of the tunnel domain
        if ($this->extractSubdomain($host) !== null) {
            return true;
        }

        // Check if it's a registered custom domain
        try {
            return app(CustomDomainService::class)->resolveToUsername($host) !== null;
        } catch (\Exception) {
            return false;
        }
    }

    private function extractSubdomain(string $host): ?string
    {
        $baseDomain = config('app.tunnel_domain', 'vibecodepc.com');
        $host = strtolower($host);

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -(strlen($baseDomain) + 1));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return $subdomain;
    }

    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        // For custom domains, resolve the subdomain
        if ($subdomain === null) {
            $subdomain = app(CustomDomainService::class)->resolveToUsername($host);
        }

        if (! $subdomain) {
            return;
        }

        $routingService = app(TunnelRoutingService::class);
        $path = '/'.ltrim($request->path(), '/');
        $route = $routingService->resolveRoute($subdomain, $path);

        if (! $route && $path !== '/') {
            $route = $routingService->resolveRoute($subdomain, '/');
        }

        if (! $route) {
            return;
        }

        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        TunnelRequestLog::query()->create([
            'tunnel_route_id' => $route->id,
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}
