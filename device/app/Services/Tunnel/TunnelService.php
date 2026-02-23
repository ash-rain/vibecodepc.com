<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TunnelService
{
    public function __construct(
        private readonly int $deviceAppPort = 8001,
        private readonly string $tokenFilePath = '/tunnel/token',
    ) {}

    public function isInstalled(): bool
    {
        return true;
    }

    public function isRunning(): bool
    {
        return file_exists($this->tokenFilePath) && filesize($this->tokenFilePath) > 0;
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
        $url = sprintf('https://%s.%s', $subdomain, config('vibecodepc.cloud_domain'));

        $result = Process::timeout(15)->run([
            'curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', $url,
        ]);

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
     * Start cloudflared by writing the tunnel token to the shared volume.
     * The cloudflared container picks it up automatically via its entrypoint.
     * If already running with a different token (e.g. after re-provisioning),
     * the entrypoint detects the change and restarts cloudflared.
     * Returns null on success, or an error message on failure.
     */
    public function start(): ?string
    {
        if (! $this->hasCredentials()) {
            return 'Tunnel is not configured. Complete the setup wizard to provision tunnel credentials.';
        }

        $token = TunnelConfig::current()->tunnel_token_encrypted;

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

    /**
     * Stop cloudflared by truncating the token file.
     * The container entrypoint detects this and gracefully shuts down.
     * Returns null on success, or an error message on failure.
     */
    public function stop(): ?string
    {
        if (! $this->isRunning()) {
            return null;
        }

        file_put_contents($this->tokenFilePath, '');

        return null;
    }

    /**
     * Push updated ingress rules to the remote tunnel configuration via the Cloud API.
     *
     * Since the tunnel uses config_src "cloudflare" (remote-managed), ingress rules
     * must be updated via the Cloudflare API, not a local config file.
     *
     * @param  array<string, int>  $routes  Map of subdomain paths to local ports
     */
    public function updateIngress(array $routes): void
    {
        $ingress = [];

        foreach ($routes as $path => $port) {
            $ingress[] = [
                'path' => "/{$path}(/.*)?$",
                'service' => "http://localhost:{$port}",
            ];
        }

        // Default route: device app on main URL
        $ingress[] = [
            'service' => "http://localhost:{$this->deviceAppPort}",
        ];

        $cloudApi = app(CloudApiClient::class);
        $identity = app(DeviceIdentityService::class);

        if (! $identity->hasIdentity()) {
            Log::warning('Cannot update remote ingress: device identity not configured');

            return;
        }

        $cloudApi->reconfigureTunnelIngress($identity->getDeviceInfo()->id, $ingress);
    }

    /**
     * Force-cleanup: truncate the token file to signal the container to stop,
     * and mark the TunnelConfig as errored so the UI reflects the broken state.
     */
    public function cleanup(): void
    {
        $cleaned = [];

        if (file_exists($this->tokenFilePath)) {
            file_put_contents($this->tokenFilePath, '');
            $cleaned[] = 'token file truncated';
        }

        // Mark tunnel config as errored so the UI reflects the broken state
        $config = TunnelConfig::current();

        if ($config && $config->status !== 'error') {
            $config->update(['status' => 'error']);
            $cleaned[] = 'config marked as error';
        }

        Log::warning('Tunnel cleanup executed', ['actions' => $cleaned]);
    }
}
