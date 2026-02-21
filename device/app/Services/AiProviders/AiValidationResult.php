<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

final readonly class AiValidationResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public bool $valid,
        public string $message,
        public array $metadata = [],
    ) {}

    public static function success(string $message = 'API key is valid.', array $metadata = []): self
    {
        return new self(true, $message, $metadata);
    }

    public static function failure(string $message = 'API key is invalid.'): self
    {
        return new self(false, $message);
    }
}
