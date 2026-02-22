<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\TunnelConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;

class TunnelService
{
    public function __construct(
        private readonly string $configPath = '/etc/cloudflared/config.yml',
        private readonly int $deviceAppPort = 8001,
        private readonly ?string $tokenFilePath = null,
    ) {}

    public function isInstalled(): bool
    {
        if ($this->tokenFilePath !== null) {
            return true;
        }

        $result = Process::run($this->shell('cloudflared --version 2>/dev/null'));

        return $result->successful();
    }

    public function isRunning(): bool
    {
        if ($this->tokenFilePath !== null) {
            return file_exists($this->tokenFilePath) && filesize($this->tokenFilePath) > 0;
        }

        return $this->findCloudflaredPids() !== [];
    }

    /**
     * Check if a tunnel token has been provisioned via the wizard.
     */
    public function hasCredentials(): bool
    {
        $config = TunnelConfig::current();

        return $config !== null
            && ! empty($config->tunnel_token_encrypted);
    }

    public function testConnectivity(string $subdomain): bool
    {
        $result = Process::timeout(15)->run(
            sprintf('curl -s -o /dev/null -w "%%{http_code}" https://%s.%s', escapeshellarg($subdomain), config('vibecodepc.cloud_domain')),
        );

        if (! $result->successful()) {
            return false;
        }

        $statusCode = (int) trim($result->output());

        return $statusCode >= 200 && $statusCode < 500;
    }

    /** @return array{installed: bool, running: bool, configured: bool} */
    public function getStatus(): array
    {
        return [
            'installed' => $this->isInstalled(),
            'running' => $this->isRunning(),
            'configured' => $this->hasCredentials(),
        ];
    }

    /**
     * Start cloudflared using the provisioned tunnel token.
     * Returns null on success, or an error message on failure.
     */
    public function start(): ?string
    {
        if ($this->isRunning()) {
            return null;
        }

        if (! $this->isInstalled()) {
            return 'cloudflared is not installed.';
        }

        if (! $this->hasCredentials()) {
            return 'Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.';
        }

        $token = TunnelConfig::current()->tunnel_token_encrypted;

        // Token-file mode: write token to shared volume for the cloudflared container
        if ($this->tokenFilePath !== null) {
            $dir = dirname($this->tokenFilePath);

            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if (! is_writable($dir)) {
                return "Tunnel token directory is not writable: {$dir}";
            }

            file_put_contents($this->tokenFilePath, $token);

            return null;
        }

        // Try systemd first (Linux production) — write token to env file for the service
        if ($this->hasSystemd()) {
            $envDir = dirname($this->configPath);
            $envFile = $envDir . '/tunnel.env';
            @mkdir($envDir, 0755, true);
            file_put_contents($envFile, "TUNNEL_TOKEN={$token}\n");
            chmod($envFile, 0600);

            $result = Process::run('sudo systemctl start cloudflared 2>&1');

            if ($result->successful()) {
                sleep(1);

                if ($this->isRunning()) {
                    return null;
                }

                $this->cleanup();

                return 'Service started but cloudflared is not responding.';
            }
        }

        // Direct launch as background process (dev/fallback)
        Process::run($this->shell(sprintf(
            'nohup cloudflared tunnel run --token %s > /tmp/cloudflared.log 2>&1 & echo $!',
            escapeshellarg($token),
        )));

        // Wait for it to become responsive
        for ($i = 0; $i < 10; $i++) {
            usleep(500_000);

            if ($this->isRunning()) {
                return null;
            }
        }

        $logResult = Process::run('tail -5 /tmp/cloudflared.log 2>/dev/null');
        $logTail = trim($logResult->output());

        $this->cleanup();

        return 'Failed to start cloudflared.' . ($logTail ? "\n" . $logTail : '');
    }

    /**
     * Stop cloudflared. Returns null on success, or an error message on failure.
     */
    public function stop(): ?string
    {
        if (! $this->isRunning()) {
            return null;
        }

        // Token-file mode: truncate the token file to signal the entrypoint to stop
        if ($this->tokenFilePath !== null) {
            file_put_contents($this->tokenFilePath, '');

            return null;
        }

        // Try systemd first (Linux production)
        if ($this->hasSystemd()) {
            Process::run('sudo systemctl stop cloudflared 2>/dev/null');
        }

        // Graceful kill via PIDs (works on macOS and Linux)
        $pids = $this->findCloudflaredPids();

        if ($pids !== []) {
            Process::run('kill ' . implode(' ', $pids) . ' 2>/dev/null');
        }

        // Wait for shutdown
        for ($i = 0; $i < 6; $i++) {
            usleep(500_000);

            if (! $this->isRunning()) {
                return null;
            }
        }

        // Force kill survivors
        $survivors = $this->findCloudflaredPids();

        if ($survivors !== []) {
            Process::run('kill -9 ' . implode(' ', $survivors) . ' 2>/dev/null');
            usleep(500_000);
        }

        if ($this->isRunning()) {
            $this->cleanup();

            return 'Failed to stop cloudflared.';
        }

        return null;
    }

    /**
     * Update the cloudflared ingress rules in the config file.
     *
     * @param  array<string, int>  $routes  Map of subdomain paths to local ports
     */
    public function updateIngress(string $subdomain, array $routes): void
    {
        $hostname = "{$subdomain}." . config('vibecodepc.cloud_domain');
        $ingress = [];

        foreach ($routes as $path => $port) {
            $ingress[] = [
                'hostname' => $hostname,
                'path' => "/{$path}(/.*)?$",
                'service' => "http://localhost:{$port}",
            ];
        }

        // Default route: device app on main URL
        $ingress[] = [
            'hostname' => $hostname,
            'service' => "http://localhost:{$this->deviceAppPort}",
        ];

        // Catch-all rule (required by cloudflared)
        $ingress[] = ['service' => 'http_status:404'];

        $dir = dirname($this->configPath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->configPath,
            Yaml::dump(['ingress' => $ingress], 3, 2),
        );
    }

    /**
     * Force-cleanup all tunnel artifacts: kill processes, remove token/env files,
     * and mark the TunnelConfig as errored. Works on both macOS and Linux.
     */
    public function cleanup(): void
    {
        $cleaned = [];

        // Token-file mode: truncate the token to signal the container to stop
        if ($this->tokenFilePath !== null) {
            if (file_exists($this->tokenFilePath)) {
                file_put_contents($this->tokenFilePath, '');
                $cleaned[] = 'token file truncated';
            }
        } else {
            // Try systemd stop first (Linux production only)
            if ($this->hasSystemd()) {
                Process::run('sudo systemctl stop cloudflared 2>/dev/null');
                $cleaned[] = 'systemctl stop attempted';
            }

            // Kill all cloudflared processes with escalating force
            $pids = $this->findCloudflaredPids();

            if ($pids !== []) {
                $pidList = implode(' ', $pids);

                // Graceful SIGTERM first
                Process::run("kill {$pidList} 2>/dev/null");
                usleep(1_000_000);

                // Check survivors and SIGKILL them
                $survivors = $this->findCloudflaredPids();

                if ($survivors !== []) {
                    Process::run('kill -9 ' . implode(' ', $survivors) . ' 2>/dev/null');
                    usleep(500_000);
                }

                $cleaned[] = 'killed PIDs: ' . $pidList;
            }

            // Remove stale env file
            $envFile = dirname($this->configPath) . '/tunnel.env';

            if (file_exists($envFile)) {
                @unlink($envFile);
                $cleaned[] = 'env file removed';
            }

            // Remove stale log file
            if (file_exists('/tmp/cloudflared.log')) {
                @unlink('/tmp/cloudflared.log');
                $cleaned[] = 'log file removed';
            }
        }

        // Mark tunnel config as errored so the UI reflects the broken state
        $config = TunnelConfig::current();

        if ($config && $config->status !== 'error') {
            $config->update(['status' => 'error']);
            $cleaned[] = 'config marked as error';
        }

        Log::warning('Tunnel cleanup executed', ['actions' => $cleaned]);
    }

    /**
     * Find all cloudflared process IDs. Works on macOS and Linux.
     *
     * @return list<int>
     */
    private function findCloudflaredPids(): array
    {
        // pgrep works on both macOS and Linux; -f matches the full command line
        $result = Process::run('pgrep -f cloudflared 2>/dev/null');

        if (! $result->successful() || trim($result->output()) === '') {
            return [];
        }

        $myPid = getmypid();

        return collect(explode("\n", trim($result->output())))
            ->map(fn (string $line) => (int) trim($line))
            ->filter(fn (int $pid) => $pid > 0 && $pid !== $myPid)
            ->values()
            ->all();
    }

    /**
     * Check if systemd is available (Linux only).
     */
    private function hasSystemd(): bool
    {
        return Process::run('command -v systemctl 2>/dev/null')->successful();
    }

    /**
     * Wrap a command in a login shell so binaries like cloudflared are found in PATH.
     */
    private function shell(string $command): string
    {
        return sprintf('bash -lc %s', escapeshellarg($command));
    }
}
