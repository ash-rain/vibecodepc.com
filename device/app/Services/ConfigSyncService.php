<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceState;
use App\Models\TunnelConfig;
use App\Services\Tunnel\TunnelService;
use Illuminate\Support\Facades\Log;

class ConfigSyncService
{
    public function __construct(
        private readonly CloudApiClient $cloudApi,
        private readonly TunnelService $tunnelService,
    ) {}

    public function syncIfNeeded(string $deviceId): void
    {
        $remoteConfig = $this->cloudApi->getDeviceConfig($deviceId);

        if ($remoteConfig === null) {
            return;
        }

        $remoteVersion = $remoteConfig['config_version'] ?? 0;
        $localVersion = (int) DeviceState::getValue('config_version', '0');

        if ($remoteVersion <= $localVersion) {
            return;
        }

        Log::info("Config sync: remote version {$remoteVersion} > local {$localVersion}, applying changes");

        $tunnelConfig = TunnelConfig::current();

        if (isset($remoteConfig['subdomain']) && $tunnelConfig) {
            if ($tunnelConfig->subdomain !== $remoteConfig['subdomain']) {
                $tunnelConfig->update(['subdomain' => $remoteConfig['subdomain']]);
                Log::info("Config sync: subdomain updated to {$remoteConfig['subdomain']}");
            }
        }

        // The cloud may deliver a fresh tunnel token after re-provisioning
        // a broken tunnel. Apply it and restart cloudflared to reconnect.
        if (isset($remoteConfig['tunnel_token']) && $tunnelConfig) {
            $tunnelConfig->update([
                'tunnel_token_encrypted' => $remoteConfig['tunnel_token'],
                'status' => 'active',
            ]);

            Log::info('Config sync: new tunnel token received, restarting cloudflared');

            $this->tunnelService->stop();
            $error = $this->tunnelService->start();

            if ($error) {
                Log::error("Config sync: failed to restart tunnel after token update: {$error}");
            }
        }

        DeviceState::setValue('config_version', (string) $remoteVersion);
    }
}
