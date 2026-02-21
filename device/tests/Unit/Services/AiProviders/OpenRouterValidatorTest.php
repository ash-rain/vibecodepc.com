<?php

declare(strict_types=1);

use App\Services\AiProviders\OpenRouterValidator;
use Illuminate\Support\Facades\Http;

it('validates a successful openrouter api key', function () {
    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
    ]);

    $validator = new OpenRouterValidator;
    $result = $validator->validate('sk-or-test-key');

    expect($result->valid)->toBeTrue();
});

it('rejects an invalid openrouter api key', function () {
    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([], 401),
    ]);

    $validator = new OpenRouterValidator;
    $result = $validator->validate('sk-or-invalid');

    expect($result->valid)->toBeFalse();
});

it('returns correct provider metadata', function () {
    $validator = new OpenRouterValidator;

    expect($validator->getProviderName())->toBe('OpenRouter')
        ->and($validator->getApiKeyUrl())->toBe('https://openrouter.ai/keys');
});
