<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TunnelException extends Exception
{
    public function __construct(
        string $message = 'Tunnel operation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
