<?php

declare(strict_types=1);

namespace VibecodePC\Common\Contracts;

use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\DTOs\DeviceStatusResult;
use VibecodePC\Common\DTOs\PairingResult;

interface DeviceRegistryContract
{
    public function getDeviceInfo(): DeviceInfo;

    public function getDeviceStatus(string $deviceId): DeviceStatusResult;

    public function claimDevice(string $deviceId, int $userId): PairingResult;

    public function registerDevice(DeviceInfo $deviceInfo): void;
}
