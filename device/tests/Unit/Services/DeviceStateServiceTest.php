<?php

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('getMode returns pairing when no credentials exist', function () {
    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});

it('getMode returns pairing when credential exists but is not paired', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});

it('getMode returns wizard as default when paired but no mode is stored', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_WIZARD);
});

it('getMode returns stored mode when paired', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('getMode returns pairing when stored mode is wizard but no credentials exist', function () {
    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});

it('setMode updates the device mode', function () {
    $service = new DeviceStateService;

    $service->setMode(DeviceStateService::MODE_WIZARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_WIZARD);

    $service->setMode(DeviceStateService::MODE_DASHBOARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('isPairing returns true when not paired', function () {
    $service = new DeviceStateService;

    expect($service->isPairing())->toBeTrue()
        ->and($service->isWizard())->toBeFalse()
        ->and($service->isDashboard())->toBeFalse();
});

it('isWizard returns true when paired and in wizard mode', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);

    $service = new DeviceStateService;

    expect($service->isWizard())->toBeTrue()
        ->and($service->isPairing())->toBeFalse()
        ->and($service->isDashboard())->toBeFalse();
});

it('isDashboard returns true when paired and in dashboard mode', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $service = new DeviceStateService;

    expect($service->isDashboard())->toBeTrue()
        ->and($service->isPairing())->toBeFalse()
        ->and($service->isWizard())->toBeFalse();
});

// Edge Case Tests for Invalid State Transitions

it('setMode accepts any string value including invalid modes', function () {
    $service = new DeviceStateService;

    $service->setMode('invalid_mode');

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe('invalid_mode');
});

it('getMode returns invalid mode value when paired and stored mode is unrecognized', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, 'unrecognized_mode');

    $service = new DeviceStateService;

    expect($service->getMode())->toBe('unrecognized_mode');
});

it('isPairing returns false when mode is unrecognized but device is paired', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, 'unrecognized_mode');

    $service = new DeviceStateService;

    expect($service->isPairing())->toBeFalse()
        ->and($service->isWizard())->toBeFalse()
        ->and($service->isDashboard())->toBeFalse();
});

it('setMode stores empty string as mode', function () {
    $service = new DeviceStateService;

    $service->setMode('');

    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe('');
});

it('getMode returns empty string when paired and mode is set to empty', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, '');

    $service = new DeviceStateService;

    expect($service->getMode())->toBe('');
});

// Edge Case Tests for Missing Keys

it('getMode returns wizard when mode key is deleted from database', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
    DeviceState::where('key', DeviceStateService::MODE_KEY)->delete();

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_WIZARD);
});

it('getMode returns pairing when credentials deleted after mode was set', function () {
    $credential = CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
    $credential->delete();

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});

it('handles mode retrieval when database table is empty', function () {
    DeviceState::query()->delete();

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING);
});

// Edge Case Tests for Database/Cache Interactions

it('getMode reflects immediate state change without caching issues', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_WIZARD);
    $firstMode = $service->getMode();

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);
    $secondMode = $service->getMode();

    expect($firstMode)->toBe(DeviceStateService::MODE_WIZARD)
        ->and($secondMode)->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('concurrent mode updates result in last-write-wins behavior', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service1 = new DeviceStateService;
    $service2 = new DeviceStateService;

    $service1->setMode(DeviceStateService::MODE_WIZARD);
    $service2->setMode(DeviceStateService::MODE_DASHBOARD);

    $finalMode = DeviceState::getValue(DeviceStateService::MODE_KEY);

    expect($finalMode)->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('mode persists across multiple service instances', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service1 = new DeviceStateService;
    $service1->setMode(DeviceStateService::MODE_DASHBOARD);

    $service2 = new DeviceStateService;
    $mode = $service2->getMode();

    expect($mode)->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('handles rapid successive mode changes', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;

    $modes = [DeviceStateService::MODE_WIZARD, DeviceStateService::MODE_DASHBOARD, DeviceStateService::MODE_WIZARD];
    foreach ($modes as $mode) {
        $service->setMode($mode);
    }

    expect($service->getMode())->toBe(DeviceStateService::MODE_WIZARD);
});

it('handles mode with special characters and unicode', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;
    $specialMode = 'mode_with_special_chars_!@#$%^&*()_+-=[]{}|;\':",./<>?';

    $service->setMode($specialMode);

    expect($service->getMode())->toBe($specialMode);
});

it('handles unicode mode values', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;
    $unicodeMode = 'モード_Режим_模式';

    $service->setMode($unicodeMode);

    expect($service->getMode())->toBe($unicodeMode);
});

it('handles very long mode values', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    $service = new DeviceStateService;
    $longMode = str_repeat('a', 10000);

    $service->setMode($longMode);

    expect($service->getMode())->toBe($longMode);
});

it('isWizard returns false when paired but mode key was never set', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::query()->delete();

    $service = new DeviceStateService;

    expect($service->isWizard())->toBeTrue()
        ->and($service->isDashboard())->toBeFalse()
        ->and($service->isPairing())->toBeFalse();
});

it('handles multiple cloud credentials with only first considered', function () {
    CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_username' => 'firstuser',
        'cloud_email' => 'first@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    CloudCredential::create([
        'pairing_token_encrypted' => '2|def456',
        'cloud_username' => 'seconduser',
        'cloud_email' => 'second@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => false,
        'paired_at' => null,
    ]);

    $service = new DeviceStateService;

    expect($service->getMode())->not->toBe(DeviceStateService::MODE_PAIRING);
});

it('handles credential unpairing after mode was set', function () {
    $credential = CloudCredential::create([
        'pairing_token_encrypted' => '1|abc123',
        'cloud_username' => 'testuser',
        'cloud_email' => 'test@example.com',
        'cloud_url' => 'https://vibecodepc.com',
        'is_paired' => true,
        'paired_at' => now(),
    ]);

    DeviceState::setValue(DeviceStateService::MODE_KEY, DeviceStateService::MODE_DASHBOARD);

    $credential->update(['is_paired' => false, 'paired_at' => null]);

    $service = new DeviceStateService;

    expect($service->getMode())->toBe(DeviceStateService::MODE_PAIRING)
        ->and($service->isPairing())->toBeTrue();
});
