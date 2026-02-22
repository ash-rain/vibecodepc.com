<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CustomDomainService;
use App\Services\TunnelRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TunnelProxyController extends Controller
{
    public function __construct(
        private TunnelRoutingService $routingService,
        private CustomDomainService $customDomainService,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        $host = $request->getHost();
        $parts = $this->extractSubdomainParts($host);
        $subdomain = $parts['subdomain'] ?? null;
        $projectSlug = $parts['project'] ?? null;

        if (! $subdomain) {
            // Try custom domain resolution
            $subdomain = $this->customDomainService->resolveToUsername($host);
        }

        if (! $subdomain) {
            abort(404, 'Invalid subdomain.');
        }

        $path = '/'.ltrim($request->path(), '/');
        $route = $this->routingService->resolveRoute($subdomain, $path, $projectSlug);

        // Fall back to root path if specific path not found
        if (! $route && $path !== '/') {
            $route = $this->routingService->resolveRoute($subdomain, '/', $projectSlug);
        }

        if (! $route) {
            abort(404, 'No tunnel route found for this subdomain.');
        }

        $device = $route->device;

        if (! $device || ! $device->tunnel_url || ! $device->is_online) {
            abort(502, 'Device is offline or tunnel is not active.');
        }

        $targetUrl = rtrim($device->tunnel_url, '/').$path;

        try {
            $proxyResponse = Http::timeout(30)
                ->withHeaders([
                    'X-Forwarded-For' => $request->ip(),
                    'X-Forwarded-Host' => $host,
                    'X-Forwarded-Proto' => $request->getScheme(),
                ])
                ->send($request->method(), $targetUrl, [
                    'query' => $request->query(),
                    'body' => $request->getContent(),
                ]);

            return response($proxyResponse->body(), $proxyResponse->status())
                ->withHeaders(
                    collect($proxyResponse->headers())
                        ->except(['transfer-encoding', 'connection'])
                        ->all()
                );
        } catch (\Exception) {
            abort(502, 'Unable to reach the device tunnel.');
        }
    }

    /**
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
}
