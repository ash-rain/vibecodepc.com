<?php

declare(strict_types=1);

use App\Services\GitHub\GitHubDeviceFlowService;
use App\Services\GitHub\GitHubProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

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

// Circuit Breaker Tests

it('opens circuit breaker after consecutive failures', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // First 4 attempts should fail but circuit still closed (threshold is 5)
    for ($i = 0; $i < 4; $i++) {
        try {
            $service->initiateDeviceFlow();
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Circuit should still be closed
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed');

    // Make 1 more call to reach threshold of 5
    try {
        $service->initiateDeviceFlow();
    } catch (RequestException $e) {
        // Expected
    }

    // Circuit should now be open
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('open')
        ->and($state['is_closed'])->toBeFalse();
});

it('fails fast when circuit is open', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Open the circuit with 5 failures
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->initiateDeviceFlow();
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Verify circuit is open
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('open');

    // Next call should fail immediately with circuit breaker exception
    $startTime = microtime(true);
    try {
        $service->initiateDeviceFlow();
        $this->fail('Expected RuntimeException was not thrown');
    } catch (\RuntimeException $e) {
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should fail fast (< 100ms, no HTTP call made)
        expect($duration)->toBeLessThan(100)
            ->and($e->getMessage())->toContain('Circuit breaker is OPEN');
    }
});

it('resets failure count on successful request', function () {
    $callCount = 0;

    Http::fake([
        'github.com/login/device/code' => function () use (&$callCount) {
            $callCount++;
            // First 3 calls fail, then succeed
            if ($callCount <= 3) {
                return Http::response(['error' => 'Service unavailable'], 503);
            }

            return Http::response([
                'device_code' => 'device-123',
                'user_code' => 'ABCD-1234',
                'verification_uri' => 'https://github.com/login/device',
                'expires_in' => 900,
                'interval' => 5,
            ]);
        },
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Make 3 failing calls
    for ($i = 0; $i < 3; $i++) {
        try {
            $service->initiateDeviceFlow();
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Should have some failures recorded but circuit still closed
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed');

    // Make a successful call
    $result = $service->initiateDeviceFlow();

    // Failure count should reset
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['failure_count'])->toBe(0);
});

it('allows manual reset of circuit breaker', function () {
    Http::fake([
        'github.com/login/device/code' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Open the circuit
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->initiateDeviceFlow();
        } catch (RequestException $e) {
            // Expected
        }
    }

    expect($service->getCircuitBreakerState()['state'])->toBe('open');

    // Reset the circuit
    $service->resetCircuitBreaker();

    // Circuit should be closed
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['is_closed'])->toBeTrue()
        ->and($state['failure_count'])->toBe(0);
});

it('tracks circuit breaker state', function () {
    $service = new GitHubDeviceFlowService('test-client-id');

    $state = $service->getCircuitBreakerState();

    expect($state)->toHaveKeys(['state', 'failure_count', 'success_count', 'is_closed'])
        ->and($state['state'])->toBe('closed')
        ->and($state['is_closed'])->toBeTrue()
        ->and($state['failure_count'])->toBe(0)
        ->and($state['success_count'])->toBe(0);
});

it('applies circuit breaker to pollForToken', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Open the circuit with 5 failures
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->pollForToken('device-123');
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Verify circuit is open
    expect($service->getCircuitBreakerState()['state'])->toBe('open');

    // pollForToken should fail fast
    expect(function () use ($service) {
        $service->pollForToken('device-123');
    })->toThrow(\RuntimeException::class, 'Circuit breaker is OPEN');
});

it('handles connection exceptions with circuit breaker', function () {
    Http::fake([
        'github.com/login/device/code' => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Make calls that will throw ConnectionException
    for ($i = 0; $i < 5; $i++) {
        try {
            $service->initiateDeviceFlow();
        } catch (ConnectionException $e) {
            // Expected
        }
    }

    // Circuit should be open
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('open');
});

it('records success on successful token poll', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'access_token' => 'gho_test_token',
            'token_type' => 'bearer',
            'scope' => 'repo user',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Make successful call
    $result = $service->pollForToken('device-123');

    // Should have recorded success
    expect($result)->not->toBeNull()
        ->and($service->getCircuitBreakerState()['state'])->toBe('closed');
});

it('does not record failure for authorization_pending response', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'authorization_pending',
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Make multiple calls with authorization_pending
    for ($i = 0; $i < 10; $i++) {
        $result = $service->pollForToken('device-123');
        expect($result)->toBeNull();
    }

    // Circuit should still be closed (authorization_pending is not a failure)
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['failure_count'])->toBe(0);
});

it('does not record failure for slow_down response', function () {
    Http::fake([
        'github.com/login/oauth/access_token' => Http::response([
            'error' => 'slow_down',
            'interval' => 10,
        ]),
    ]);

    $service = new GitHubDeviceFlowService('test-client-id');

    // Make multiple calls with slow_down
    for ($i = 0; $i < 10; $i++) {
        $result = $service->pollForToken('device-123');
        expect($result)->toBe(GitHubDeviceFlowService::SLOW_DOWN);
    }

    // Circuit should still be closed (slow_down is not a failure)
    $state = $service->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['failure_count'])->toBe(0);
});
