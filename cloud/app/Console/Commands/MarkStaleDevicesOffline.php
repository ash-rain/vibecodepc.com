<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeviceTelemetryService;
use Illuminate\Console\Command;

class MarkStaleDevicesOffline extends Command
{
    protected $signature = 'devices:mark-stale';

    protected $description = 'Mark devices with no heartbeat in 5+ minutes as offline';

    public function handle(DeviceTelemetryService $telemetryService): int
    {
        $count = $telemetryService->markStaleDevicesOffline();

        $this->info("Marked {$count} stale device(s) as offline.");

        return self::SUCCESS;
    }
}
