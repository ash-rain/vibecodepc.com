<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class AnthropicValidator implements AiProviderContract
{
    public function validate(string $apiKey): AiValidationResult
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(10)
                ->get('https://api.anthropic.com/v1/models');

            if ($response->successful()) {
                return AiValidationResult::success('Connected to Anthropic.');
            }

            return AiValidationResult::failure('Invalid API key. Check your Anthropic console.');
        } catch (\Exception $e) {
            return AiValidationResult::failure('Could not connect to Anthropic: '.$e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return 'Anthropic';
    }

    public function getApiKeyUrl(): string
    {
        return 'https://console.anthropic.com/settings/keys';
    }

    public function getPlaceholder(): string
    {
        return 'sk-ant-...';
    }
}
