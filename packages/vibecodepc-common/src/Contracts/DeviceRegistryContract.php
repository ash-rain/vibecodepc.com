<?php

declare(strict_types=1);

namespace VibecodePC\Common\Contracts;

use VibecodePC\Common\DTOs\DeviceInfo;
use VibecodePC\Common\Enums\DeviceStatus;

interface DeviceRegistryContract
{
    public function getDeviceInfo(): DeviceInfo;

    public function getDeviceStatus(string $deviceId): DeviceStatus;

    public function claimDevice(string $deviceId, int $userId): bool;
}
