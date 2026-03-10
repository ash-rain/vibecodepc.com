<?php

use App\Jobs\ProvisionQuickTunnelJob;
use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function setupFakeDeviceIdentity(string $uuid): void
{
    $deviceJson = json_encode([
        'id' => $uuid,
        'hardware_serial' => 'test-serial',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ]);

    $path = storage_path('test-device.json');
    file_put_contents($path, $deviceJson);
    config(['vibecodepc.device_json_path' => $path]);

    // Re-register the singleton so it picks up the new config
    app()->singleton(
        DeviceIdentityService::class,
        fn () => new DeviceIdentityService($path),
    );
}

afterEach(function () {
    $path = storage_path('test-device.json');
    if (file_exists($path)) {
        unlink($path);
    }

    Cache::forget('device:registration:last');

    // Clean up CloudCredentials to ensure test isolation
    CloudCredential::query()->delete();
});

it('fails when no device identity file exists', function () {
    config(['vibecodepc.device_json_path' => '/tmp/nonexistent-device.json']);
    app()->singleton(
        DeviceIdentityService::class,
        fn () => new DeviceIdentityService('/tmp/nonexistent-device.json'),
    );

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('No device identity found')
        ->assertExitCode(1);
});

it('checks cloud API and exits when device is unclaimed', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Device registered with cloud')
        ->expectsOutputToContain("Checking pairing status for device: {$uuid}")
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('creates a CloudCredential and dispatches tunnel job when pairing is received', function () {
    Queue::fake();

    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                'token' => '1|abc123',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'ip_hint' => '192.168.1.100',
            ],
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Device has been claimed!')
        ->expectsOutputToContain('Paired to: testuser (test@example.com)')
        ->expectsOutputToContain('Tunnel provisioning dispatched.')
        ->assertExitCode(0);

    $credential = CloudCredential::current();
    expect($credential)->not->toBeNull()
        ->and($credential->cloud_username)->toBe('testuser')
        ->and($credential->cloud_email)->toBe('test@example.com')
        ->and($credential->cloud_url)->toBe($cloudUrl)
        ->and($credential->isPaired())->toBeTrue()
        ->and($credential->paired_at)->not->toBeNull();

    Queue::assertPushed(ProvisionQuickTunnelJob::class);
});

it('transitions device mode to wizard after pairing', function () {
    Queue::fake();

    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                'token' => '1|abc123',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'ip_hint' => '192.168.1.100',
            ],
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->assertExitCode(0);

    $mode = DeviceState::getValue(DeviceStateService::MODE_KEY);
    expect($mode)->toBe(DeviceStateService::MODE_WIZARD);
});

it('registers device with cloud before checking status', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Device registered with cloud')
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/devices/register'));
});

it('continues checking even if device registration fails', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 500),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Failed to register device with cloud')
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);
});

it('rate-limits device registration to once per minute', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    // First call registers
    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Device registered with cloud')
        ->assertExitCode(0);

    // Second call skips registration (cached)
    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Device registered with cloud')
        ->assertExitCode(0);

    Http::assertSentCount(3); // 1 register + 2 status checks
});

it('exits silently when already paired', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
    ]);

    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Checking pairing status')
        ->assertExitCode(0);
});

it('handles cloud API connection errors gracefully', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(null, 500),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles cloud API timeout gracefully', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles malformed response from cloud API gracefully', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['invalid' => 'data'], 200),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->assertExitCode(0);
});

it('handles cloud API returning 404 for unknown device', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Device not found'], 404),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles unpaired credential record that is not marked as paired', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    // Ensure clean state
    CloudCredential::query()->delete();

    // Create initial unpaired credential
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => false,
        'paired_at' => null,
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                'token' => '2|def456',
                'username' => 'newuser',
                'email' => 'new@example.com',
                'ip_hint' => '192.168.1.101',
            ],
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Device has been claimed!')
        ->assertExitCode(0);

    // Verify a paired credential now exists (either updated or newly created)
    $pairedCount = CloudCredential::where('is_paired', true)->count();
    expect($pairedCount)->toBeGreaterThanOrEqual(1);
});

it('handles paired state with no CloudCredential record at all', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

// Edge case tests for network failures, already paired state, and invalid device responses

it('handles ConnectionException during device registration', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        },
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Failed to register device with cloud')
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);
});

it('handles DNS resolution failure during status check', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
        },
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles already paired state with multiple CloudCredential records', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    // Create multiple paired records (shouldn't happen, but test it)
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token-1',
        'cloud_username' => 'testuser1',
        'cloud_email' => 'test1@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now()->subDays(2),
    ]);

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token-2',
        'cloud_username' => 'testuser2',
        'cloud_email' => 'test2@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now()->subDay(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
    ]);

    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Checking pairing status')
        ->assertExitCode(0);

    // Verify at least one paired credential exists (command should exit early)
    $credential = CloudCredential::current();
    expect($credential)->not->toBeNull()
        ->and($credential->isPaired())->toBeTrue();
});

it('handles already paired state with expired credential', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    // Create a paired record but mark it as not currently active
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now()->subYear(),
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
    ]);

    // Command should still exit silently since isPaired() returns true
    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Checking pairing status')
        ->assertExitCode(0);
});

it('handles invalid pairing data with missing token', function () {
    Queue::fake();
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                // Missing 'token' field
                'username' => 'testuser',
                'email' => 'test@example.com',
                'ip_hint' => '192.168.1.100',
            ],
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('handles invalid pairing data with missing username', function () {
    Queue::fake();
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => [
                'device_id' => $uuid,
                'token' => '1|abc123',
                // Missing 'username' field
                'email' => 'test@example.com',
                'ip_hint' => '192.168.1.100',
            ],
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('handles invalid pairing data with null pairing object', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'claimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Status: claimed')
        ->assertExitCode(0);

    // Should not create credential when pairing is null
    expect(CloudCredential::count())->toBe(0);
});

it('handles invalid device response with missing status field', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            // Missing 'status' field
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);
});

it('handles network timeout during getDeviceStatus with specific error message', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Request timed out after 10 seconds');
        },
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed: Request timed out after 10 seconds')
        ->assertExitCode(0);
});

it('handles SSL certificate error during API call', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('SSL certificate problem: unable to get local issuer certificate');
        },
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles HTTP 503 Service Unavailable from cloud API', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Service temporarily unavailable'], 503),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles HTTP 502 Bad Gateway from cloud API', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Bad Gateway'], 502),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles HTTP 429 Too Many Requests from cloud API', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response(['error' => 'Rate limit exceeded'], 429),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles already paired state but cloud reports unclaimed', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    // Device thinks it's paired locally
    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    // But cloud says unclaimed - this shouldn't happen but test it
    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    // Should exit silently because local state says paired
    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Checking pairing status')
        ->assertExitCode(0);
});

it('handles device registration with 503 response', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(['error' => 'Service unavailable'], 503),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Failed to register device with cloud')
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);
});

it('handles empty response body from cloud API', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response('', 200),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles invalid JSON response from cloud API', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response('not valid json', 200),
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles network failure during both registration and status check', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Network is unreachable');
        },
        "{$cloudUrl}/api/devices/{$uuid}/status" => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Network is unreachable');
        },
    ]);

    $this->artisan('device:poll-pairing')
        ->expectsOutputToContain('Failed to register device with cloud')
        ->expectsOutputToContain('Checking pairing status')
        ->expectsOutputToContain('Poll failed:')
        ->assertExitCode(0);

    expect(CloudCredential::count())->toBe(0);
});

it('handles already paired with is_paired true but paired_at is null', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    CloudCredential::create([
        'pairing_token_encrypted' => 'test-token',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => $cloudUrl,
        'is_paired' => true,
        'paired_at' => null,
    ]);

    Http::fake([
        "{$cloudUrl}/api/devices/register" => Http::response(null, 200),
    ]);

    // Should exit silently since isPaired() checks is_paired field
    $this->artisan('device:poll-pairing')
        ->doesntExpectOutputToContain('Checking pairing status')
        ->assertExitCode(0);
});
