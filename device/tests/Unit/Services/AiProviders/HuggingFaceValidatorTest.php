<?php

declare(strict_types=1);

use App\Services\AiProviders\HuggingFaceValidator;
use Illuminate\Support\Facades\Http;

it('validates a successful huggingface token', function () {
    Http::fake([
        'huggingface.co/api/whoami-v2' => Http::response(['name' => 'testuser'], 200),
    ]);

    $validator = new HuggingFaceValidator;
    $result = $validator->validate('hf_test_token');

    expect($result->valid)->toBeTrue()
        ->and($result->metadata['username'])->toBe('testuser');
});

it('rejects an invalid huggingface token', function () {
    Http::fake([
        'huggingface.co/api/whoami-v2' => Http::response([], 401),
    ]);

    $validator = new HuggingFaceValidator;
    $result = $validator->validate('hf_invalid');

    expect($result->valid)->toBeFalse();
});

it('returns correct provider metadata', function () {
    $validator = new HuggingFaceValidator;

    expect($validator->getProviderName())->toBe('Hugging Face')
        ->and($validator->getApiKeyUrl())->toBe('https://huggingface.co/settings/tokens');
});
