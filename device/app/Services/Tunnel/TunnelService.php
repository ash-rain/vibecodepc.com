<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\TunnelConfig;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;

class TunnelService
{
    public function __construct(
        private readonly string $configPath = '/etc/cloudflared/config.yml',
        private readonly int $deviceAppPort = 8001,
    ) {}

    public function isInstalled(): bool
    {
        $result = Process::run($this->shell('cloudflared --version 2>/dev/null'));

        return $result->successful();
    }

    public function isRunning(): bool
    {
        $result = Process::run('pgrep -x cloudflared 2>/dev/null');

        return $result->successful();
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
            sprintf('curl -s -o /dev/null -w "%%{http_code}" https://%s.vibecodepc.com', escapeshellarg($subdomain)),
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

        // Try systemd first (production RPi) — write token to env file for the service
        $envDir = dirname($this->configPath);
        $envFile = $envDir.'/tunnel.env';
        @mkdir($envDir, 0755, true);
        file_put_contents($envFile, "TUNNEL_TOKEN={$token}\n");
        chmod($envFile, 0600);

        $result = Process::run('sudo systemctl start cloudflared 2>&1');

        if ($result->successful()) {
            sleep(1);

            return $this->isRunning() ? null : 'Service started but cloudflared is not responding.';
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

        return 'Failed to start cloudflared.'.($logTail ? "\n".$logTail : '');
    }

    /**
     * Stop cloudflared. Returns null on success, or an error message on failure.
     */
    public function stop(): ?string
    {
        if (! $this->isRunning()) {
            return null;
        }

        // Try systemd first (production RPi)
        $result = Process::run('sudo systemctl stop cloudflared 2>/dev/null');

        if (! $result->successful()) {
            Process::run('pkill -x cloudflared 2>/dev/null');
        }

        // Wait for shutdown
        for ($i = 0; $i < 6; $i++) {
            usleep(500_000);

            if (! $this->isRunning()) {
                return null;
            }
        }

        // Force kill
        Process::run('pkill -9 -x cloudflared 2>/dev/null');
        usleep(500_000);

        return $this->isRunning() ? 'Failed to stop cloudflared.' : null;
    }

    /**
     * Update the cloudflared ingress rules in the config file.
     *
     * @param  array<string, int>  $routes  Map of subdomain paths to local ports
     */
    public function updateIngress(string $subdomain, array $routes): void
    {
        $hostname = "{$subdomain}.vibecodepc.com";
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
     * Wrap a command in a login shell so binaries like cloudflared are found in PATH.
     */
    private function shell(string $command): string
    {
        return sprintf('bash -lc %s', escapeshellarg($command));
    }
}
