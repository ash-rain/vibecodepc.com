<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class OpenAiValidator implements AiProviderContract
{
    public function validate(string $apiKey): AiValidationResult
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                $models = $response->json('data', []);

                return AiValidationResult::success('Connected to OpenAI.', [
                    'model_count' => count($models),
                ]);
            }

            return AiValidationResult::failure('Invalid API key. Check your OpenAI dashboard.');
        } catch (\Exception $e) {
            return AiValidationResult::failure('Could not connect to OpenAI: '.$e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }

    public function getApiKeyUrl(): string
    {
        return 'https://platform.openai.com/api-keys';
    }

    public function getPlaceholder(): string
    {
        return 'sk-...';
    }
}
