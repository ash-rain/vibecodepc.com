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
}
