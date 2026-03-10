<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Models\TunnelConfig;
use App\Services\CloudApiClient;
use App\Services\DeviceRegistry\DeviceIdentityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Temporary: pairing is optional. Full remote access requires pairing.
 */
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
     * Also returns true if the tunnel step was explicitly skipped by the user.
     */
    public function hasCredentials(): bool
    {
        $config = TunnelConfig::current();

        if ($config === null) {
            return false;
        }

        // User explicitly skipped tunnel setup - consider it "configured" for wizard purposes
        if ($config->isSkipped()) {
            return true;
        }

        return ! empty($config->tunnel_token_encrypted);
    }

    /**
     * Check if the tunnel setup was explicitly skipped by the user.
     */
    public function isSkipped(): bool
    {
        $config = TunnelConfig::current();

        return $config !== null && $config->isSkipped();
    }

    /**
     * Check if tunnel was skipped but token file now exists (auto-detected).
     */
    public function wasSkippedButNowAvailable(): bool
    {
        $config = TunnelConfig::current();

        return $config !== null && $config->isAvailableAfterSkip();
    }

    /**
     * Poll the tunnel status and update config if token file appears after skip.
     *
     * @return array{detected: bool, message: string|null, error: string|null}
     */
    public function pollStatus(): array
    {
        $config = TunnelConfig::current();

        // Only check if tunnel was skipped but not yet verified
        if (! $config || ! $config->isSkipped()) {
            return ['detected' => false, 'message' => null, 'error' => null];
        }

        // Check if tunnel token file now exists (provisioned externally)
        if (! $this->isRunning()) {
            return ['detected' => false, 'message' => null, 'error' => null];
        }

        // Tunnel token appeared! Update the config
        try {
            $config->markAsAvailable();
            Log::info('Tunnel status auto-detected: token file appeared, tunnel marked as available');

            return ['detected' => true, 'message' => 'Tunnel is now available and marked as active', 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Failed to update tunnel status on auto-detect', ['error' => $e->getMessage()]);

            return ['detected' => false, 'message' => null, 'error' => "Failed to update tunnel status: {$e->getMessage()}"];
        }
    }

    /**
     * Check if tunnel is effectively configured and ready to use.
     * Returns true if credentials exist OR if tunnel was skipped but token is now available.
     */
    public function isEffectivelyConfigured(): bool
    {
        return $this->hasCredentials() || $this->wasSkippedButNowAvailable();
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
     * Check if there's sufficient disk space for token file operations.
     *
     * @param  int  $requiredBytes  The minimum required space in bytes
     * @return bool True if there's sufficient space, false otherwise
     */
    protected function hasSufficientDiskSpace(int $requiredBytes = 1024): bool
    {
        $dir = dirname($this->tokenFilePath);
        $freeSpace = disk_free_space($dir);

        return $freeSpace !== false && $freeSpace >= $requiredBytes;
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

        if ($this->isSkipped()) {
            return 'Tunnel setup was skipped. Complete tunnel setup to enable remote access.';
        }

        $token = TunnelConfig::current()->tunnel_token_encrypted;

        $dir = dirname($this->tokenFilePath);

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (! is_writable($dir)) {
            return "Tunnel token directory is not writable: {$dir}";
        }

        if (! $this->hasSufficientDiskSpace(strlen($token) + 1024)) {
            Log::error('Insufficient disk space for tunnel token file', [
                'path' => $this->tokenFilePath,
                'required' => strlen($token) + 1024,
                'available' => disk_free_space($dir),
            ]);

            return 'Failed to write tunnel token file: insufficient disk space';
        }

        $result = file_put_contents($this->tokenFilePath, $token);

        if ($result === false) {
            Log::error('Failed to write tunnel token file', [
                'path' => $this->tokenFilePath,
                'error' => error_get_last()['message'] ?? 'Unknown error',
            ]);

            return "Failed to write tunnel token file: {$this->tokenFilePath}";
        }

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

        if (! $this->hasSufficientDiskSpace(1)) {
            Log::error('Insufficient disk space for tunnel token file truncation', [
                'path' => $this->tokenFilePath,
                'available' => @disk_free_space(dirname($this->tokenFilePath)),
            ]);

            return 'Failed to truncate tunnel token file: insufficient disk space';
        }

        $result = file_put_contents($this->tokenFilePath, '');

        if ($result === false) {
            Log::error('Failed to truncate tunnel token file', [
                'path' => $this->tokenFilePath,
                'error' => error_get_last()['message'] ?? 'Unknown error',
            ]);

            return "Failed to truncate tunnel token file: {$this->tokenFilePath}";
        }

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
        if ($this->isSkipped()) {
            Log::info('Skipping ingress update: tunnel setup was skipped');

            return;
        }

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
            if (! $this->hasSufficientDiskSpace(1)) {
                Log::error('Insufficient disk space for tunnel token file cleanup', [
                    'path' => $this->tokenFilePath,
                    'available' => @disk_free_space(dirname($this->tokenFilePath)),
                ]);
            } else {
                $result = file_put_contents($this->tokenFilePath, '');

                if ($result === false) {
                    Log::error('Failed to truncate tunnel token file during cleanup', [
                        'path' => $this->tokenFilePath,
                        'error' => error_get_last()['message'] ?? 'Unknown error',
                    ]);
                } else {
                    $cleaned[] = 'token file truncated';
                }
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
}
