<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollTunnelStatus extends Command
{
    protected $signature = 'device:poll-tunnel-status';

    protected $description = 'Check if tunnel token file appeared and update tunnel status';

    public function handle(TunnelService $tunnelService): int
    {
        $config = TunnelConfig::current();

        // Only check if tunnel was skipped but not yet verified
        if (! $config || ! $config->isSkipped()) {
            return self::SUCCESS;
        }

        // Check if tunnel token file now exists (provisioned externally)
        if (! $tunnelService->isRunning()) {
            return self::SUCCESS;
        }

        // Tunnel token appeared! Update the config
        $this->info('Tunnel token detected! Updating status...');

        try {
            $config->markAsAvailable();
            Log::info('Tunnel status auto-detected: token file appeared, tunnel marked as available');
            $this->info('Tunnel is now available and marked as active');
        } catch (\Throwable $e) {
            Log::error('Failed to update tunnel status on auto-detect', ['error' => $e->getMessage()]);
            $this->error("Failed to update tunnel status: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
