<?php

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DeviceRegistry\DeviceIdentityService;
use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
});

it('fails when no device identity file exists', function () {
    config(['vibecodepc.device_json_path' => '/tmp/nonexistent-device.json']);
    app()->singleton(
        DeviceIdentityService::class,
        fn () => new DeviceIdentityService('/tmp/nonexistent-device.json'),
    );

    $this->artisan('device:poll-pairing', ['--once' => true])
        ->expectsOutputToContain('No device identity found')
        ->assertExitCode(1);
});

it('polls cloud API and exits when device is unclaimed with --once flag', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
        "{$cloudUrl}/api/devices/{$uuid}/status" => Http::response([
            'device_id' => $uuid,
            'status' => 'unclaimed',
            'pairing' => null,
        ]),
    ]);

    $this->artisan('device:poll-pairing', ['--once' => true])
        ->expectsOutputToContain("Polling pairing status for device: {$uuid}")
        ->expectsOutputToContain('Status: unclaimed')
        ->assertExitCode(0);

    Http::assertSentCount(1);
    expect(CloudCredential::count())->toBe(0);
});

it('creates a CloudCredential when pairing is received', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
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

    $this->artisan('device:poll-pairing', ['--once' => true])
        ->expectsOutputToContain('Device has been claimed!')
        ->expectsOutputToContain('Paired to: testuser (test@example.com)')
        ->assertExitCode(0);

    $credential = CloudCredential::current();
    expect($credential)->not->toBeNull()
        ->and($credential->cloud_username)->toBe('testuser')
        ->and($credential->cloud_email)->toBe('test@example.com')
        ->and($credential->cloud_url)->toBe($cloudUrl)
        ->and($credential->isPaired())->toBeTrue()
        ->and($credential->paired_at)->not->toBeNull();
});

it('transitions device mode to wizard after pairing', function () {
    $uuid = (string) Str::uuid();
    $cloudUrl = config('vibecodepc.cloud_url');

    setupFakeDeviceIdentity($uuid);

    Http::fake([
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

    $this->artisan('device:poll-pairing', ['--once' => true])
        ->assertExitCode(0);

    $mode = DeviceState::getValue(DeviceStateService::MODE_KEY);
    expect($mode)->toBe(DeviceStateService::MODE_WIZARD);
});
