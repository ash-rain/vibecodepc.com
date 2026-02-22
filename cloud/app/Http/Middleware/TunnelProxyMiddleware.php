<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\TunnelProxyController;
use App\Models\Device;
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

        // Check if device owner has an active subscription for tunnel access
        $subdomain = $this->extractSubdomain($host);
        if ($subdomain) {
            $device = Device::whereHas('tunnelRoutes', fn ($q) => $q->where('subdomain', $subdomain)->where('is_active', true))
                ->first();

            if ($device?->user && ! $device->user->canUseTunnel()) {
                abort(402, 'Device owner subscription required for tunnel access.');
            }
        }

        $startTime = microtime(true);

        try {
            $response = app(TunnelProxyController::class)($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Proxy controller aborts with 502/503/504 on tunnel errors — re-throw
            // after logging so the framework renders the error page
            $this->logRequest($request, response('', $e->getStatusCode()), $startTime);

            throw $e;
        }

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

    /**
     * Extract the subdomain from the host. Supports both simple subdomains
     * (username.vibecodepc.com) and compound subdomains (project--username.vibecodepc.com).
     *
     * @return array{subdomain: string, project: string|null}|null
     */
    private function extractSubdomainParts(string $host): ?array
    {
        $baseDomain = config('app.tunnel_domain', 'vibecodepc.com');
        $host = strtolower($host);

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $prefix = substr($host, 0, -(strlen($baseDomain) + 1));

        if ($prefix === '' || str_contains($prefix, '.')) {
            return null;
        }

        if (str_contains($prefix, '--')) {
            [$project, $subdomain] = explode('--', $prefix, 2);

            return ['subdomain' => $subdomain, 'project' => $project];
        }

        return ['subdomain' => $prefix, 'project' => null];
    }

    private function extractSubdomain(string $host): ?string
    {
        $parts = $this->extractSubdomainParts($host);

        return $parts['subdomain'] ?? null;
    }

    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        $host = $request->getHost();
        $parts = $this->extractSubdomainParts($host);
        $subdomain = $parts['subdomain'] ?? null;
        $projectSlug = $parts['project'] ?? null;

        // For custom domains, resolve the subdomain
        if ($subdomain === null) {
            $subdomain = app(CustomDomainService::class)->resolveToUsername($host);
        }

        if (! $subdomain) {
            return;
        }

        $routingService = app(TunnelRoutingService::class);
        $path = '/'.ltrim($request->path(), '/');
        $route = $routingService->resolveRoute($subdomain, $path, $projectSlug);

        if (! $route && $path !== '/') {
            $route = $routingService->resolveRoute($subdomain, '/', $projectSlug);
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
