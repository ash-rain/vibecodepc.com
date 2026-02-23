<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReprovisionTunnelJob;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTunnelHealth extends Command
{
    protected $signature = 'tunnel:health-check
        {--dry-run : Report status without dispatching any jobs}';

    protected $description = 'Probe active tunnel endpoints and re-provision any that return Cloudflare tunnel errors';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $devices = Device::claimed()
            ->online()
            ->whereNotNull('tunnel_url')
            ->whereHas('tunnelRoutes', fn ($q) => $q->active())
            ->get();

        if ($devices->isEmpty()) {
            $this->line('No active tunnels to check.');

            return self::SUCCESS;
        }

        $this->line("Checking {$devices->count()} tunnel(s)...");

        $broken = 0;

        foreach ($devices as $device) {
            $status = $this->probeTunnel($device);

            if ($status === null) {
                // Healthy or unreachable in a non-CF-error way — skip
                continue;
            }

            $broken++;
            $this->warn("  [{$status}] {$device->uuid} — {$device->tunnel_url}");

            if ($dryRun) {
                continue;
            }

            $this->dispatchReprovision($device);
        }

        $label = $dryRun ? ' (dry run)' : '';
        $this->info("Done. {$broken} broken tunnel(s) detected{$label}.");

        return self::SUCCESS;
    }

    /**
     * Probe a device's tunnel URL and return the CF error code if a tunnel
     * infrastructure error is detected, or null if the tunnel is healthy.
     */
    private function probeTunnel(Device $device): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($device->tunnel_url);

            $status = $response->status();
            $body = $response->body();

            if ($this->isCloudflareTunnelError($status, $body)) {
                $cfCode = $this->extractCfErrorCode($body);

                return $cfCode ? "CF-{$cfCode}" : "HTTP-{$status}";
            }

            return null;
        } catch (\Throwable $e) {
            // Connection timeout, DNS failure, etc. — not necessarily a CF
            // tunnel error. Log but don't treat as broken tunnel infra.
            Log::debug('Tunnel health-check probe failed', [
                'device_uuid' => $device->uuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isCloudflareTunnelError(int $statusCode, string $body): bool
    {
        if ($statusCode === 530) {
            return true;
        }

        if (preg_match('/cf-error-code["\'>\s]*(\d{4})/i', $body, $matches)) {
            return in_array((int) $matches[1], [1033, 1016, 1015], true);
        }

        return false;
    }

    private function extractCfErrorCode(string $body): ?int
    {
        if (preg_match('/cf-error-code["\'>\s]*(\d{4})/i', $body, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function dispatchReprovision(Device $device): void
    {
        $flag = "tunnel-reprovisioning:{$device->id}";

        if (Cache::has($flag)) {
            $this->line("    Skipped — re-provisioning already in progress.");

            return;
        }

        Cache::put($flag, true, 300);
        ReprovisionTunnelJob::dispatch($device->id);

        Log::info('Health-check: tunnel error detected, re-provisioning dispatched', [
            'device_uuid' => $device->uuid,
        ]);

        $this->line("    Re-provisioning job dispatched.");
    }
}
