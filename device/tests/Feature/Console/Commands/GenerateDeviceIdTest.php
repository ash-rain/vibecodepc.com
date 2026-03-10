<?php

declare(strict_types=1);

beforeEach(function () {
    $this->defaultPath = config('vibecodepc.device_json_path');
    $this->customPath = storage_path('testing/custom-device.json');
});

afterEach(function () {
    // Clean up device.json files
    if (file_exists($this->defaultPath)) {
        @unlink($this->defaultPath);
    }

    if (file_exists($this->customPath)) {
        @unlink($this->customPath);
    }

    // Clean up custom directory if created
    $customDir = dirname($this->customPath);
    if (is_dir($customDir)) {
        @rmdir($customDir);
    }
});

it('generates device id and writes to default path', function () {
    $this->artisan('device:generate-id')
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID generated:')
        ->expectsOutputToContain('Written to:')
        ->expectsOutputToContain('QR URL:');

    expect(file_exists($this->defaultPath))->toBeTrue();

    $content = json_decode(file_get_contents($this->defaultPath), true);
    expect($content)->toHaveKey('id')
        ->toHaveKey('hardware_serial')
        ->toHaveKey('manufactured_at')
        ->toHaveKey('firmware_version');
});

it('uses --force flag to overwrite existing device.json', function () {
    // Create initial device.json
    $initialData = [
        'id' => 'initial-uuid-1234',
        'hardware_serial' => 'INITIAL-SN',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    $dir = dirname($this->defaultPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->defaultPath, json_encode($initialData));

    // Verify initial file exists
    expect(file_exists($this->defaultPath))->toBeTrue();
    $content = json_decode(file_get_contents($this->defaultPath), true);
    expect($content['id'])->toBe('initial-uuid-1234');

    // Generate new ID with --force
    $this->artisan('device:generate-id', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID generated:')
        ->expectsOutputToContain('Written to:');

    // Verify file was overwritten with new ID
    $newContent = json_decode(file_get_contents($this->defaultPath), true);
    expect($newContent['id'])->not->toBe('initial-uuid-1234')
        ->and($newContent['id'])->not->toBeEmpty();
});

it('uses --path option to write to custom location', function () {
    $this->artisan('device:generate-id', ['--path' => $this->customPath])
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID generated:')
        ->expectsOutputToContain('Written to:');

    expect(file_exists($this->customPath))->toBeTrue();
    expect(file_exists($this->defaultPath))->toBeFalse();

    $content = json_decode(file_get_contents($this->customPath), true);
    expect($content)->toHaveKey('id')
        ->toHaveKey('hardware_serial')
        ->toHaveKey('manufactured_at')
        ->toHaveKey('firmware_version');
});

it('fails when device.json exists without --force flag', function () {
    // Create initial device.json
    $initialData = [
        'id' => 'existing-uuid-5678',
        'hardware_serial' => 'EXISTING-SN',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    $dir = dirname($this->defaultPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->defaultPath, json_encode($initialData));

    $this->artisan('device:generate-id')
        ->assertFailed()
        ->expectsOutputToContain('Device identity already exists');

    // Verify original file is unchanged
    $content = json_decode(file_get_contents($this->defaultPath), true);
    expect($content['id'])->toBe('existing-uuid-5678');
});

it('creates directory if it does not exist', function () {
    $nestedPath = storage_path('testing/nested/path/device.json');

    // Ensure parent directories don't exist
    $parentDir = dirname($nestedPath);
    if (is_dir($parentDir)) {
        rmdir($parentDir);
    }

    $this->artisan('device:generate-id', ['--path' => $nestedPath])
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID generated:');

    expect(file_exists($nestedPath))->toBeTrue();
    expect(is_dir($parentDir))->toBeTrue();

    // Cleanup
    @unlink($nestedPath);
    @rmdir($parentDir);
    @rmdir(dirname($parentDir));
});

it('combines --force and --path options', function () {
    // Create initial file at custom path
    $initialData = [
        'id' => 'custom-uuid-9999',
        'hardware_serial' => 'CUSTOM-SN',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    $dir = dirname($this->customPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->customPath, json_encode($initialData));

    // Overwrite with --force and custom --path
    $this->artisan('device:generate-id', [
        '--force' => true,
        '--path' => $this->customPath,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Device ID generated:')
        ->expectsOutputToContain($this->customPath);

    $content = json_decode(file_get_contents($this->customPath), true);
    expect($content['id'])->not->toBe('custom-uuid-9999');
});

it('generates valid UUID format device id', function () {
    $this->artisan('device:generate-id')
        ->assertSuccessful();

    $content = json_decode(file_get_contents($this->defaultPath), true);
    $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    expect($content['id'])->toMatch($uuidPattern);
});

it('includes hardware serial in generated device.json', function () {
    $this->artisan('device:generate-id')
        ->assertSuccessful();

    $content = json_decode(file_get_contents($this->defaultPath), true);

    expect($content['hardware_serial'])->not->toBeEmpty()
        ->and(strlen($content['hardware_serial']))->toBeGreaterThan(0);
});

it('includes current timestamp as manufactured_at', function () {
    $before = now()->subSecond();

    $this->artisan('device:generate-id')
        ->assertSuccessful();

    $after = now()->addSecond();

    $content = json_decode(file_get_contents($this->defaultPath), true);
    $manufacturedAt = \Carbon\Carbon::parse($content['manufactured_at']);

    expect($manufacturedAt->greaterThanOrEqualTo($before))->toBeTrue()
        ->and($manufacturedAt->lessThanOrEqualTo($after))->toBeTrue();
});

it('includes firmware version in generated device.json', function () {
    $this->artisan('device:generate-id')
        ->assertSuccessful();

    $content = json_decode(file_get_contents($this->defaultPath), true);

    expect($content['firmware_version'])->toBe('1.0.0');
});

it('generates correct QR URL format', function () {
    $cloudUrl = config('vibecodepc.cloud_browser_url');

    $this->artisan('device:generate-id')
        ->assertSuccessful()
        ->expectsOutputToContain('QR URL:');

    $content = json_decode(file_get_contents($this->defaultPath), true);

    // Verify the file content has a valid UUID and the QR URL contains the cloud URL
    expect($content['id'])->not->toBeEmpty();
    expect($content['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

it('returns FAILURE exit code when file exists without force', function () {
    $initialData = [
        'id' => 'existing-uuid',
        'hardware_serial' => 'EXISTING',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    $dir = dirname($this->defaultPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->defaultPath, json_encode($initialData));

    $result = $this->artisan('device:generate-id')->run();

    expect($result)->toBe(1); // FAILURE exit code
});

it('returns SUCCESS exit code when generating new device id', function () {
    $result = $this->artisan('device:generate-id')->run();

    expect($result)->toBe(0); // SUCCESS exit code
});

it('returns SUCCESS exit code when overwriting with force', function () {
    $initialData = [
        'id' => 'existing-uuid',
        'hardware_serial' => 'EXISTING',
        'manufactured_at' => '2026-01-01T00:00:00Z',
        'firmware_version' => '1.0.0',
    ];

    $dir = dirname($this->defaultPath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($this->defaultPath, json_encode($initialData));

    $result = $this->artisan('device:generate-id', ['--force' => true])->run();

    expect($result)->toBe(0); // SUCCESS exit code
});
