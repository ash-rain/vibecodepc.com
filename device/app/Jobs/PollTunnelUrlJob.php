<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\QuickTunnelUrlDiscovered;
use App\Models\QuickTunnel;
use App\Services\Tunnel\QuickTunnelService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PollTunnelUrlJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     * Set to allow for all polling attempts plus buffer.
     */
    public int $timeout = 60;

    /**
     * The number of times the job may be attempted.
     * This job runs once and either succeeds or gives up.
     */
    public int $tries = 1;

    /**
     * The unique lock duration in seconds.
     * Prevents duplicate polling jobs for the same tunnel.
     */
    public int $uniqueFor = 60;

    public function __construct(
        public QuickTunnel $tunnel,
        public int $maxWaitSeconds = 30,
        public int $pollIntervalSeconds = 2,
    ) {}

    /**
     * Execute the job - poll for tunnel URL from container logs.
     */
    public function handle(QuickTunnelService $service): void
    {
        // Skip if tunnel already has URL or is no longer active
        if (! $this->shouldContinuePolling()) {
            return;
        }

        $waited = 0;

        while ($waited < $this->maxWaitSeconds) {
            // Check if we should continue before each poll
            if (! $this->shouldContinuePolling()) {
                return;
            }

            // Attempt to extract URL from logs
            $url = $service->refreshUrl($this->tunnel);

            if ($url) {
                Log::info('Quick tunnel URL discovered via polling job', [
                    'tunnel_id' => $this->tunnel->id,
                    'container' => $this->tunnel->container_name,
                    'url' => $url,
                    'waited_seconds' => $waited,
                ]);

                // Broadcast event for real-time updates
                event(new QuickTunnelUrlDiscovered($this->tunnel->refresh(), $url));

                return;
            }

            $waited += $this->pollIntervalSeconds;

            // Sleep only if we're going to continue
            if ($waited < $this->maxWaitSeconds) {
                sleep($this->pollIntervalSeconds);
            }
        }

        Log::warning('Quick tunnel URL not discovered within polling timeout', [
            'tunnel_id' => $this->tunnel->id,
            'container' => $this->tunnel->container_name,
            'max_wait_seconds' => $this->maxWaitSeconds,
        ]);
    }

    /**
     * Get the unique lock key for this job.
     */
    public function uniqueId(): string
    {
        return (string) $this->tunnel->id;
    }

    /**
     * Determine if we should continue polling.
     * Stop if tunnel has URL, is stopped, or no longer exists.
     */
    private function shouldContinuePolling(): bool
    {
        // Refresh tunnel state from database
        $this->tunnel->refresh();

        // Stop if tunnel has URL already
        if ($this->tunnel->tunnel_url) {
            return false;
        }

        // Stop if tunnel is no longer active
        if (! $this->tunnel->isActive()) {
            Log::info('Stopping tunnel URL polling - tunnel no longer active', [
                'tunnel_id' => $this->tunnel->id,
                'status' => $this->tunnel->status,
            ]);

            return false;
        }

        return true;
    }
}
