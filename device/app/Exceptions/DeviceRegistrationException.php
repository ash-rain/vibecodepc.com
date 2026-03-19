<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class DeviceRegistrationException extends Exception
{
    public function __construct(
        string $message = 'Device registration failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
