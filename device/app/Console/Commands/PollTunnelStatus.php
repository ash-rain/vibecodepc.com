<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tunnel\TunnelService;
use Illuminate\Console\Command;

class PollTunnelStatus extends Command
{
    protected $signature = 'device:poll-tunnel-status';

    protected $description = 'Check if tunnel token file appeared and update tunnel status';

    public function handle(TunnelService $tunnelService): int
    {
        $result = $tunnelService->pollStatus();

        if ($result['detected']) {
            $this->info('Tunnel token detected! Updating status...');
            $this->info($result['message']);

            return self::SUCCESS;
        }

        if ($result['error']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
