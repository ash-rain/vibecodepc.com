<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum AiProvider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case OpenRouter = 'openrouter';
    case HuggingFace = 'huggingface';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::OpenRouter => 'OpenRouter',
            self::HuggingFace => 'Hugging Face',
            self::Custom => 'Custom Provider',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OpenAI => 'GPT-4, GPT-3.5, DALL-E',
            self::Anthropic => 'Claude 4, Claude 3.5',
            self::OpenRouter => 'Access 100+ models',
            self::HuggingFace => 'Open-source models',
            self::Custom => 'OpenAI-compatible API',
        };
    }

    public function apiKeyUrl(): string
    {
        return match ($this) {
            self::OpenAI => 'https://platform.openai.com/api-keys',
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::OpenRouter => 'https://openrouter.ai/keys',
            self::HuggingFace => 'https://huggingface.co/settings/tokens',
            self::Custom => '',
        };
    }
}
