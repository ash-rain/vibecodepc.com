<?php

declare(strict_types=1);

use App\Models\CloudCredential;

it('returns false when is_paired is false', function () {
    $credential = CloudCredential::factory()->create([
        'is_paired' => false,
    ]);

    expect($credential->isPaired())->toBeFalse();
});

it('returns true when is_paired is true', function () {
    $credential = CloudCredential::factory()->paired()->create();

    expect($credential->isPaired())->toBeTrue();
});

it('returns false for unpaired factory state', function () {
    $credential = CloudCredential::factory()->unpaired()->create();

    expect($credential->isPaired())->toBeFalse();
});

it('isPaired returns boolean type', function () {
    $credential = CloudCredential::factory()->create();

    expect($credential->isPaired())->toBeBool();
});

it('current returns the latest credential', function () {
    CloudCredential::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $latest = CloudCredential::factory()->create([
        'created_at' => now(),
    ]);

    CloudCredential::factory()->create([
        'created_at' => now()->subDay(),
    ]);

    $result = CloudCredential::current();

    expect($result->id)->toBe($latest->id);
});

it('current returns null when no credentials exist', function () {
    expect(CloudCredential::current())->toBeNull();
});

it('getToken returns the pairing token', function () {
    $token = 'test-token-12345';
    $credential = CloudCredential::factory()->withToken($token)->create();

    expect($credential->getToken())->toBe($token);
});

it('getToken returns empty string when token is empty', function () {
    $credential = CloudCredential::factory()->create([
        'pairing_token_encrypted' => '',
    ]);

    expect($credential->getToken())->toBe('');
});

it('is_paired is cast to boolean', function () {
    $credential = CloudCredential::factory()->create([
        'is_paired' => 1,
    ]);

    expect($credential->is_paired)->toBeTrue();

    $credential2 = CloudCredential::factory()->create([
        'is_paired' => 0,
    ]);

    expect($credential2->is_paired)->toBeFalse();
});

it('paired_at is cast to datetime', function () {
    $credential = CloudCredential::factory()->paired()->create();

    expect($credential->paired_at)->toBeInstanceOf(DateTime::class);
});

it('isPaired works after model refresh', function () {
    $credential = CloudCredential::factory()->create([
        'is_paired' => false,
    ]);

    expect($credential->isPaired())->toBeFalse();

    $credential->update(['is_paired' => true]);

    expect($credential->fresh()->isPaired())->toBeTrue();
});

it('handles multiple paired credentials', function () {
    CloudCredential::factory()->paired()->count(3)->create();

    expect(CloudCredential::where('is_paired', true)->count())->toBe(3);
});

it('paired credential has all required fields', function () {
    $credential = CloudCredential::factory()->paired()->create();

    expect($credential->cloud_username)->not->toBeNull()
        ->and($credential->cloud_email)->not->toBeNull()
        ->and($credential->cloud_url)->not->toBeNull()
        ->and($credential->paired_at)->not->toBeNull()
        ->and($credential->is_paired)->toBeTrue();
});

it('can transition from unpaired to paired', function () {
    $credential = CloudCredential::factory()->unpaired()->create();

    expect($credential->isPaired())->toBeFalse();

    $credential->update([
        'is_paired' => true,
        'paired_at' => now(),
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
    ]);

    $refreshed = $credential->fresh();

    expect($refreshed->isPaired())->toBeTrue()
        ->and($refreshed->cloud_username)->toBe('testuser')
        ->and($refreshed->cloud_email)->toBe('test@example.com');
});
