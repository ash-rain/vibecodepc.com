<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceState;
use App\Models\TunnelConfig;
use Illuminate\Support\Facades\Log;

class ConfigSyncService
{
    public function __construct(
        private readonly CloudApiClient $cloudApi,
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

        if (isset($remoteConfig['subdomain'])) {
            $tunnelConfig = TunnelConfig::current();

            if ($tunnelConfig && $tunnelConfig->subdomain !== $remoteConfig['subdomain']) {
                $tunnelConfig->update(['subdomain' => $remoteConfig['subdomain']]);
                Log::info("Config sync: subdomain updated to {$remoteConfig['subdomain']}");
            }
        }

        DeviceState::setValue('config_version', (string) $remoteVersion);
    }
}
