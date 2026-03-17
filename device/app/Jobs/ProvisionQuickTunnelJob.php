<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\Tunnel\QuickTunnelService;
use App\Services\WizardProgressService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionQuickTunnelJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function handle(
        QuickTunnelService $quickTunnelService,
        WizardProgressService $progressService,
        CloudApiClient $client,
        DeviceIdentityService $identity,
    ): void {
        // Skip if tunnel was explicitly skipped by user
        $tunnelConfig = TunnelConfig::current();
        if ($tunnelConfig?->isSkipped()) {
            Log::info('Skipping quick tunnel provisioning: tunnel setup was skipped');
            $progressService->seedProgress();

            return;
        }

        $url = null;

        try {
            $url = $quickTunnelService->startForDashboard();
        } catch (Throwable $e) {
            Log::warning("Quick tunnel failed: {$e->getMessage()}");

            if (! app()->environment('local')) {
                $progressService->seedProgress();

                return;
            }

            // In local dev, fall back to the device's direct URL so the
            // cloud setup page can redirect without a real tunnel.
            $url = config('app.url');
            Log::info("Using local fallback URL: {$url}");
        }

        $progressService->seedProgress();

        // URL discovery is now handled asynchronously by PollTunnelUrlJob.
        // This job just starts the tunnel; the async job will discover
        // the URL and broadcast events when ready.
        if ($url) {
            // URL was captured immediately (rare, but possible)
            try {
                $client->registerTunnelUrl($identity->getDeviceInfo()->id, $url);
            } catch (Throwable $e) {
                Log::warning("Failed to register tunnel URL with cloud: {$e->getMessage()}");
            }
        } else {
            Log::info('Quick tunnel started; URL will be discovered asynchronously by PollTunnelUrlJob');
        }
    }
}
