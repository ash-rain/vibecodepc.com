<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\QuickTunnel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class QuickTunnelService
{
    private const CONTAINER_PREFIX = 'vibe-qt';

    private const CLOUDFLARED_IMAGE = 'cloudflare/cloudflared:latest';

    private const URL_WAIT_SECONDS = 10;

    private const URL_POLL_INTERVAL = 2;

    /**
     * Start a quick tunnel for the dashboard on the device app port.
     * Returns the tunnel URL, or null if the URL wasn't captured in time.
     */
    public function startForDashboard(): ?string
    {
        $port = (int) config('vibecodepc.tunnel.device_app_port');
        $tunnel = $this->start($port);

        return $tunnel->tunnel_url;
    }

    /**
     * Start a quick tunnel for a given local port.
     * Pass null for $projectId to create a dashboard tunnel.
     */
    public function start(int $port, ?int $projectId = null): QuickTunnel
    {
        $existing = $projectId
            ? QuickTunnel::forProject($projectId)
            : QuickTunnel::forDashboard();

        if ($existing) {
            $this->forceRemove($existing);
        }

        $suffix = strtolower(Str::random(8));
        $label = $projectId ? "p{$projectId}" : 'dash';
        $containerName = self::CONTAINER_PREFIX . "-{$label}-{$suffix}";

        $result = Process::timeout(30)->run(sprintf(
            'docker run -d --name %s --network host %s tunnel --url http://localhost:%d',
            escapeshellarg($containerName),
            escapeshellarg(self::CLOUDFLARED_IMAGE),
            $port,
        ));

        if (! $result->successful()) {
            throw new \RuntimeException(
                'Failed to start quick tunnel container: ' . trim($result->errorOutput()),
            );
        }

        $containerId = substr(trim($result->output()), 0, 12);

        $tunnel = QuickTunnel::create([
            'project_id' => $projectId,
            'container_name' => $containerName,
            'container_id' => $containerId,
            'local_port' => $port,
            'status' => 'starting',
            'started_at' => now(),
        ]);

        $url = $this->waitForUrl($containerName);

        $tunnel->update([
            'tunnel_url' => $url,
            'status' => 'running',
        ]);

        return $tunnel->refresh();
    }

    /**
     * Stop a running quick tunnel and remove its container.
     */
    public function stop(QuickTunnel $tunnel): void
    {
        $this->removeContainer($tunnel->container_name);

        $tunnel->update([
            'status' => 'stopped',
            'stopped_at' => now(),
        ]);
    }

    /**
     * Check if the quick tunnel container is still alive.
     */
    public function isHealthy(QuickTunnel $tunnel): bool
    {
        if (! $tunnel->isActive()) {
            return false;
        }

        $result = Process::timeout(5)->run(sprintf(
            'docker inspect --format={{.State.Running}} %s 2>/dev/null',
            escapeshellarg($tunnel->container_name),
        ));

        return $result->successful() && str_contains(trim($result->output()), 'true');
    }

    /**
     * Try to capture the tunnel URL from container logs if not yet available.
     */
    public function refreshUrl(QuickTunnel $tunnel): ?string
    {
        if ($tunnel->tunnel_url) {
            return $tunnel->tunnel_url;
        }

        $url = $this->extractUrlFromLogs($tunnel->container_name);

        if ($url) {
            $tunnel->update(['tunnel_url' => $url, 'status' => 'running']);
        }

        return $url;
    }

    /**
     * Completely remove a quick tunnel: stop container, delete DB record.
     */
    public function cleanup(QuickTunnel $tunnel): void
    {
        $this->removeContainer($tunnel->container_name);
        $tunnel->delete();
    }

    /**
     * Stop and remove container, then delete the record.
     * Used internally before starting a replacement tunnel.
     */
    private function forceRemove(QuickTunnel $tunnel): void
    {
        $this->removeContainer($tunnel->container_name);
        $tunnel->delete();
    }

    private function removeContainer(string $containerName): void
    {
        Process::timeout(15)->run(sprintf(
            'docker rm -f %s 2>/dev/null',
            escapeshellarg($containerName),
        ));
    }

    private function waitForUrl(string $containerName): ?string
    {
        $waited = 0;

        while ($waited < self::URL_WAIT_SECONDS) {
            sleep(self::URL_POLL_INTERVAL);
            $waited += self::URL_POLL_INTERVAL;

            $url = $this->extractUrlFromLogs($containerName);

            if ($url) {
                return $url;
            }
        }

        Log::warning('Quick tunnel URL not captured within timeout', [
            'container' => $containerName,
            'timeout' => self::URL_WAIT_SECONDS,
        ]);

        return null;
    }

    private function extractUrlFromLogs(string $containerName): ?string
    {
        $result = Process::timeout(5)->run(sprintf(
            'docker logs %s 2>&1',
            escapeshellarg($containerName),
        ));

        if (! $result->successful()) {
            return null;
        }

        if (preg_match('#(https://[a-zA-Z0-9-]+\.trycloudflare\.com)#', $result->output(), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
