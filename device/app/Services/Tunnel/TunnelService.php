<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\TunnelConfig;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class TunnelService
{
    private const CREDENTIALS_PATH = '/etc/cloudflared/credentials.json';

    private const PLACEHOLDER_TUNNEL_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private readonly string $configPath = '/etc/cloudflared/config.yml',
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
     * Check if real tunnel credentials have been provisioned via the wizard.
     */
    public function hasCredentials(): bool
    {
        $config = TunnelConfig::current();

        return $config !== null
            && ! empty($config->tunnel_id)
            && $config->tunnel_id !== self::PLACEHOLDER_TUNNEL_ID;
    }

    public function createTunnel(string $subdomain, string $tunnelToken): bool
    {
        $tunnelConfig = TunnelConfig::current();
        $tunnelId = $tunnelConfig?->tunnel_id ?? self::PLACEHOLDER_TUNNEL_ID;

        $config = implode("\n", [
            "tunnel: {$tunnelId}",
            'credentials-file: '.self::CREDENTIALS_PATH,
            '',
            'ingress:',
            "  - hostname: {$subdomain}.vibecodepc.com",
            '    service: http://localhost:80',
            '  - service: http_status:404',
        ]);

        $dir = dirname($this->configPath);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return File::put($this->configPath, $config) !== false;
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
     * Start cloudflared. Returns null on success, or an error message on failure.
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

        // Try systemd first (production RPi), then direct launch (dev/fallback)
        $result = Process::run('sudo systemctl start cloudflared 2>&1');

        if ($result->successful()) {
            sleep(1);

            return $this->isRunning() ? null : 'Service started but cloudflared is not responding.';
        }

        // Direct launch as background process
        Process::run($this->shell(sprintf(
            'nohup cloudflared tunnel --config %s run > /tmp/cloudflared.log 2>&1 & echo $!',
            escapeshellarg($this->configPath),
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
     * Update the cloudflared ingress rules to include per-project routes.
     *
     * @param  string  $subdomain  The device's tunnel subdomain
     * @param  array<string, int>  $projectRoutes  Map of path prefix => local port
     */
    public function updateIngress(string $subdomain, array $projectRoutes): bool
    {
        $tunnelConfig = TunnelConfig::current();
        $tunnelId = $tunnelConfig?->tunnel_id ?? self::PLACEHOLDER_TUNNEL_ID;

        $ingressRules = [];

        foreach ($projectRoutes as $path => $port) {
            $ingressRules[] = "  - hostname: {$subdomain}.vibecodepc.com\n    path: /{$path}/*\n    service: http://localhost:{$port}";
        }

        // Default route for the device dashboard
        $ingressRules[] = "  - hostname: {$subdomain}.vibecodepc.com\n    service: http://localhost:80";
        $ingressRules[] = '  - service: http_status:404';

        $config = implode("\n", [
            "tunnel: {$tunnelId}",
            'credentials-file: '.self::CREDENTIALS_PATH,
            '',
            'ingress:',
            implode("\n", $ingressRules),
        ]);

        $dir = dirname($this->configPath);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $written = File::put($this->configPath, $config) !== false;

        if ($written) {
            Process::run('sudo systemctl reload cloudflared 2>/dev/null || true');
        }

        return $written;
    }

    /**
     * Wrap a command in a login shell so binaries like cloudflared are found in PATH.
     */
    private function shell(string $command): string
    {
        return sprintf('bash -lc %s', escapeshellarg($command));
    }
}
