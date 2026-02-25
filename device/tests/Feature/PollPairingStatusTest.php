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
