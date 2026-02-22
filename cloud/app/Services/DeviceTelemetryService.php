<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceHeartbeat;

class DeviceTelemetryService
{
    private const int ONLINE_THRESHOLD_MINUTES = 5;

    /**
     * @param array{
     *     cpu_percent?: float,
     *     cpu_temp?: float,
     *     ram_used_mb?: int,
     *     ram_total_mb?: int,
     *     disk_used_gb?: float,
     *     disk_total_gb?: float,
     *     running_projects?: int,
     *     tunnel_active?: bool,
     *     firmware_version?: string,
     *     os_version?: string,
     * } $metrics
     */
    public function processHeartbeat(Device $device, array $metrics): DeviceHeartbeat
    {
        $heartbeat = $device->heartbeats()->create($metrics);

        $device->update([
            'last_heartbeat_at' => now(),
            'is_online' => true,
            'cpu_percent' => $metrics['cpu_percent'] ?? null,
            'cpu_temp' => $metrics['cpu_temp'] ?? null,
            'ram_used_mb' => $metrics['ram_used_mb'] ?? null,
            'ram_total_mb' => $metrics['ram_total_mb'] ?? null,
            'disk_used_gb' => $metrics['disk_used_gb'] ?? null,
            'disk_total_gb' => $metrics['disk_total_gb'] ?? null,
            'os_version' => $metrics['os_version'] ?? $device->os_version,
            'firmware_version' => $metrics['firmware_version'] ?? $device->firmware_version,
        ]);

        return $heartbeat;
    }

    public function markStaleDevicesOffline(): int
    {
        return Device::query()
            ->where('is_online', true)
            ->where('last_heartbeat_at', '<', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES))
            ->update(['is_online' => false]);
    }

    public function isDeviceOnline(Device $device): bool
    {
        if (! $device->last_heartbeat_at) {
            return false;
        }

        return $device->last_heartbeat_at->greaterThan(
            now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES)
        );
    }

    public function pruneOldHeartbeats(int $keepDays = 30): int
    {
        return DeviceHeartbeat::query()
            ->where('created_at', '<', now()->subDays($keepDays))
            ->delete();
    }
}
