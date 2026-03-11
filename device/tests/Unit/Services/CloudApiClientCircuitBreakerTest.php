<?php

use App\Services\CloudApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

it('opens circuit breaker after consecutive failures', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    // Mock consistent failures
    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // First 4 attempts should retry and fail
    try {
        $client->getDeviceStatus($uuid);
    } catch (RequestException $e) {
        // Expected
    }

    // Circuit should still be closed (failure threshold is 5)
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('closed');

    // Make 4 more calls to reach threshold of 5
    for ($i = 0; $i < 4; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Circuit should now be open
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('open')
        ->and($state['is_closed'])->toBeFalse();
});

it('fails fast when circuit is open', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Open the circuit with 5 failures
    for ($i = 0; $i < 5; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Verify circuit is open
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('open');

    // Next call should fail immediately with circuit breaker exception
    $startTime = microtime(true);
    try {
        $client->getDeviceStatus($uuid);
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
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';
    $callCount = 0;

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () use (&$callCount, $uuid) {
            $callCount++;
            // First 3 calls fail, then succeed
            if ($callCount <= 3) {
                return Http::response(['error' => 'Service unavailable'], 503);
            }

            return Http::response([
                'device_id' => $uuid,
                'status' => 'unclaimed',
                'pairing' => null,
            ]);
        },
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Make 3 failing calls
    for ($i = 0; $i < 3; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Should have some failures recorded but circuit still closed
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('closed');

    // Make a successful call
    $result = $client->getDeviceStatus($uuid);

    // Failure count should reset
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['failure_count'])->toBe(0);
});

it('allows manual reset of circuit breaker', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Open the circuit
    for ($i = 0; $i < 5; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (RequestException $e) {
            // Expected
        }
    }

    expect($client->getCircuitBreakerState()['state'])->toBe('open');

    // Reset the circuit
    $client->resetCircuitBreaker();

    // Circuit should be closed
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('closed')
        ->and($state['is_closed'])->toBeTrue()
        ->and($state['failure_count'])->toBe(0);
});

it('tracks circuit breaker state in getCircuitBreakerState', function () {
    $cloudUrl = 'https://vibecodepc.test';
    $client = new CloudApiClient($cloudUrl);

    $state = $client->getCircuitBreakerState();

    expect($state)->toHaveKeys(['state', 'failure_count', 'success_count', 'is_closed'])
        ->and($state['state'])->toBe('closed')
        ->and($state['is_closed'])->toBeTrue()
        ->and($state['failure_count'])->toBe(0)
        ->and($state['success_count'])->toBe(0);
});

it('skips non-critical operations when circuit is open', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/heartbeat" => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Create a TunnelConfig for testing
    \App\Models\TunnelConfig::factory()->create([
        'subdomain' => 'test',
        'status' => 'verified',
    ]);

    // Open the circuit with heartbeat failures
    for ($i = 0; $i < 5; $i++) {
        $client->sendHeartbeat($uuid, [
            'cpu_percent' => 50.0,
            'ram_used_mb' => 1024,
            'ram_total_mb' => 2048,
            'disk_used_gb' => 10.0,
            'disk_total_gb' => 100.0,
            'temperature_c' => null,
            'running_projects' => 1,
            'tunnel_active' => true,
            'firmware_version' => '1.0.0',
        ]);
    }

    // Verify circuit is open
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('open');

    // Next heartbeat should be skipped without throwing
    // (it logs debug message and returns early)
    $client->sendHeartbeat($uuid, [
        'cpu_percent' => 50.0,
        'ram_used_mb' => 1024,
        'ram_total_mb' => 2048,
        'disk_used_gb' => 10.0,
        'disk_total_gb' => 100.0,
        'temperature_c' => null,
        'running_projects' => 1,
        'tunnel_active' => true,
        'firmware_version' => '1.0.0',
    ]);

    // Should complete without throwing exception
    expect(true)->toBeTrue();
});

it('records success on successful requests', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Make successful call
    $client->getDeviceStatus($uuid);

    // Should have recorded success
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('closed');
});

it('handles connection exceptions with circuit breaker', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Make calls that will throw ConnectionException
    for ($i = 0; $i < 5; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (ConnectionException $e) {
            // Expected
        }
    }

    // Circuit should be open
    $state = $client->getCircuitBreakerState();
    expect($state['state'])->toBe('open');
});

it('applies circuit breaker to all critical operations', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = 'https://vibecodepc.test';

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Service unavailable'], 503),
        "{$cloudUrl}/api/devices/register" => Http::response(['error' => 'Service unavailable'], 503),
        "{$cloudUrl}/api/subdomains/test/availability" => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $client = new CloudApiClient($cloudUrl);

    // Open circuit with device status failures
    for ($i = 0; $i < 5; $i++) {
        try {
            $client->getDeviceStatus($uuid);
        } catch (RequestException $e) {
            // Expected
        }
    }

    // Verify circuit is open
    expect($client->getCircuitBreakerState()['state'])->toBe('open');

    // All critical operations should fail fast
    expect(function () use ($client, $uuid) {
        $client->getDeviceStatus($uuid);
    })->toThrow(\RuntimeException::class, 'Circuit breaker is OPEN');

    expect(function () use ($client) {
        $client->registerDevice(['name' => 'test']);
    })->toThrow(\RuntimeException::class, 'Circuit breaker is OPEN');

    expect(function () use ($client) {
        $client->checkSubdomainAvailability('test');
    })->toThrow(\RuntimeException::class, 'Circuit breaker is OPEN');
});
