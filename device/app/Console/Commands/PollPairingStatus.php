<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CloudCredential;
use App\Models\QuickTunnel;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use App\Services\Tunnel\QuickTunnelService;
use App\Services\WizardProgressService;
use Illuminate\Console\Command;
use Throwable;
use VibecodePC\Common\DTOs\DeviceInfo;

class PollPairingStatus extends Command
{
    protected $signature = 'device:poll-pairing
        {--interval=5 : Polling interval in seconds}
        {--once : Poll once and exit}';

    protected $description = 'Poll the cloud for device pairing status and auto-provision tunnel';

    private bool $shouldRun = true;

    public function handle(
        CloudApiClient $client,
        DeviceIdentityService $identity,
        DeviceStateService $stateService,
        QuickTunnelService $quickTunnelService,
        WizardProgressService $progressService,
    ): int {
        if (! $identity->hasIdentity()) {
            $this->error('No device identity found. Run: php artisan device:generate-id');

            return self::FAILURE;
        }

        // Skip if already paired — nothing to do
        $credential = CloudCredential::current();
        if ($credential?->isPaired()) {
            $this->info('Device is already paired. Exiting.');

            return self::SUCCESS;
        }

        $deviceInfo = $identity->getDeviceInfo();

        // Register device with cloud (idempotent — cloud handles duplicates)
        $this->registerDeviceWithCloud($client, $deviceInfo);

        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        $this->info("Polling pairing status for device: {$deviceInfo->id}");

        // Handle graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->shouldRun = false);
            pcntl_signal(SIGINT, fn() => $this->shouldRun = false);
        }

        while ($this->shouldRun) {
            try {
                $status = $client->getDeviceStatus($deviceInfo->id);

                $this->line("Status: {$status->status->value}");

                if ($status->pairing) {
                    $this->info('Device has been claimed! Storing credentials...');

                    CloudCredential::create([
                        'pairing_token_encrypted' => $status->pairing->token,
                        'cloud_username' => $status->pairing->username,
                        'cloud_email' => $status->pairing->email,
                        'cloud_url' => config('vibecodepc.cloud_url'),
                        'is_paired' => true,
                        'paired_at' => now(),
                    ]);

                    $stateService->setMode(DeviceStateService::MODE_WIZARD);

                    $this->info("Paired to: {$status->pairing->username} ({$status->pairing->email})");
                    $this->info('Device mode set to: wizard');

                    // Auto-provision quick tunnel for immediate access
                    $this->provisionQuickTunnel($quickTunnelService, $progressService, $client, $identity);

                    return self::SUCCESS;
                }
            } catch (Throwable $e) {
                $this->warn("Poll failed: {$e->getMessage()}");
            }

            if ($once) {
                break;
            }

            sleep($interval);
        }

        $this->info('Polling stopped.');

        return self::SUCCESS;
    }

    private function registerDeviceWithCloud(CloudApiClient $client, DeviceInfo $deviceInfo): void
    {
        try {
            $client->registerDevice($deviceInfo->toArray());
            $this->info('Device registered with cloud.');
        } catch (Throwable $e) {
            $this->warn("Failed to register device with cloud: {$e->getMessage()}");
        }
    }

    private function provisionQuickTunnel(
        QuickTunnelService $quickTunnelService,
        WizardProgressService $progressService,
        CloudApiClient $client,
        DeviceIdentityService $identity,
    ): void {
        $this->info('Starting quick tunnel...');

        try {
            $url = $quickTunnelService->startForDashboard();
        } catch (Throwable $e) {
            $this->warn("Quick tunnel failed: {$e->getMessage()}");

            if (! app()->environment('local')) {
                $this->info('Tunnel can be configured later via the wizard.');

                return;
            }

            // In local dev, fall back to the device's direct URL so the
            // cloud setup page can redirect without a real tunnel.
            $url = config('app.url');
            $this->info("Using local fallback URL: {$url}");
        }

        $progressService->seedProgress();

        // If URL wasn't captured in the initial timeout, keep retrying
        if (! $url) {
            $tunnel = QuickTunnel::forDashboard();
            if ($tunnel) {
                $this->info('Waiting for tunnel URL (may take up to 30s)...');
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
                $this->info('Tunnel URL registered with cloud.');
            } catch (Throwable $e) {
                $this->warn("Failed to register tunnel URL with cloud: {$e->getMessage()}");
            }

            $this->info("Quick tunnel active at {$url}");
            $this->info("Wizard available at {$url}/wizard");
        } else {
            $this->warn('Quick tunnel started but URL not captured. It will be registered later.');
        }
    }
}
