<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DeviceNotFoundException extends RuntimeException
{
    public static function withUuid(string $uuid): self
    {
        return new self("Device not found: {$uuid}");
    }
}
