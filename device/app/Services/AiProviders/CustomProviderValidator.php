<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class CustomProviderValidator implements AiProviderContract
{
    public function __construct(
        private readonly ?string $baseUrl = null,
    ) {}

    public function validate(string $apiKey): AiValidationResult
    {
        if (! $this->baseUrl) {
            return AiValidationResult::failure('Base URL is required for custom providers.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get(rtrim($this->baseUrl, '/').'/v1/models');

            if ($response->successful()) {
                return AiValidationResult::success('Connected to custom provider.');
            }

            return AiValidationResult::failure('Could not authenticate with the custom provider.');
        } catch (\Exception $e) {
            return AiValidationResult::failure('Could not connect: '.$e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return 'Custom Provider';
    }

    public function getApiKeyUrl(): string
    {
        return '';
    }

    public function getPlaceholder(): string
    {
        return 'your-api-key';
    }
}
