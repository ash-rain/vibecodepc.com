<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

interface AiProviderContract
{
    public function validate(string $apiKey): AiValidationResult;

    public function getProviderName(): string;

    public function getApiKeyUrl(): string;

    public function getPlaceholder(): string;
}
