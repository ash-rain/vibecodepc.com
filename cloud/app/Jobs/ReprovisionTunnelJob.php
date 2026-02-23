<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use App\Services\CloudflareTunnelService;
use App\Services\TunnelRoutingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReprovisionTunnelJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private readonly int $deviceId,
    ) {}

    public function handle(
        CloudflareTunnelService $cfService,
        TunnelRoutingService $routingService,
    ): void {
        $lock = Cache::lock("tunnel-reprovision:{$this->deviceId}", 300);

        if (! $lock->get()) {
            return;
        }

        try {
            $device = Device::find($this->deviceId);

            if (! $device) {
                return;
            }

            $route = $device->tunnelRoutes()->latest()->first();

            if (! $route) {
                Log::warning('Tunnel re-provisioning skipped: no routes found', [
                    'device_uuid' => $device->uuid,
                ]);

                return;
            }

            $subdomain = $route->subdomain;
            $tunnelName = "device-{$device->uuid}";
            $port = (int) config('cloudflare.device_app_port', 8001);
            $hostname = "{$subdomain}.vibecodepc.com";

            $tunnel = $cfService->findTunnelByName($tunnelName);
            $wasRecreated = false;

            if (! $tunnel) {
                $tunnel = $cfService->createTunnel($tunnelName);
                $wasRecreated = true;
            }

            $cfService->configureTunnelIngress($tunnel['id'], $hostname, $port);
            $cfService->createDnsRecord($subdomain, $tunnel['id']);

            $device->update([
                'tunnel_url' => "https://{$hostname}",
                'config_version' => ($device->config_version ?? 0) + 1,
            ]);

            $device->tunnelRoutes()
                ->where('subdomain', $subdomain)
                ->update(['is_active' => true]);

            // When the tunnel was fully recreated, the device needs a fresh token
            // to reconnect. Cache it encrypted so the device can pick it up via
            // the config endpoint on its next heartbeat cycle.
            if ($wasRecreated) {
                $token = $cfService->getTunnelToken($tunnel['id']);

                Cache::put(
                    "tunnel-new-token:{$device->id}",
                    encrypt($token),
                    now()->addHour(),
                );
            }

            $routingService->clearProxyFailures($device);

            Log::info('Tunnel re-provisioned successfully', [
                'device_uuid' => $device->uuid,
                'subdomain' => $subdomain,
                'tunnel_id' => $tunnel['id'],
                'was_recreated' => $wasRecreated,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tunnel re-provisioning failed, marking as broken', [
                'device_id' => $this->deviceId,
                'error' => $e->getMessage(),
            ]);

            $device = Device::find($this->deviceId);

            if ($device) {
                $routingService->markTunnelBroken($device);
            }
        } finally {
            Cache::forget("tunnel-reprovisioning:{$this->deviceId}");
            $lock->release();
        }
    }
}
