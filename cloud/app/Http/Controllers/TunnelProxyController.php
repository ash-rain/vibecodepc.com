<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ReprovisionTunnelJob;
use App\Models\Device;
use App\Services\CustomDomainService;
use App\Services\TunnelRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            if ($device) {
                $this->routingService->recordProxyFailure($device);
            }

            abort(502, 'Device is offline or tunnel is not active.');
        }

        // If a re-provisioning job is already running, return a retry-friendly
        // response instead of cascading more failures into the counter.
        if (Cache::has("tunnel-reprovisioning:{$device->id}")) {
            abort(503, 'Tunnel is being re-provisioned. Please try again shortly.');
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

            $status = $proxyResponse->status();
            $body = $proxyResponse->body();

            // Detect Cloudflare tunnel error pages (e.g. error 1033).
            // These indicate the tunnel infrastructure itself is broken
            // and needs re-provisioning, not just a transient upstream error.
            if ($this->isCloudflareTunnelError($status, $body)) {
                $this->dispatchReprovision($device, $status, $body);

                abort(502, 'Tunnel connection error. Automatic recovery has been initiated.');
            }

            // 502/503/504 from the tunnel itself means cloudflared is broken
            if ($status >= 502 && $status <= 504) {
                $this->routingService->recordProxyFailure($device);

                abort($status, 'Tunnel returned an error.');
            }

            $this->routingService->clearProxyFailures($device);

            return response($body, $status)
                ->withHeaders(
                    collect($proxyResponse->headers())
                        ->except(['transfer-encoding', 'connection'])
                        ->all()
                );
        } catch (\Exception $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            $this->routingService->recordProxyFailure($device);

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

    /**
     * Detect Cloudflare tunnel error pages returned when the tunnel
     * infrastructure itself is broken (e.g. connector disconnected,
     * tunnel deleted, DNS misconfigured).
     */
    private function isCloudflareTunnelError(int $statusCode, string $body): bool
    {
        // HTTP 530 is Cloudflare's status code for tunnel/origin errors.
        if ($statusCode === 530) {
            return true;
        }

        // Check for known Cloudflare tunnel error codes embedded in the HTML:
        // 1033 — Argo Tunnel error (connector not connected)
        // 1016 — Origin DNS error
        // 1015 — Host header mismatch
        if (preg_match('/cf-error-code["\'>\s]*(\d{4})/i', $body, $matches)) {
            return in_array((int) $matches[1], [1033, 1016, 1015], true);
        }

        return false;
    }

    /**
     * Dispatch a tunnel re-provisioning job for the device, guarded by
     * a cache flag so only one job runs per device at a time.
     */
    private function dispatchReprovision(Device $device, int $statusCode, string $body): void
    {
        $flag = "tunnel-reprovisioning:{$device->id}";

        if (Cache::has($flag)) {
            return;
        }

        // Set the flag before dispatching so concurrent requests don't
        // queue duplicate jobs. The job clears it when finished.
        Cache::put($flag, true, 300);

        ReprovisionTunnelJob::dispatch($device->id);

        Log::warning('Cloudflare tunnel error detected, re-provisioning dispatched', [
            'device_uuid' => $device->uuid,
            'status_code' => $statusCode,
            'cf_error' => $this->extractCfErrorCode($body),
        ]);
    }

    private function extractCfErrorCode(string $body): ?int
    {
        if (preg_match('/cf-error-code["\'>\s]*(\d{4})/i', $body, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
