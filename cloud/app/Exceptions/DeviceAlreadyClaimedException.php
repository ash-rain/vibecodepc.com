<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DeviceAlreadyClaimedException extends RuntimeException
{
    public static function withUuid(string $uuid): self
    {
        return new self("Device already claimed: {$uuid}");
    }
}
