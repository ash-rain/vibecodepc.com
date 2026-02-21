<?php

use App\Models\CloudCredential;
use App\Models\DeviceState;
use App\Services\DeviceStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('getMode returns pairing when no credentials exist', function () {
    $service = new DeviceStateService();

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

    $service = new DeviceStateService();

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

    $service = new DeviceStateService();

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

    $service = new DeviceStateService();

    expect($service->getMode())->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('setMode updates the device mode', function () {
    $service = new DeviceStateService();

    $service->setMode(DeviceStateService::MODE_WIZARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_WIZARD);

    $service->setMode(DeviceStateService::MODE_DASHBOARD);
    expect(DeviceState::getValue(DeviceStateService::MODE_KEY))->toBe(DeviceStateService::MODE_DASHBOARD);
});

it('isPairing returns true when not paired', function () {
    $service = new DeviceStateService();

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

    $service = new DeviceStateService();

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

    $service = new DeviceStateService();

    expect($service->isDashboard())->toBeTrue()
        ->and($service->isPairing())->toBeFalse()
        ->and($service->isWizard())->toBeFalse();
});
