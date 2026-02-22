<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class TunnelService
{
    public function __construct(
        private readonly string $configPath = '/etc/cloudflared/config.yml',
    ) {}

    public function isInstalled(): bool
    {
        $result = Process::run('cloudflared --version 2>/dev/null');

        return $result->successful();
    }

    public function isRunning(): bool
    {
        $result = Process::run('pgrep -x cloudflared 2>/dev/null');

        return $result->successful();
    }

    public function createTunnel(string $subdomain, string $tunnelToken): bool
    {
        $config = implode("\n", [
            'tunnel: vibecodepc',
            'credentials-file: /etc/cloudflared/credentials.json',
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

    /** @return array{installed: bool, running: bool} */
    public function getStatus(): array
    {
        return [
            'installed' => $this->isInstalled(),
            'running' => $this->isRunning(),
        ];
    }

    public function start(): bool
    {
        $result = Process::run('sudo systemctl start cloudflared 2>/dev/null || cloudflared tunnel run vibecodepc &');

        return $result->successful();
    }

    public function stop(): bool
    {
        $result = Process::run('sudo systemctl stop cloudflared 2>/dev/null || pkill -x cloudflared');

        return $result->successful();
    }

    /**
     * Update the cloudflared ingress rules to include per-project routes.
     *
     * @param  string  $subdomain  The device's tunnel subdomain
     * @param  array<string, int>  $projectRoutes  Map of path prefix => local port
     */
    public function updateIngress(string $subdomain, array $projectRoutes): bool
    {
        $ingressRules = [];

        foreach ($projectRoutes as $path => $port) {
            $ingressRules[] = "  - hostname: {$subdomain}.vibecodepc.com\n    path: /{$path}/*\n    service: http://localhost:{$port}";
        }

        // Default route for the device dashboard
        $ingressRules[] = "  - hostname: {$subdomain}.vibecodepc.com\n    service: http://localhost:80";
        $ingressRules[] = '  - service: http_status:404';

        $config = implode("\n", [
            'tunnel: vibecodepc',
            'credentials-file: /etc/cloudflared/credentials.json',
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
}
