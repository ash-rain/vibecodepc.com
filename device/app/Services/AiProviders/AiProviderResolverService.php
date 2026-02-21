<?php

declare(strict_types=1);

namespace App\Services\AiProviders;

use VibecodePC\Common\Enums\AiProvider;

class AiProviderResolverService
{
    public function resolve(AiProvider $provider, ?string $baseUrl = null): AiProviderContract
    {
        return match ($provider) {
            AiProvider::OpenAI => new OpenAiValidator,
            AiProvider::Anthropic => new AnthropicValidator,
            AiProvider::OpenRouter => new OpenRouterValidator,
            AiProvider::HuggingFace => new HuggingFaceValidator,
            AiProvider::Custom => new CustomProviderValidator($baseUrl),
        };
    }
}
