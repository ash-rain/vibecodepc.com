<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DeviceStatusException extends Exception
{
    public function __construct(
        string $message = 'Failed to retrieve device status',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
