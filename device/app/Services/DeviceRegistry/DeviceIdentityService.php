<?php

declare(strict_types=1);

namespace App\Services\DeviceRegistry;

use RuntimeException;
use VibecodePC\Common\DTOs\DeviceInfo;

class DeviceIdentityService
{
    private ?DeviceInfo $cached = null;

    public function __construct(
        private readonly string $deviceJsonPath,
    ) {}

    public function getDeviceInfo(): DeviceInfo
    {
        if ($this->cached) {
            return $this->cached;
        }

        if (! file_exists($this->deviceJsonPath)) {
            throw new RuntimeException(
                "Device identity file not found at {$this->deviceJsonPath}. Run: php artisan device:generate-id"
            );
        }

        $json = file_get_contents($this->deviceJsonPath);

        return $this->cached = DeviceInfo::fromJson($json);
    }

    public function hasIdentity(): bool
    {
        return file_exists($this->deviceJsonPath);
    }

    public function getPairingUrl(): string
    {
        $device = $this->getDeviceInfo();

        return config('vibecodepc.cloud_url').'/id/'.$device->id;
    }
}
