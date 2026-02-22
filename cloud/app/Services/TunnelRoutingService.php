<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\TunnelRoute;

class TunnelRoutingService
{
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
}
