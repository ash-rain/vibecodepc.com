<?php

declare(strict_types=1);

use App\Services\DeviceRegistry\DeviceIdentityService;
use Illuminate\Support\Str;
use VibecodePC\Common\DTOs\DeviceInfo;

beforeEach(function () {
    $this->testDevicePath = storage_path('test-device-identity-'.Str::random(8).'.json');
});

afterEach(function () {
    if (file_exists($this->testDevicePath)) {
        unlink($this->testDevicePath);
    }
});

it('throws exception when device identity file does not exist', function () {
    $service = new DeviceIdentityService('/nonexistent/path/device.json');

    expect(fn () => $service->getDeviceInfo())
        ->toThrow(RuntimeException::class, 'Device identity file not found');
});

it('returns device info when file exists', function () {
    $expectedDevice = new DeviceInfo(
        id: 'test-id-123',
        hardwareSerial: 'serial-456',
        manufacturedAt: '2024-01-01T00:00:00Z',
        firmwareVersion: '1.0.0',
    );

    file_put_contents($this->testDevicePath, $expectedDevice->toJson());

    $service = new DeviceIdentityService($this->testDevicePath);
    $deviceInfo = $service->getDeviceInfo();

    expect($deviceInfo->id)->toBe('test-id-123')
        ->and($deviceInfo->hardwareSerial)->toBe('serial-456')
        ->and($deviceInfo->manufacturedAt)->toBe('2024-01-01T00:00:00Z')
        ->and($deviceInfo->firmwareVersion)->toBe('1.0.0');
});

it('caches device info after first read', function () {
    $expectedDevice = new DeviceInfo(
        id: 'test-id-789',
        hardwareSerial: 'serial-abc',
        manufacturedAt: '2024-02-01T00:00:00Z',
        firmwareVersion: '1.1.0',
    );

    file_put_contents($this->testDevicePath, $expectedDevice->toJson());

    $service = new DeviceIdentityService($this->testDevicePath);

    // First read
    $deviceInfo1 = $service->getDeviceInfo();

    // Modify file to test caching
    $newDevice = new DeviceInfo(
        id: 'different-id',
        hardwareSerial: 'different-serial',
        manufacturedAt: '2024-03-01T00:00:00Z',
        firmwareVersion: '2.0.0',
    );
    file_put_contents($this->testDevicePath, $newDevice->toJson());

    // Second read should return cached value
    $deviceInfo2 = $service->getDeviceInfo();

    expect($deviceInfo1->id)->toBe('test-id-789')
        ->and($deviceInfo2->id)->toBe('test-id-789'); // Still the original cached value
});

it('checks if identity file exists', function () {
    $service = new DeviceIdentityService($this->testDevicePath);

    expect($service->hasIdentity())->toBeFalse();

    $device = new DeviceInfo(
        id: 'test-id',
        hardwareSerial: 'test-serial',
        manufacturedAt: now()->toIso8601String(),
        firmwareVersion: '1.0.0',
    );
    file_put_contents($this->testDevicePath, $device->toJson());

    expect($service->hasIdentity())->toBeTrue();
});

it('generates correct pairing url', function () {
    $device = new DeviceInfo(
        id: 'device-123',
        hardwareSerial: 'serial-123',
        manufacturedAt: now()->toIso8601String(),
        firmwareVersion: '1.0.0',
    );
    file_put_contents($this->testDevicePath, $device->toJson());

    $service = new DeviceIdentityService($this->testDevicePath);
    $pairingUrl = $service->getPairingUrl();

    $expectedUrl = config('vibecodepc.cloud_browser_url').'/pair/device-123';
    expect($pairingUrl)->toBe($expectedUrl);
});

it('auto-generates device identity in test environment when using trait', function () {
    // This test verifies that HasTunnelFakes trait auto-generates device identity
    // We need to explicitly call setUpTunnelFakes for this unit test
    $this->setUpTunnelFakes();

    $path = config('vibecodepc.device_json_path');
    expect(file_exists($path))->toBeTrue();

    $service = app(DeviceIdentityService::class);
    $deviceInfo = $service->getDeviceInfo();

    // Verify device info is valid
    expect($deviceInfo->id)->not->toBeEmpty()
        ->and($deviceInfo->hardwareSerial)->not->toBeEmpty()
        ->and($deviceInfo->firmwareVersion)->not->toBeEmpty()
        ->and($deviceInfo->manufacturedAt)->not->toBeEmpty();
});
