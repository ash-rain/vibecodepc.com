<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ReprovisionTunnelJob;
use App\Models\Device;
use App\Models\TunnelRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TunnelRoutingService
{
    private const FAILURE_THRESHOLD = 3;

    private const FAILURE_WINDOW_SECONDS = 120;

    public function registerTunnel(Device $device, string $tunnelUrl): void
    {
        $device->update(['tunnel_url' => $tunnelUrl]);
    }

    /**
     * @param  array<int, array{path: string, target_port: int, project_name?: string}>  $routes
     * @return \Illuminate\Support\Collection<int, TunnelRoute>
     */
    public function updateRoutes(Device $device, string $subdomain, array $routes): \Illuminate\Support\Collection
    {
        // Deactivate all existing routes for this device+subdomain
        $device->tunnelRoutes()
            ->where('subdomain', $subdomain)
            ->update(['is_active' => false]);

        $tunnelRoutes = collect();

        foreach ($routes as $route) {
            $tunnelRoute = TunnelRoute::updateOrCreate(
                [
                    'device_id' => $device->id,
                    'subdomain' => $subdomain,
                    'path' => $route['path'] ?? '/',
                ],
                [
                    'target_port' => $route['target_port'],
                    'project_name' => $route['project_name'] ?? null,
                    'is_active' => true,
                ],
            );

            $tunnelRoutes->push($tunnelRoute);
        }

        return $tunnelRoutes;
    }

    public function resolveRoute(string $subdomain, string $path = '/', ?string $projectSlug = null): ?TunnelRoute
    {
        $query = TunnelRoute::query()
            ->active()
            ->where('subdomain', $subdomain);

        if ($projectSlug !== null) {
            return $query->where('project_name', $projectSlug)->first();
        }

        return $query->where('path', $path)->first();
    }

    public function deactivateDeviceRoutes(Device $device): int
    {
        return $device->tunnelRoutes()->update(['is_active' => false]);
    }

    /**
     * Record a proxy failure for a device. After consecutive failures exceed the
     * threshold, attempt automatic re-provisioning. Falls back to marking the
     * tunnel as broken if a re-provisioning job is already in progress.
     *
     * Returns true if the threshold was reached and recovery was initiated.
     */
    public function recordProxyFailure(Device $device): bool
    {
        $cacheKey = "tunnel-failures:{$device->id}";
        $count = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, self::FAILURE_WINDOW_SECONDS);

        if ($count < self::FAILURE_THRESHOLD) {
            return false;
        }

        Cache::forget($cacheKey);

        $reprovisionFlag = "tunnel-reprovisioning:{$device->id}";

        if (Cache::has($reprovisionFlag)) {
            // A re-provisioning job is already running; don't stack another
            // or mark broken while recovery is in progress.
            return true;
        }

        Cache::put($reprovisionFlag, true, 300);
        ReprovisionTunnelJob::dispatch($device->id);

        Log::info('Tunnel failure threshold reached, re-provisioning dispatched', [
            'device_uuid' => $device->uuid,
        ]);

        return true;
    }

    /**
     * Clear failure counter after a successful proxy.
     */
    public function clearProxyFailures(Device $device): void
    {
        Cache::forget("tunnel-failures:{$device->id}");
    }

    /**
     * Mark a device's tunnel as broken: clear the tunnel URL and deactivate all routes.
     */
    public function markTunnelBroken(Device $device): void
    {
        $device->update(['tunnel_url' => null]);
        $this->deactivateDeviceRoutes($device);

        Log::warning('Tunnel marked as broken after repeated proxy failures', [
            'device_uuid' => $device->uuid,
        ]);
    }
}
