<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\TunnelRoute;
use App\Services\CloudflareTunnelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use VibecodePC\Common\Enums\DeviceStatus;

class CleanOrphanedTunnels extends Command
{
    protected $signature = 'tunnels:clean-orphaned
        {--dry-run : Show what would be cleaned without making changes}
        {--stale-days=7 : Consider tunnels orphaned after this many days offline}';

    protected $description = 'Clean up orphaned tunnels from deactivated/offline devices and free subdomains';

    public function handle(CloudflareTunnelService $cfService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $staleDays = (int) $this->option('stale-days');
        $cutoff = now()->subDays($staleDays);

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        $cleaned = 0;

        // 1. Deactivated devices — always clean up tunnels
        $deactivated = Device::where('status', DeviceStatus::Deactivated)
            ->whereHas('tunnelRoutes', fn($q) => $q->active())
            ->get();

        foreach ($deactivated as $device) {
            $this->cleanDeviceTunnel($device, $cfService, $dryRun, 'deactivated');
            $cleaned++;
        }

        // 2. Unclaimed devices that still have tunnel routes (e.g. after unpair)
        $unclaimed = Device::where('status', DeviceStatus::Unclaimed)
            ->whereHas('tunnelRoutes', fn($q) => $q->active())
            ->get();

        foreach ($unclaimed as $device) {
            $this->cleanDeviceTunnel($device, $cfService, $dryRun, 'unclaimed');
            $cleaned++;
        }

        // 3. Claimed devices that have been offline too long
        $staleOffline = Device::where('status', DeviceStatus::Claimed)
            ->where('is_online', false)
            ->where(function ($query) use ($cutoff) {
                $query->where('last_heartbeat_at', '<', $cutoff)
                    ->orWhereNull('last_heartbeat_at');
            })
            ->whereHas('tunnelRoutes', fn($q) => $q->active())
            ->get();

        foreach ($staleOffline as $device) {
            $this->cleanDeviceTunnel($device, $cfService, $dryRun, "offline > {$staleDays} days");
            $cleaned++;
        }

        // 4. Orphaned tunnel routes with no device
        $orphanedRoutes = TunnelRoute::active()
            ->whereDoesntHave('device')
            ->get();

        if ($orphanedRoutes->isNotEmpty()) {
            $this->line("Found {$orphanedRoutes->count()} orphaned route(s) with no device.");

            if (! $dryRun) {
                TunnelRoute::active()
                    ->whereDoesntHave('device')
                    ->update(['is_active' => false]);
            }
        }

        $this->info("Cleaned {$cleaned} orphaned tunnel(s)." . ($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }

    private function cleanDeviceTunnel(
        Device $device,
        CloudflareTunnelService $cfService,
        bool $dryRun,
        string $reason,
    ): void {
        $routes = $device->tunnelRoutes()->active()->get();
        $subdomains = $routes->pluck('subdomain')->unique();

        $this->line("Device {$device->uuid} ({$reason}): " . $subdomains->implode(', '));

        if ($dryRun) {
            return;
        }

        // Delete Cloudflare tunnel
        $tunnelName = "device-{$device->uuid}";

        try {
            $tunnel = $cfService->findTunnelByName($tunnelName);

            if ($tunnel) {
                $cfService->deleteTunnel($tunnel['id']);
                $this->line("  Deleted CF tunnel: {$tunnelName}");
            }
        } catch (Throwable $e) {
            Log::warning("Failed to delete CF tunnel {$tunnelName}", ['error' => $e->getMessage()]);
            $this->warn("  Could not delete CF tunnel: {$e->getMessage()}");
        }

        // Delete DNS records for each subdomain
        foreach ($subdomains as $subdomain) {
            try {
                $fqdn = "{$subdomain}.vibecodepc.com";
                $dnsId = $cfService->findDnsRecord($fqdn);

                if ($dnsId) {
                    $cfService->deleteDnsRecord($dnsId);
                    $this->line("  Deleted DNS record: {$fqdn}");
                }
            } catch (Throwable $e) {
                Log::warning("Failed to delete DNS for {$subdomain}", ['error' => $e->getMessage()]);
                $this->warn("  Could not delete DNS record: {$e->getMessage()}");
            }
        }

        // Deactivate all tunnel routes
        $device->tunnelRoutes()->update(['is_active' => false]);

        // Clear tunnel URL from device
        $device->update(['tunnel_url' => null]);

        $this->line('  Routes deactivated, tunnel URL cleared.');
    }
}
