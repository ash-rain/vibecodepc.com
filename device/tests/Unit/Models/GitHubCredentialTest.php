<?php

declare(strict_types=1);

use App\Models\GitHubCredential;
use Illuminate\Support\Facades\DB;

it('getToken returns the encrypted access token', function () {
    $token = 'gho_test_token_12345';
    $credential = GitHubCredential::factory()->create([
        'access_token_encrypted' => $token,
    ]);

    expect($credential->getToken())->toBe($token);
});

it('getToken returns empty string when token is empty', function () {
    $credential = GitHubCredential::factory()->create([
        'access_token_encrypted' => '',
    ]);

    expect($credential->getToken())->toBe('');
});

it('current returns the latest credential', function () {
    GitHubCredential::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    $latest = GitHubCredential::factory()->create([
        'created_at' => now(),
    ]);

    GitHubCredential::factory()->create([
        'created_at' => now()->subDay(),
    ]);

    $result = GitHubCredential::current();

    expect($result->id)->toBe($latest->id);
});

it('current returns null when no credentials exist', function () {
    expect(GitHubCredential::current())->toBeNull();
});

it('hasCopilot returns true when has_copilot is true', function () {
    $credential = GitHubCredential::factory()->withCopilot()->create();

    expect($credential->hasCopilot())->toBeTrue();
});

it('hasCopilot returns false when has_copilot is false', function () {
    $credential = GitHubCredential::factory()->create([
        'has_copilot' => false,
    ]);

    expect($credential->hasCopilot())->toBeFalse();
});

it('has_copilot is cast to boolean', function () {
    $credential = GitHubCredential::factory()->create([
        'has_copilot' => 1,
    ]);

    expect($credential->has_copilot)->toBeTrue();

    $credential2 = GitHubCredential::factory()->create([
        'has_copilot' => 0,
    ]);

    expect($credential2->has_copilot)->toBeFalse();
});

it('token_expires_at is cast to datetime', function () {
    $credential = GitHubCredential::factory()->create([
        'token_expires_at' => now()->addHour(),
    ]);

    expect($credential->token_expires_at)->toBeInstanceOf(DateTime::class);
});

it('access_token_encrypted is encrypted', function () {
    $rawToken = 'gho_raw_token_12345';
    $credential = GitHubCredential::factory()->create([
        'access_token_encrypted' => $rawToken,
    ]);

    $freshCredential = GitHubCredential::find($credential->id);
    $databaseValue = DB::table('github_credentials')->where('id', $credential->id)->value('access_token_encrypted');

    expect($freshCredential->getToken())->toBe($rawToken)
        ->and($databaseValue)->not->toBe($rawToken);
});

it('hasCopilot works after model refresh', function () {
    $credential = GitHubCredential::factory()->create([
        'has_copilot' => false,
    ]);

    expect($credential->hasCopilot())->toBeFalse();

    $credential->update(['has_copilot' => true]);

    expect($credential->fresh()->hasCopilot())->toBeTrue();
});

it('can create credential with all fields populated', function () {
    $credential = GitHubCredential::factory()->create([
        'github_username' => 'testuser',
        'github_email' => 'test@example.com',
        'github_name' => 'Test User',
        'has_copilot' => true,
        'token_expires_at' => now()->addDay(),
    ]);

    expect($credential->github_username)->toBe('testuser')
        ->and($credential->github_email)->toBe('test@example.com')
        ->and($credential->github_name)->toBe('Test User')
        ->and($credential->has_copilot)->toBeTrue()
        ->and($credential->token_expires_at)->toBeInstanceOf(DateTime::class);
});

it('current returns most recent even when only one credential exists', function () {
    $credential = GitHubCredential::factory()->create();

    $result = GitHubCredential::current();

    expect($result->id)->toBe($credential->id);
});

it('handles multiple credentials with copilot status', function () {
    GitHubCredential::factory()->withCopilot()->count(2)->create();
    GitHubCredential::factory()->count(3)->create(['has_copilot' => false]);

    expect(GitHubCredential::where('has_copilot', true)->count())->toBe(2)
        ->and(GitHubCredential::where('has_copilot', false)->count())->toBe(3);
});

it('getToken returns string type', function () {
    $credential = GitHubCredential::factory()->create();

    expect($credential->getToken())->toBeString();
});

it('current scope orders by created_at descending', function () {
    $oldest = GitHubCredential::factory()->create(['created_at' => now()->subDays(3)]);
    $middle = GitHubCredential::factory()->create(['created_at' => now()->subDays(1)]);
    $newest = GitHubCredential::factory()->create(['created_at' => now()]);

    $result = GitHubCredential::current();

    expect($result->id)->toBe($newest->id);
});

it('nullable fields can be null', function () {
    $credential = GitHubCredential::factory()->create([
        'github_email' => null,
        'github_name' => null,
        'token_expires_at' => fn () => null,
    ]);

    expect($credential->github_email)->toBeNull()
        ->and($credential->github_name)->toBeNull()
        ->and($credential->token_expires_at)->toBeNull();
});

it('factory withCopilot state creates credential with copilot enabled', function () {
    $credential = GitHubCredential::factory()->withCopilot()->create();

    expect($credential->has_copilot)->toBeTrue()
        ->and($credential->hasCopilot())->toBeTrue();
});

it('factory default state creates credential without copilot', function () {
    $credential = GitHubCredential::factory()->create();

    expect($credential->has_copilot)->toBeFalse()
        ->and($credential->hasCopilot())->toBeFalse();
});
