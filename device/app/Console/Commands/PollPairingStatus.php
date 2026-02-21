<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CloudCredential;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use Illuminate\Console\Command;
use Throwable;

class PollPairingStatus extends Command
{
    protected $signature = 'device:poll-pairing
        {--interval=5 : Polling interval in seconds}
        {--once : Poll once and exit}';

    protected $description = 'Poll the cloud for device pairing status';

    private bool $shouldRun = true;

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
        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        $this->info("Polling pairing status for device: {$deviceInfo->id}");

        // Handle graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->shouldRun = false);
            pcntl_signal(SIGINT, fn () => $this->shouldRun = false);
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
}
