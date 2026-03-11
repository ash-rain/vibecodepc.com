<?php

declare(strict_types=1);

use App\Models\AiProviderConfig;
use VibecodePC\Common\Enums\AiProvider;

it('returns false when validated_at is null', function () {
    $config = AiProviderConfig::factory()->create([
        'validated_at' => null,
    ]);

    expect($config->isValidated())->toBeFalse();
});

it('returns true when validated_at is set', function () {
    $config = AiProviderConfig::factory()->validated()->create();

    expect($config->isValidated())->toBeTrue();
});

it('returns false when validated_at is explicitly set to null', function () {
    $config = AiProviderConfig::factory()->create([
        'validated_at' => now(),
    ]);

    $config->update(['validated_at' => null]);

    expect($config->fresh()->isValidated())->toBeFalse();
});

it('returns decrypted API key', function () {
    $apiKey = 'sk-test-1234567890abcdef';
    $config = AiProviderConfig::factory()->create([
        'api_key_encrypted' => $apiKey,
    ]);

    expect($config->getDecryptedKey())->toBe($apiKey);
});

it('returns different API keys for different configs', function () {
    $config1 = AiProviderConfig::factory()
        ->forProvider(AiProvider::OpenAI)
        ->create([
            'api_key_encrypted' => 'sk-key-one',
        ]);
    $config2 = AiProviderConfig::factory()
        ->forProvider(AiProvider::Anthropic)
        ->create([
            'api_key_encrypted' => 'sk-key-two',
        ]);

    expect($config1->getDecryptedKey())->toBe('sk-key-one')
        ->and($config2->getDecryptedKey())->toBe('sk-key-two');
});

it('handles empty string API key', function () {
    $config = AiProviderConfig::factory()->create([
        'api_key_encrypted' => '',
    ]);

    expect($config->getDecryptedKey())->toBe('');
});

it('handles special characters in API key', function () {
    $apiKey = 'sk-test+special/char=key!@#$%';
    $config = AiProviderConfig::factory()->create([
        'api_key_encrypted' => $apiKey,
    ]);

    expect($config->getDecryptedKey())->toBe($apiKey);
});

it('works with different AI providers', function () {
    $providers = AiProvider::cases();

    foreach ($providers as $provider) {
        $config = AiProviderConfig::factory()
            ->forProvider($provider)
            ->create();

        expect($config->provider)->toBe($provider);
    }
});

it('isValidated works after model refresh', function () {
    $config = AiProviderConfig::factory()->create([
        'validated_at' => null,
    ]);

    expect($config->isValidated())->toBeFalse();

    $config->update(['validated_at' => now()]);

    expect($config->fresh()->isValidated())->toBeTrue();
});

it('getDecryptedKey returns string type', function () {
    $config = AiProviderConfig::factory()->create([
        'api_key_encrypted' => 'sk-test-key',
    ]);

    expect($config->getDecryptedKey())->toBeString();
});

it('isValidated returns boolean type', function () {
    $config = AiProviderConfig::factory()->create();

    expect($config->isValidated())->toBeBool();
});
