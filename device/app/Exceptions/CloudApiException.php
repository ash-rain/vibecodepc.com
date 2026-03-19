<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CloudApiException extends Exception
{
    public function __construct(
        string $message = 'Cloud API request failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
