<?php

declare(strict_types=1);

use App\Services\AiProviders\OpenAiValidator;
use Illuminate\Support\Facades\Http;

it('validates a successful openai api key', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4'],
                ['id' => 'gpt-3.5-turbo'],
            ],
        ]),
    ]);

    $validator = new OpenAiValidator;
    $result = $validator->validate('sk-test-key');

    expect($result->valid)->toBeTrue()
        ->and($result->metadata['model_count'])->toBe(2);
});

it('rejects an invalid openai api key', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $validator = new OpenAiValidator;
    $result = $validator->validate('sk-invalid');

    expect($result->valid)->toBeFalse();
});

it('handles connection errors gracefully', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(null, 500),
    ]);

    $validator = new OpenAiValidator;
    $result = $validator->validate('sk-test');

    expect($result->valid)->toBeFalse();
});

it('returns correct provider metadata', function () {
    $validator = new OpenAiValidator;

    expect($validator->getProviderName())->toBe('OpenAI')
        ->and($validator->getApiKeyUrl())->toBe('https://platform.openai.com/api-keys')
        ->and($validator->getPlaceholder())->toBe('sk-...');
});
