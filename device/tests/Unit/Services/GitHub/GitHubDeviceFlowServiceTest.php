<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubDeviceFlowService;
use App\Services\GitHub\GitHubProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('initiates device flow', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response([
            'device_code' => 'device-123',
            'user_code' => 'ABCD-1234',
            'verification_uri' => 'https://github.com/login/device',
            'expires_in' => 900,
            'interval' => 5,
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->initiateDeviceFlow();

    expect($result->deviceCode)->toBe('device-123')
        ->and($result->userCode)->toBe('ABCD-1234')
        ->and($result->verificationUri)->toBe('https://github.com/login/device')
        ->and($result->interval)->toBe(5);
});

it('polls for token and returns null when pending', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'authorization_pending',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->pollForToken('device-123');

    expect($result)->toBeNull();
});

it('polls for token and returns result when authorized', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'gho_test_token',
            'token_type' => 'bearer',
            'scope' => 'repo user',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->pollForToken('device-123');

    expect($result)->not->toBeNull()
        ->and($result->accessToken)->toBe('gho_test_token')
        ->and($result->tokenType)->toBe('bearer');
});

it('fetches user profile', function () {
    Http::fake([
        'api.github.com/user' => Http::response([
            'login' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'avatar_url' => 'https://avatars.github.com/u/123',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $profile = $service->getUserProfile('gho_test_token');

    expect($profile->username)->toBe('testuser')
        ->and($profile->name)->toBe('Test User')
        ->and($profile->email)->toBe('test@example.com');
});

it('returns error string when token is expired', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'expired_token',
            'error_description' => 'The device code has expired.',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->pollForToken('device-123');

    expect($result)->toBe('The device code has expired.');
});

it('returns error string when access is denied', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'access_denied',
            'error_description' => 'The user has denied your application access.',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->pollForToken('device-123');

    expect($result)->toBe('The user has denied your application access.');
});

it('returns slow_down constant for slow_down response', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'slow_down',
            'interval' => 10,
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $result = $service->pollForToken('device-123');

    expect($result)->toBe(GitHubDeviceFlowService::SLOW_DOWN);
});

it('configures git identity', function () {
    Process::fake();

    $service = new GitHubDeviceFlowService('test-client-id');
    $service->configureGitIdentity('Test User', 'test@example.com');

    Process::assertRan(fn ($process) => str_contains($process->command, 'git config --global user.name'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'git config --global user.email'));
});

it('detects copilot access from profile', function () {
    $service = new GitHubDeviceFlowService('test-client-id');

    $profile = new GitHubProfile(
        username: 'testuser',
        name: 'Test User',
        email: 'test@example.com',
        avatarUrl: null,
        plan: 'pro',
    );

    expect($service->checkCopilotAccess($profile))->toBeTrue();
});

it('detects copilot for free plan users', function () {
    $service = new GitHubDeviceFlowService('test-client-id');

    $profile = new GitHubProfile(
        username: 'freeuser',
        name: null,
        email: null,
        avatarUrl: null,
        plan: 'free',
    );

    expect($service->checkCopilotAccess($profile))->toBeTrue();
});

it('parses plan from user profile response', function () {
    Http::fake([
        'api.github.com/user' => Http::response([
            'login' => 'prouser',
            'name' => 'Pro User',
            'email' => 'pro@example.com',
            'avatar_url' => null,
            'plan' => ['name' => 'pro', 'space' => 976562499],
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $profile = $service->getUserProfile('gho_test_token');

    expect($profile->plan)->toBe('pro')
        ->and($profile->copilotTier())->toBe('pro')
        ->and($profile->hasCopilotAccess())->toBeTrue();
});

it('defaults plan to free when not present', function () {
    Http::fake([
        'api.github.com/user' => Http::response([
            'login' => 'testuser',
            'name' => 'Test User',
            'email' => null,
            'avatar_url' => null,
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');
    $profile = $service->getUserProfile('gho_test_token');

    expect($profile->plan)->toBe('free')
        ->and($profile->copilotTier())->toBe('free')
        ->and($profile->hasCopilotAccess())->toBeTrue();
});
