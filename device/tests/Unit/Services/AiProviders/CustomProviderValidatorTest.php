<?php

declare(strict_types=1);

use App\Services\AiProviders\CustomProviderValidator;
use Illuminate\Support\Facades\Http;

it('validates a successful custom provider api key', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response(['data' => []], 200),
    ]);

    $validator = new CustomProviderValidator('https://api.example.com');
    $result = $validator->validate('test-key');

    expect($result->valid)->toBeTrue();
});

it('rejects an invalid custom provider api key', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response([], 401),
    ]);

    $validator = new CustomProviderValidator('https://api.example.com');
    $result = $validator->validate('invalid');

    expect($result->valid)->toBeFalse();
});

it('fails when no base url is provided', function () {
    $validator = new CustomProviderValidator;
    $result = $validator->validate('test-key');

    expect($result->valid)->toBeFalse()
        ->and($result->message)->toContain('Base URL');
});
