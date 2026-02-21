<?php

declare(strict_types=1);

use App\Services\AiProviders\AnthropicValidator;
use Illuminate\Support\Facades\Http;

it('validates a successful anthropic api key', function () {
    Http::fake([
        'api.anthropic.com/v1/models' => Http::response(['data' => []], 200),
    ]);

    $validator = new AnthropicValidator;
    $result = $validator->validate('sk-ant-test-key');

    expect($result->valid)->toBeTrue();
});

it('rejects an invalid anthropic api key', function () {
    Http::fake([
        'api.anthropic.com/v1/models' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $validator = new AnthropicValidator;
    $result = $validator->validate('sk-ant-invalid');

    expect($result->valid)->toBeFalse();
});

it('returns correct provider metadata', function () {
    $validator = new AnthropicValidator;

    expect($validator->getProviderName())->toBe('Anthropic')
        ->and($validator->getApiKeyUrl())->toBe('https://console.anthropic.com/settings/keys')
        ->and($validator->getPlaceholder())->toBe('sk-ant-...');
});
