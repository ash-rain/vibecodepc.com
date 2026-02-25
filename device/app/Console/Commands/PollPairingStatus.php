<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProvisionQuickTunnelJob;
use App\Models\CloudCredential;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;
use VibecodePC\Common\DTOs\DeviceInfo;

class PollPairingStatus extends Command
{
    protected $signature = 'device:poll-pairing';

    protected $description = 'Check cloud for device pairing status and dispatch tunnel provisioning';

    public function handle(
        CloudApiClient $client,
        DeviceIdentityService $identity,
        DeviceStateService $stateService,
    ): int {
        if (! $identity->hasIdentity()) {
            $this->error('No device identity found. Run: php artisan device:generate-id');

            return self::FAILURE;
        }

        $deviceInfo = $identity->getDeviceInfo();

        // Register with cloud at most once per minute (idempotent but saves bandwidth)
        $this->registerDeviceIfDue($client, $deviceInfo);

        // Already paired? Nothing to do.
        $credential = CloudCredential::current();
        if ($credential?->isPaired()) {
            return self::SUCCESS;
        }

        $this->info("Checking pairing status for device: {$deviceInfo->id}");

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

                ProvisionQuickTunnelJob::dispatch();
                $this->info('Tunnel provisioning dispatched.');
            }
        } catch (Throwable $e) {
            $this->warn("Poll failed: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }

    private function registerDeviceIfDue(CloudApiClient $client, DeviceInfo $deviceInfo): void
    {
        $cacheKey = 'device:registration:last';

        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            $client->registerDevice($deviceInfo->toArray());
            Cache::put($cacheKey, true, now()->addMinute());
            $this->info('Device registered with cloud.');
        } catch (Throwable $e) {
            $this->warn("Failed to register device with cloud: {$e->getMessage()}");
        }
    }
}
