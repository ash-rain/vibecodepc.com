<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Services\CloudflareTunnelService;
use Illuminate\Console\Command;

class ReconfigureTunnelIngress extends Command
{
    protected $signature = 'tunnel:reconfigure
        {uuid : Device UUID}
        {--port= : Override the device app port (default: from config)}';

    protected $description = 'Update the Cloudflare tunnel ingress config for a device';

    public function handle(CloudflareTunnelService $cfService): int
    {
        $device = Device::where('uuid', $this->argument('uuid'))->first();

        if (! $device) {
            $this->error('Device not found.');

            return self::FAILURE;
        }

        $route = TunnelRoute::where('device_id', $device->id)
            ->where('is_active', true)
            ->first();

        if (! $route) {
            $this->error('No active tunnel route found for this device.');

            return self::FAILURE;
        }

        $port = (int) ($this->option('port') ?? config('cloudflare.device_app_port'));
        $hostname = "{$route->subdomain}.vibecodepc.com";
        $tunnelName = "device-{$device->uuid}";

        $tunnel = $cfService->findTunnelByName($tunnelName);

        if (! $tunnel) {
            $this->error("Cloudflare tunnel '{$tunnelName}' not found.");

            return self::FAILURE;
        }

        $cfService->configureTunnelIngress($tunnel['id'], $hostname, $port);

        $route->update(['target_port' => $port]);

        $this->info("Tunnel ingress updated: {$hostname} → localhost:{$port}");

        return self::SUCCESS;
    }
}
