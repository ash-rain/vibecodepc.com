<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\QuickTunnel;
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

        // If URL wasn't captured in the initial timeout, keep retrying
        if (! $url) {
            $tunnel = QuickTunnel::forDashboard();
            if ($tunnel) {
                for ($i = 0; $i < 15; $i++) {
                    sleep(2);
                    $url = $quickTunnelService->refreshUrl($tunnel);
                    if ($url) {
                        break;
                    }
                }
            }
        }

        if ($url) {
            try {
                $client->registerTunnelUrl($identity->getDeviceInfo()->id, $url);
            } catch (Throwable $e) {
                Log::warning("Failed to register tunnel URL with cloud: {$e->getMessage()}");
            }
        }
    }
}
