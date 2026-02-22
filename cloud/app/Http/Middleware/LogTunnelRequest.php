<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TunnelRequestLog;
use App\Services\TunnelRoutingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogTunnelRequest
{
    public function __construct(private TunnelRoutingService $routingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        $host = $request->getHost();
        $baseDomain = config('app.tunnel_domain', 'vibecodepc.com');
        $host = strtolower($host);

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return;
        }

        $subdomain = substr($host, 0, -(strlen($baseDomain) + 1));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        $route = $this->routingService->resolveRoute($subdomain, $path);

        if (! $route && $path !== '/') {
            $route = $this->routingService->resolveRoute($subdomain, '/');
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
