<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;

class HuggingFaceValidator implements AiProviderContract
{
    public function validate(string $apiKey): AiValidationResult
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get('https://huggingface.co/api/whoami-v2');

            if ($response->successful()) {
                $user = $response->json('name', 'unknown');

                return AiValidationResult::success("Connected as {$user}.", [
                    'username' => $user,
                ]);
            }

            return AiValidationResult::failure('Invalid API token. Check your Hugging Face settings.');
        } catch (\Exception $e) {
            return AiValidationResult::failure('Could not connect to Hugging Face: '.$e->getMessage());
        }
    }

    public function getProviderName(): string
    {
        return 'Hugging Face';
    }

    public function getApiKeyUrl(): string
    {
        return 'https://huggingface.co/settings/tokens';
    }

    public function getPlaceholder(): string
    {
        return 'hf_...';
    }
}
