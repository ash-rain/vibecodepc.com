<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TunnelRequestLog;
use Illuminate\Console\Command;

class PruneTunnelRequestLogs extends Command
{
    protected $signature = 'tunnel-logs:prune {--days=90 : Number of days to keep}';

    protected $description = 'Delete tunnel request logs older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $count = TunnelRequestLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$count} tunnel request log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
