<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class OpenRouterValidator implements AiProviderContract
{
    public function validate(string $apiKey): AiValidationResult
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get('https://openrouter.ai/api/v1/models');

            if ($response->successful()) {
                return AiValidationResult::success('Connected to OpenRouter.');
            }

            return AiValidationResult::failure('Invalid API key. Check your OpenRouter dashboard.');
        } catch (\Exception $e) {
            return AiValidationResult::failure('Could not connect to OpenRouter: '.$e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return 'OpenRouter';
    }

    public function getApiKeyUrl(): string
    {
        return 'https://openrouter.ai/keys';
    }

    public function getPlaceholder(): string
    {
        return 'sk-or-...';
    }
}
