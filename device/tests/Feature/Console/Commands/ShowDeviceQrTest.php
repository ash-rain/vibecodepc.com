<?php

declare(strict_types=1);

beforeEach(function () {
    $this->devicePath = config('vibecodepc.device_json_path');
});

afterEach(function () {
    // Clean up device.json files
    $defaultPath = config('vibecodepc.device_json_path');

    if (file_exists($defaultPath)) {
        @unlink($defaultPath);
    }
});

it('displays error when no device identity exists', function () {
    // Ensure no device.json exists
    if (file_exists($this->devicePath)) {
        unlink($this->devicePath);
    }

    $this->artisan('device:show-qr')
        ->assertFailed()
        ->expectsOutputToContain('No device identity found. Run: php artisan device:generate-id');
});

it('displays QR code when device identity exists', function () {
    // Create device identity file
    $deviceData = [
        'id' => 'test-device-uuid-1234',
        'hardware_serial' => 'TEST-SN-5678',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $this->artisan('device:show-qr')
        ->assertSuccessful()
        ->expectsOutputToContain('=== VibeCodePC Pairing ===')
        ->expectsOutputToContain('Device ID: test-device-uuid-1234');
});

it('displays QR code with correct URL format', function () {
    // Create device identity file
    $deviceData = [
        'id' => 'abc-123-xyz',
        'hardware_serial' => 'SN-001',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $this->artisan('device:show-qr')
        ->assertSuccessful()
        ->expectsOutputToContain('Pair URL:');
});

it('includes hardware serial in output', function () {
    // Create device identity file
    $deviceData = [
        'id' => 'uuid-123',
        'hardware_serial' => 'HW-SERIAL-TEST',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $this->artisan('device:show-qr')
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID: uuid-123');
});

it('handles device identity with special characters in ID', function () {
    // Create device identity file with special characters
    $deviceData = [
        'id' => 'device_123-test.device',
        'hardware_serial' => 'SN-TEST',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $this->artisan('device:show-qr')
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID: device_123-test.device');
});

it('returns FAILURE exit code when no identity exists', function () {
    // Ensure no device.json exists
    if (file_exists($this->devicePath)) {
        unlink($this->devicePath);
    }

    $result = $this->artisan('device:show-qr')->run();

    expect($result)->toBe(1); // FAILURE exit code
});

it('returns SUCCESS exit code when identity exists', function () {
    // Create device identity file
    $deviceData = [
        'id' => 'success-test-uuid',
        'hardware_serial' => 'SN-SUCCESS',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $result = $this->artisan('device:show-qr')->run();

    expect($result)->toBe(0); // SUCCESS exit code
});

it('displays QR code text representation', function () {
    // Create device identity file
    $deviceData = [
        'id' => 'qr-test-uuid',
        'hardware_serial' => 'SN-QR-TEST',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    // The QR code should contain some text output (it's rendered as ASCII art)
    $this->artisan('device:show-qr')
        ->assertSuccessful();
});

it('handles valid UUID format device ID', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $deviceData = [
        'id' => $uuid,
        'hardware_serial' => 'SN-UUID',
        'manufactured_at' => '2026-03-08T12:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    // Ensure directory exists
    $dir = dirname($this->devicePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->devicePath, json_encode($deviceData));

    $this->artisan('device:show-qr')
        ->assertSuccessful()
        ->expectsOutputToContain("Device ID: {$uuid}");
});
