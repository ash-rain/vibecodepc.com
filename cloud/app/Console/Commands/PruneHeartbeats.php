<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeviceTelemetryService;
use Illuminate\Console\Command;

class PruneHeartbeats extends Command
{
    protected $signature = 'heartbeats:prune {--days=30 : Number of days to keep}';

    protected $description = 'Delete heartbeat records older than the specified number of days';

    public function handle(DeviceTelemetryService $telemetryService): int
    {
        $days = (int) $this->option('days');
        $count = $telemetryService->pruneOldHeartbeats($days);

        $this->info("Pruned {$count} heartbeat(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
