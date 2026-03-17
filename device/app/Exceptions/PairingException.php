<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class PairingException extends Exception
{
    public function __construct(
        string $message = 'Device pairing operation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
